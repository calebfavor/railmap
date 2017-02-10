<?php

namespace Railroad\Railmap\DataMapper;

use Carbon\Carbon;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Events\Dispatcher;
use Railroad\Railmap\Entity\EntityInterface;
use Railroad\Railmap\Entity\Links\LinkBase;
use Railroad\Railmap\Entity\Links\LinkFactory;
use Railroad\Railmap\Entity\Links\ManyToMany;
use Railroad\Railmap\Entity\Links\OneToMany;
use Railroad\Railmap\Entity\Links\OneToOne;
use Railroad\Railmap\Events\EntityCreated;
use Railroad\Railmap\Events\EntityDestroyed;
use Railroad\Railmap\Events\EntitySaved;
use Railroad\Railmap\Events\EntityUpdated;
use Railroad\Railmap\Helpers\RailmapHelpers;

abstract class DatabaseDataMapperBase extends DataMapperBase
{
    public $cacheTime = null;

    /**
     * @var $databaseManager DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var $cacheManager CacheRepository
     */
    protected $cacheRepository;

    public function __construct()
    {
        parent::__construct();

        $this->databaseManager = app(DatabaseManager::class);
        $this->cacheRepository = app(CacheRepository ::class);
    }

    /**
     * @param int $id
     * @return null|EntityInterface
     */
    public function get($id)
    {
        return $this->getMany([$id])[0] ?? null;
    }

    /**
     * @param int[] $ids
     * @return null|EntityInterface|EntityInterface[]
     */
    public function getMany($ids)
    {
        // First we check if any of the ids already exist in the identity map, if they do we only pull
        // the ones that don't already exist.

        $entitiesAlreadyInIdentityMap = $this->identityMap->getMany(get_class($this->entity()), $ids);
        $ids = array_diff(RailmapHelpers::entityArrayColumn($entitiesAlreadyInIdentityMap, 'getId'), $ids);

        // If all the requested entities are already in the map we can just return them
        if (empty($ids)) {
            return $entitiesAlreadyInIdentityMap;
        }

        $query = $this->gettingQuery()->whereIn($this->table . '.id', $ids);

        // This checks if the query is cache and returns the cached results if it is,
        // or it actually queries the database.
        $rows = $this->executeQueryOrGetCached(
            $query,
            function (Builder $query) {
                return $query->get();
            }
        );

        $entities = [];

        // Build the actual entities from the row data
        foreach ($rows as $row) {
            $entity = $this->entity();
            $entity->fill($this->afterGet((array)$row));

            // Since we are already filtering entities that exist we dont need to check if it exists in the
            // identity map before storing it.
            $this->identityMap->store($entity, $entity->getId());

            $entities[$entity->getId()] = $entity;
        }

        // Make sure we also include any identities that were already in the map
        $entities = array_merge($entities, $entitiesAlreadyInIdentityMap);

        // This restores the results to the original order of how the ids were passed in
        $orderedEntities = [];

        foreach ($ids as $id) {
            if (!empty($entities[$id])) {
                $orderedEntities[] = $entities[$id];
            }
        }

        // Process all the links that are always pulled and return final entities
        return $this->processWithLinks($orderedEntities);
    }

    /**
     * @param callable|null $queryCallback
     * @return int
     */
    public function count(callable $queryCallback = null)
    {
        $query = $this->gettingQuery();

        if (is_callable($queryCallback)) {
            $query = $queryCallback($query);
        }

        return $this->executeQueryOrGetCached(
            $query,
            function (Builder $query) {
                return $query->count();
            }
        );
    }

    /**
     * @param callable|null $queryCallback
     * @return boolean
     */
    public function exists(callable $queryCallback = null)
    {
        $query = $this->gettingQuery();

        if (is_callable($queryCallback)) {
            $query = $queryCallback($query);
        }

        return $this->executeQueryOrGetCached(
            $query,
            function (Builder $query) {
                return $query->exists();
            }
        );
    }

    /**
     * @param callable $queryCallback
     * @param bool $forceArrayReturn
     * @return EntityInterface[]
     */
    public function getWithQuery(callable $queryCallback, $forceArrayReturn = false)
    {
        $query = $queryCallback($this->gettingQuery());

        $rows = $this->executeQueryOrGetCached(
            $query,
            function (Builder $query) {
                return $query->get();
            }
        );

        $entities = [];

        foreach ($rows as $row) {
            $entity = $this->entity();
            $entity->fill($this->afterGet((array)$row));

            $entity = $this->identityMap->getOrStore($entity->getId(), $entity);

            $entities[] = $entity;
        }

        return $this->processWithLinks($entities);
    }

    /**
     * @return Builder
     */
    public function baseQuery()
    {
        $query = $this->databaseManager->connection()->query();

        $query->from($this->table);

        // If the entity has soft deletes, we only want to pull the non-soft deleted rows
        if (isset(array_flip($this->mapTo())['deleted_at'])) {
            $query->whereNull($this->table . '.deleted_at');
        }

        // If the entity has version-ing, we only want to pull version masters
        if (isset(array_flip($this->mapTo())['version_master_id'])) {
            $query->whereNull($this->table . '.version_master_id');
        }

        return $query;
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function filter($query)
    {
        return $query;
    }

    /**
     * @return Builder
     */
    public function gettingQuery()
    {
        return $this->filter($this->baseQuery());
    }

    /**
     * @return Builder
     */
    public function settingQuery()
    {
        return $this->baseQuery();
    }

    /**
     * @return Dispatcher
     */
    public function dispatcher()
    {
        return app(Dispatcher::class);
    }

    /**
     * @return array
     */
    public function types()
    {
        return [];
    }

    /**
     * @param array|null $withOverride
     * @return array|DatabaseDataMapperBase
     */
    public function with($withOverride = null)
    {
        if (!is_null($withOverride)) {
            $this->with = $withOverride;

            return $this;
        }

        return $this->with;
    }

    /**
     * @return LinkBase[]
     */
    public function links()
    {
        return [];
    }

    /**
     * This maps an array of row/columns to an entity.
     * If set, will look for the map keys using the $dataKeyPrefix.
     *
     * Ex. $entity->setName($data[$dataKeyPrefix . 'name']);
     *
     * @param EntityInterface $entity
     * @param array $data
     * @param string $dataKeyPrefix
     */
    public function fill(EntityInterface $entity, array $data, $dataKeyPrefix = '')
    {
        foreach ($this->mapFrom() as $entityPropertyName => $extractedName) {
            $setMethodName = 'set' . ucwords($entityPropertyName);

            if (method_exists($entity, $setMethodName) && isset($data[$dataKeyPrefix . $extractedName])) {
                $entity->$setMethodName($data[$dataKeyPrefix . $extractedName]);
            }
        }
    }

    /**
     * This maps an entity back to an array of row/columns.
     * If set, will prepend $dataKeyPrefix to all keys.
     *
     * @param EntityInterface $entity
     * @param string $dataKeyPrefix
     * @return array
     */
    public function extract(EntityInterface $entity, $dataKeyPrefix = '')
    {
        $extractedData = [];

        foreach ($this->mapTo() as $entityPropertyName => $extractedName) {
            $getMethodName = 'get' . ucwords($entityPropertyName);

            if (method_exists($entity, $getMethodName)) {
                $extractedData[$dataKeyPrefix . $extractedName] = $entity->$getMethodName();
            }
        }

        return $extractedData;
    }

    /**
     * @param EntityInterface|EntityInterface[] $entityOrEntities
     */
    public function persist($entityOrEntities)
    {
        if (empty($entityOrEntities)) {
            return;
        }

        if (!is_array($entityOrEntities)) {
            $entityOrEntities = [$entityOrEntities];
        }

        /** @var EntityInterface|EntityInterface[] $entityOrEntities */
        $entityOrEntities = array_filter($entityOrEntities);

        foreach ($entityOrEntities as $entity) {
            if (method_exists($entity, 'setUpdatedAt')) {
                $entity->setUpdatedAt(Carbon::now()->toDateTimeString());
            }

            if (empty($entity->getId())) {
                if (method_exists($entity, 'setCreatedAt')) {
                    $entity->setCreatedAt(Carbon::now()->toDateTimeString());
                }

                $oldEntity = null;

                $this->settingQuery()->insert($this->beforePersist($this->extract($entity)));

                $entity->setId($this->settingQuery()->getConnection()->getPdo()->lastInsertId('id'));

                $this->identityMap->store($entity, $entity->getId());
            } else {
                $oldEntity = $this->get($entity->getId());

                if (method_exists($entity, 'setVersionMasterId')) {
                    $this->saveVersion($oldEntity, $entity);
                }

                $this->settingQuery()->where([$this->table . '.id' => $entity->getId()])->take(1)->update(
                    $this->beforePersist($this->extract($entity))
                );
            }

            $this->dispatcher()->fire(new EntitySaved($entity, $oldEntity));

            if (!is_null($oldEntity)) {
                $this->dispatcher()->fire(new EntityUpdated($entity, $oldEntity));
            } else {
                $this->dispatcher()->fire(new EntityCreated($entity, $oldEntity));
            }
        }
    }

    public function saveVersion(EntityInterface $oldEntity, EntityInterface $newEntity)
    {
        $version = clone $newEntity;

        foreach ($oldEntity->versionedAttributes as $versionedAttribute) {
            if (call_user_func([$oldEntity, 'get' . ucwords($versionedAttribute)]) !==
                call_user_func([$newEntity, 'get' . ucwords($versionedAttribute)])
            ) {
                $version->setVersionMasterId($newEntity->getId());
                $version->setVersionSavedAt(Carbon::now()->toDateTimeString());
                $version->setId(null);

                $this->settingQuery()->insert($this->beforePersist($this->extract($version)));

                return;
            }
        }
    }

    /** |EntityInterface[]|integer|integer[]
     *
     * @param EntityInterface[] $entityOfEntitiesOrIdIds
     */
    public function destroy($entityOfEntitiesOrIdIds)
    {
        if (empty($entityOfEntitiesOrIdIds)) {
            return;
        }

        if (!is_array($entityOfEntitiesOrIdIds)) {
            $entityOfEntitiesOrIdIds = [$entityOfEntitiesOrIdIds];
        }

        $entityOfEntitiesOrIdIds = array_filter($entityOfEntitiesOrIdIds);

        if (!is_object(reset($entityOfEntitiesOrIdIds))) {
            $entitiesToDelete = $this->get($entityOfEntitiesOrIdIds);
        } else {
            $entitiesToDelete = $entityOfEntitiesOrIdIds;
        }

        /** @var $entitiesToDelete EntityInterface[] */
        $entitiesToDelete = array_filter($entitiesToDelete);

        foreach ($entitiesToDelete as $entityToDelete) {
            $this->identityMap->remove(get_class($this->entity()), $entityToDelete->getId());

            // soft deletes
            if (isset(array_flip($this->mapTo())['deleted_at']) &&
                method_exists($entityToDelete, 'setDeletedAt')
            ) {
                $entityToDelete->setDeletedAt(Carbon::now()->toDateTimeString());
                $entityToDelete->persist();
            } else {
                $this->settingQuery()->where($this->table . '.id', $entityToDelete->getId())->delete();
            }
        }

        // Delete all pivot table entries
        foreach ($entityOfEntitiesOrIdIds as $entity) {
            if (!is_object($entity)) {
                continue;
            }

            foreach (LinkFactory::getManyToManyLinks($this->links()) as $link) {
                call_user_func([$entity, 'get' . ucwords($link->localEntityPropertyToSet)]);
            }

            foreach ($entity->getLoadedLinkedPivotEntities() as $linkPropertyName => $linkedPivotEntities) {
                foreach ($linkedPivotEntities as $linkedPivotEntity) {
                    $linkedPivotEntity->destroy();
                }
            }
        }

        foreach ($entityOfEntitiesOrIdIds as $entityOrId) {
            if (!is_object($entityOrId)) {
                $entityOrId = $this->identityMap->remove(get_class($this->entity()), $entityOrId);
            }

            $this->dispatcher()->fire(new EntityDestroyed($entityOrId));
        }
    }

    /**
     * @param array $extractedData
     * @return array
     */
    public function beforePersist(array $extractedData)
    {
        return DataMapperPropertyTypeService::process($this->types(), $extractedData);
    }

    /**
     * @param array $gotData
     * @return array
     */
    public function afterGet(array $gotData)
    {
        return DataMapperPropertyTypeService::process($this->types(), $gotData);
    }

    private function processWithLinks($entityOrEntities)
    {
        foreach ($this->with() as $entityLinkedPropertyName) {
            foreach ($this->links() as $link) {
                if ($link->localEntityPropertyToSet == $entityLinkedPropertyName) {
                    switch (get_class($link)) {
                        case OneToOne::class:
                            $entityOrEntities = $this->processOneToOneLink($link, $entityOrEntities);
                            break;
                        case OneToMany::class:
                            $entityOrEntities = $this->processOneToManyLink($link, $entityOrEntities);
                            break;
                        case ManyToMany::class:
                            $entityOrEntities = $this->processManyToManyLink($link, $entityOrEntities);
                            break;
                    }
                }
            }
        }

        return $entityOrEntities;
    }

    public function processOneToOneLink(OneToOne $link, $entityOrEntities)
    {
        if (!is_array($entityOrEntities)) {
            $entities = [$entityOrEntities];
        } else {
            $entities = $entityOrEntities;
        }

        $class = $link->linkedEntityClass;

        /**
         * @var $class EntityInterface
         */
        $class = (new $class());

        $foreignDataMapper = $class->getOwningDataMapper();

        $localLinkValues = array_filter(
            RailmapHelpers::entityArrayColumn(
                $entities,
                'get' . ucwords($link->localEntityLinkProperty)
            )
        );

        if (empty($localLinkValues)) {
            return $entityOrEntities;
        }

        $linkedEntities = $foreignDataMapper->getWithQuery(
            function (Builder $query) use ($link, $entities, $foreignDataMapper, $localLinkValues) {
                return $query->whereIn(
                    $foreignDataMapper->table .
                    '.' .
                    $foreignDataMapper->mapFrom()[$link->foreignEntityLinkProperty],
                    $localLinkValues
                );
            }
        );

        if (empty($linkedEntities)) {
            return $entityOrEntities;
        }

        foreach ($entities as $entity) {
            foreach ($linkedEntities as $linkedEntity) {
                $extractedLinkedEntity = $linkedEntity->extract();

                if (!empty(
                    $extractedLinkedEntity[$foreignDataMapper->mapFrom()[$link->foreignEntityLinkProperty]]
                    ) &&
                    $extractedLinkedEntity[$foreignDataMapper->mapFrom()[$link->foreignEntityLinkProperty]] ==
                    call_user_func(
                        [$entity, 'get' . ucwords($link->localEntityLinkProperty)]
                    ) &&
                    !empty($linkedEntity)
                ) {
                    call_user_func(
                        [$entity, 'set' . ucwords($link->localEntityPropertyToSet)],
                        $linkedEntity
                    );
                }
            }
        }

        return $entityOrEntities;
    }

    public function processOneToManyLink(OneToMany $link, $entityOrEntities)
    {
        if (!is_array($entityOrEntities)) {
            $entities = [$entityOrEntities];
        } else {
            $entities = $entityOrEntities;
        }

        $class = $link->linkedEntityClass;

        /**
         * @var $class EntityInterface
         */
        $class = (new $class());

        $foreignDataMapper = $class->getOwningDataMapper();

        $linkedEntities = $foreignDataMapper->getWithQuery(
            function (Builder $query) use ($link, $entities, $foreignDataMapper) {
                if (is_callable($link->queryCustomizeCallback)) {
                    $query = call_user_func($link->queryCustomizeCallback, $query);
                }

                return $query->whereIn(
                    $foreignDataMapper->table .
                    '.' .
                    $foreignDataMapper->mapFrom()[$link->foreignEntityLinkProperty],
                    RailmapHelpers::entityArrayColumn(
                        $entities,
                        'get' . ucwords($link->localEntityLinkProperty)
                    )
                )->orderBy($link->sortByForeignColumn, $link->sortByForeignDirection);
            }
        );

        if (empty($linkedEntities)) {
            return $entityOrEntities;
        }

        foreach ($entities as $entity) {
            $entitiesToLink = [];

            foreach ($linkedEntities as $linkedEntity) {
                $extractedLinkedEntity = $linkedEntity->extract();

                if (!empty(
                    $extractedLinkedEntity[$foreignDataMapper->mapFrom()[$link->foreignEntityLinkProperty]]
                    ) &&
                    $extractedLinkedEntity[$foreignDataMapper->mapFrom()[$link->foreignEntityLinkProperty]] ==
                    call_user_func(
                        [$entity, 'get' . ucwords($link->localEntityLinkProperty)]
                    ) &&
                    !empty($linkedEntity)
                ) {
                    $entitiesToLink[] = $linkedEntity;
                }
            }

            call_user_func(
                [$entity, 'set' . ucwords($link->localEntityPropertyToSet)],
                $entitiesToLink
            );
        }

        return $entityOrEntities;
    }

    public function processManyToManyLink(ManyToMany $link, $entityOrEntities)
    {
        if (!is_array($entityOrEntities)) {
            $entities = [$entityOrEntities];
        } else {
            $entities = $entityOrEntities;
        }

        $foreignDataEntityClass = $link->linkedEntityClass;
        $linkDataEntityClass = $link->pivotLinkEntityClass;

        /**
         * @var $foreignDataEntityClass EntityInterface
         */
        $foreignDataEntityClass = (new $foreignDataEntityClass());

        /**
         * @var $linkDataEntityClass EntityInterface
         */
        $linkDataEntityClass = (new $linkDataEntityClass());

        $linkDataMapper = $linkDataEntityClass->getOwningDataMapper();
        $foreignDataMapper = $foreignDataEntityClass->getOwningDataMapper();

        // todo: extract to data mapper
        $linkEntities = $linkDataMapper->getWithQuery(
            function (Builder $query) use ($link, $linkDataMapper, $entityOrEntities) {
                if (is_callable($link->queryCustomizeCallback)) {
                    $query = call_user_func($link->queryCustomizeCallback, $query);
                }

                return $query->whereIn(
                    $linkDataMapper->table .
                    '.' .
                    $linkDataMapper->mapFrom()[$link->pivotLocalEntityLinkProperty],
                    RailmapHelpers::entityArrayColumn(
                        $entityOrEntities,
                        'get' . ucwords($link->localEntityLinkProperty)
                    )
                );
            }
        );

        if (empty($linkEntities)) {
            return [];
        }

        // todo: extract to data mapper
        $foreignEntities = $foreignDataMapper->getWithQuery(
            function (Builder $query) use (
                $link,
                $foreignDataMapper,
                $linkEntities
            ) {
                return $query->whereIn(
                    $foreignDataMapper->table .
                    '.' .
                    $foreignDataMapper->mapFrom()[$link->foreignEntityLinkProperty],
                    RailmapHelpers::entityArrayColumn(
                        $linkEntities,
                        'get' . ucwords($link->pivotForeignEntityLinkProperty)
                    )
                )->orderBy($link->sortByForeignColumn, $link->sortByForeignDirection);
            }
        );

        if (empty($foreignEntities)) {
            return [];
        }

        foreach ($entities as $entity) {
            $entitiesToLink = [];
            $linkEntitiesToSet = [];

            foreach ($foreignEntities as $foreignEntity) {
                foreach ($linkEntities as $linkEntity) {
                    $localEntityVal = call_user_func(
                        [$entity, 'get' . ucwords($link->localEntityLinkProperty)]
                    );

                    $linkLocalEntityVal = call_user_func(
                        [$linkEntity, 'get' . ucwords($link->pivotLocalEntityLinkProperty)]
                    );

                    $linkForeignEntityVal = call_user_func(
                        [$linkEntity, 'get' . ucwords($link->pivotForeignEntityLinkProperty)]
                    );

                    $foreignEntityVal = call_user_func(
                        [$foreignEntity, 'get' . ucwords($link->foreignEntityLinkProperty)]
                    );

                    if (!empty($localEntityVal) &&
                        !empty($linkLocalEntityVal) &&
                        !empty($linkForeignEntityVal) &&
                        !empty($foreignEntityVal) &&
                        $localEntityVal == $linkLocalEntityVal &&
                        $foreignEntityVal == $linkForeignEntityVal
                    ) {
                        $entitiesToLink[] = $foreignEntity;
                        $linkEntitiesToSet[] = $linkEntity;
                    }
                }
            }

            call_user_func(
                [$entity, 'set' . ucwords($link->localEntityPropertyToSet)],
                $entitiesToLink,
                $linkEntitiesToSet
            );
        }

        return $entityOrEntities;
    }

    public function generateQueryCacheKey($query)
    {
        return hash(
            'sha256',
            $query->toSql() . serialize($query->getBindings())
        );
    }

    public function flushCache()
    {
        $this->cacheRepository()->flush();
    }

    public function cacheRepository()
    {
        $tag = get_class($this);
        return $this->cacheRepository->tags([$tag]);
    }

    /**
     * @param Builder $query
     * @param callable $executeCallback
     * @return array|int|string|boolean
     */
    public function executeQueryOrGetCached(Builder $query, callable $executeCallback)
    {
        $cacheKey = $this->generateQueryCacheKey($query);

        if (!is_null($this->cacheTime) && $this->cacheRepository()->has($cacheKey)) {
            $rows = $this->cacheRepository()->get($cacheKey);
        } else {
            $rows = $executeCallback($query)->toArray();
            $this->cacheRepository()->put($cacheKey, $rows, $this->cacheTime);
        }

        return $rows;
    }
}