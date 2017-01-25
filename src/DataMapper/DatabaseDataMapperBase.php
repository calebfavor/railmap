<?php

namespace Railroad\Railmap\DataMapper;

use ArrayAccess;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Events\Dispatcher;
use Railroad\Railmap\Entity\EntityInterface;
use Railroad\Railmap\Entity\Links\LinkBase;
use Railroad\Railmap\Entity\Links\LinkFactory;
use Railroad\Railmap\Entity\Links\ManyToMany;
use Railroad\Railmap\Entity\Links\OneToMany;
use Railroad\Railmap\Entity\Links\OneToOne;
use Railroad\Railmap\Helpers\RailmapHelpers;

abstract class DatabaseDataMapperBase implements DataMapperInterface
{
    /**
     * @var $databaseManager DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var string[]
     */
    protected $with = [];

    /**
     * @var string
     */
    protected $table = '';

    public function __construct()
    {
        $this->databaseManager = app(DatabaseManager::class);
    }

    public function mapFrom()
    {
        return $this->mapTo();
    }

    /**
     * @param int|int[] $idOrIds
     * @return null|EntityInterface|EntityInterface[]
     */
    public function get($idOrIds)
    {
        if (is_array($idOrIds)) {
            $entities = [];

            $rows = $this->query()->whereIn('id', $idOrIds)->get()->toArray();

            foreach ($rows as $row) {
                $entity = $this->entity();
                $entity->fill($this->afterGet((array)$row));

                $entities[$entity->getId()] = $entity;
            }

            $orderedEntities = [];

            foreach ($idOrIds as $id) {
                if (!empty($entities[$id])) {
                    $orderedEntities[] = $entities[$id];
                }
            }

            return $this->processWithLinks($orderedEntities);
        }

        $row = $this->query()->where('id', '=', $idOrIds)->first();

        if (!empty($row)) {
            $entity = $this->entity();
            $entity->fill($this->afterGet((array)$row));

            return $this->processWithLinks($entity);
        }

        return null;
    }

    /**
     * @param callable $queryCallback
     * @param bool $forceArrayReturn
     * @return EntityInterface|EntityInterface[]
     */
    public function getWithQuery(callable $queryCallback, $forceArrayReturn = false)
    {
        $rows = $queryCallback($this->query());

        if (is_array($rows) || $rows instanceof ArrayAccess) {
            $entities = [];

            foreach ($rows as $row) {
                $entity = $this->entity();
                $entity->fill($this->afterGet((array)$row));

                $entities[] = $entity;
            }

            return $this->processWithLinks($entities);
        } else {
            if (!empty($rows)) {
                $entity = $this->entity();
                $entity->fill($this->afterGet((array)$rows));

                $entity = $this->processWithLinks($entity);

                return $forceArrayReturn ? [$entity] : $entity;
            }
        }

        return null;
    }

    /**
     * @return Builder
     */
    public function query()
    {
        return $this->databaseManager->connection()->query()->from($this->table);
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
     * @return []
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

                $this->query()->insert($this->beforePersist($this->extract($entity)));
            } else {
                $oldEntity = $this->get($entity->getId());

                $this->query()->where(['id' => $entity->getId()])->take(1)->update(
                    $this->beforePersist($this->extract($entity))
                );
            }

            if (empty($entity->getId())) {
                $entity->setId($this->query()->getConnection()->getPdo()->lastInsertId('id'));
            }

            // todo: event
//            $this->dispatcher()->fire(new EntitySaved($entity, $oldEntity));
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

        $idsToDelete = [];

        foreach ($entityOfEntitiesOrIdIds as $entityOrId) {
            if (is_object($entityOrId)) {
                $idsToDelete[] = $entityOrId->getId();
            } else {
                $idsToDelete[] = $entityOrId;
            }
        }

        $this->query()->whereIn('id', $idsToDelete)->delete();

        // Delete all pivot table entries
        foreach ($entityOfEntitiesOrIdIds as $entity) {
            foreach (LinkFactory::getManyToManyLinks($this->links()) as $link) {
                call_user_func([$entity, 'get' . ucwords($link->localEntityPropertyToSet)]);
            }

            foreach ($entity->getLoadedLinkedPivotEntities() as $linkPropertyName => $linkedPivotEntities) {
                foreach ($linkedPivotEntities as $linkedPivotEntity) {
                    $linkedPivotEntity->destroy();
                }
            }
        }

        foreach ($entityOfEntitiesOrIdIds as $entity) {
            // todo: event
//            $this->dispatcher()->fire(new EntityDestroyed($entity));
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

        $linkedEntities = $foreignDataMapper->getWithQuery(
            function (Builder $query) use ($link, $entities, $foreignDataMapper) {
                return $query->whereIn(
                    $foreignDataMapper->map()[$link->foreignEntityLinkProperty],
                    RailmapHelpers::entityArrayColumn(
                        $entities,
                        'get' . ucwords($link->localEntityLinkProperty)
                    )
                )->get();
            },
            true
        );

        if (empty($linkedEntities)) {
            return $entityOrEntities;
        }

        foreach ($entities as $entity) {
            foreach ($linkedEntities as $linkedEntity) {
                $extractedLinkedEntity = $linkedEntity->extract();

                if (!empty(
                    $extractedLinkedEntity[$foreignDataMapper->map()[$link->foreignEntityLinkProperty]]
                    ) &&
                    $extractedLinkedEntity[$foreignDataMapper->map()[$link->foreignEntityLinkProperty]] ==
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
                return $query->whereIn(
                    $foreignDataMapper->map()[$link->foreignEntityLinkProperty],
                    RailmapHelpers::entityArrayColumn(
                        $entities,
                        'get' . ucwords($link->localEntityLinkProperty)
                    )
                )->orderBy($link->sortByForeignColumn, $link->sortByForeignDirection)->get();
            },
            true
        );

        if (empty($linkedEntities)) {
            return $entityOrEntities;
        }

        foreach ($entities as $entity) {
            $entitiesToLink = [];

            foreach ($linkedEntities as $linkedEntity) {
                $extractedLinkedEntity = $linkedEntity->extract();

                if (!empty(
                    $extractedLinkedEntity[$foreignDataMapper->map()[$link->foreignEntityLinkProperty]]
                    ) &&
                    $extractedLinkedEntity[$foreignDataMapper->map()[$link->foreignEntityLinkProperty]] ==
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
                return $query->whereIn(
                    $linkDataMapper->map()[$link->pivotLocalEntityLinkProperty],
                    RailmapHelpers::entityArrayColumn(
                        $entityOrEntities,
                        'get' . ucwords($link->localEntityLinkProperty)
                    )
                )->get();
            },
            true
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
                    $foreignDataMapper->map()[$link->foreignEntityLinkProperty],
                    RailmapHelpers::entityArrayColumn(
                        $linkEntities,
                        'get' . ucwords($link->pivotForeignEntityLinkProperty)
                    )
                )->orderBy($link->sortByForeignColumn, $link->sortByForeignDirection)->get();
            },
            true
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
}