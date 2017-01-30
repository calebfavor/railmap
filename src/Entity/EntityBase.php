<?php

namespace Railroad\Railmap\Entity;

use Illuminate\Database\Query\Builder;
use Railroad\Railmap\DataMapper\DataMapperInterface;
use Railroad\Railmap\Entity\Links\LinkFactory;
use Railroad\Railmap\Helpers\RailmapHelpers;

abstract class EntityBase implements EntityInterface
{
    /**
     * @var $_loadedLinkedEntities EntityInterface|EntityInterface[]
     */
    private $_loadedLinkedEntities = [];

    /**
     * @var $_loadedLinkedPivotEntities EntityInterface|EntityInterface[]
     */
    private $_loadedLinkedPivotEntities = [];

    /**
     * @var $id int
     */
    protected $id;

    /**
     * @var $owningDataMapper DataMapperInterface
     */
    protected $owningDataMapper;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return DataMapperInterface
     */
    public function getOwningDataMapper()
    {
        return $this->owningDataMapper;
    }

    /**
     * @param DataMapperInterface $owningDataMapper
     */
    public function setOwningDataMapper(DataMapperInterface $owningDataMapper)
    {
        $this->owningDataMapper = $owningDataMapper;
    }

    /**
     * @return array
     */
    public function getLoadedLinkedEntities()
    {
        return $this->_loadedLinkedEntities;
    }

    /**
     * @return array
     */
    public function getLoadedLinkedPivotEntities()
    {
        return $this->_loadedLinkedPivotEntities;
    }

    /**
     * @param array $data
     * @param string $dataKeyPrefix
     */
    public function fill(array $data, $dataKeyPrefix = '')
    {
        $this->owningDataMapper->fill($this, $data, $dataKeyPrefix);
    }

    /**
     * @param string $dataKeyPrefix
     * @return []
     */
    public function extract($dataKeyPrefix = '')
    {
        return $this->owningDataMapper->extract($this, $dataKeyPrefix);
    }

    /**
     * @param string $dataKeyPrefix
     * @return array
     */
    public function flatten($dataKeyPrefix = '')
    {
        $properties = get_object_vars($this);

        foreach ($properties as $propertyName => $propertyValue) {
            $getMethodName = 'get' . ucwords($propertyName);

            if (method_exists($this, $getMethodName)) {
                $properties[$dataKeyPrefix . $propertyName] = $this->$getMethodName();
            }
        }

        return $properties;
    }

    /**
     * @param EntityInterface|EntityInterface[]|null $entityOrEntities
     */
    public function persist($entityOrEntities = null)
    {
        if (is_null($entityOrEntities)) {
            // If nothing is passed in we will save this entity

            $this->getOwningDataMapper()->persist($this);
        } else {
            // Otherwise save the passed in entities if they are linked

            if (!is_array($entityOrEntities)) {
                $entityOrEntities = [$entityOrEntities];
            }

            // todo: check if they belong to this entity

            // Eventually could be bulk inserted/updated
            foreach ($entityOrEntities as $entity) {
                $entity->persist();
            }
        }
    }

    /**
     * @param EntityInterface|EntityInterface[]|null $entityOrEntities
     */
    public function destroy($entityOrEntities = null)
    {
        if (is_null($entityOrEntities)) {
            // If nothing is passed in we will save this entity

            $this->getOwningDataMapper()->destroy($this);
        } else {
            // Otherwise save the passed in entities if they are linked

            if (!is_array($entityOrEntities)) {
                $entityOrEntities = [$entityOrEntities];
            }

            // todo: check if they belong to this entity

            // Eventually could be bulk inserted/updated
            foreach ($entityOrEntities as $entity) {
                $entity->destroy();
            }
        }
    }

    public function __call($name, $arguments)
    {
        $accessType = lcfirst(substr($name, 0, 3));
        $propertyName = lcfirst(substr($name, 3));

        // todo: refactor one to one links processing
        foreach (LinkFactory::getOneToOneLinks($this->getOwningDataMapper()->links()) as $link) {
            if ($link->localEntityPropertyToSet != $propertyName) {
                continue;
            }

            if ($accessType == 'get') {
                if (isset($this->_loadedLinkedEntities[$link->localEntityPropertyToSet])) {
                    return $this->_loadedLinkedEntities[$link->localEntityPropertyToSet];
                }

                $class = $link->linkedEntityClass;

                /**
                 * @var $class EntityInterface
                 */
                $class = (new $class());

                $foreignDataMapper = $class->getOwningDataMapper();

                $localEntityLinkValue = call_user_func(
                    [$this, 'get' . ucwords($link->localEntityLinkProperty)]
                );

                if (!empty($localEntityLinkValue)) {
                    $foreignEntity = $foreignDataMapper->get($localEntityLinkValue);

                    if (empty($foreignEntity) || get_class($foreignEntity) != $link->linkedEntityClass) {
                        return null;
                    }

                    call_user_func(
                        [$this, 'set' . ucwords($link->localEntityLinkProperty)],
                        $foreignEntity->getId()
                    );

                    $this->_loadedLinkedEntities[$link->localEntityPropertyToSet] = $foreignEntity;

                    return $foreignEntity;
                }
            } elseif ($accessType == 'set') {
                if (!empty($arguments[0]) && get_class($arguments[0]) == $link->linkedEntityClass) {

                    call_user_func(
                        [$this, 'set' . ucwords($link->localEntityLinkProperty)],
                        $arguments[0]->getId()
                    );

                    $this->_loadedLinkedEntities[$link->localEntityPropertyToSet] = $arguments[0];
                }

                if (is_null($arguments[0])) {
                    call_user_func(
                        [$this, 'set' . ucwords($link->localEntityLinkProperty)],
                        null
                    );

                    unset($this->_loadedLinkedEntities[$link->localEntityPropertyToSet]);
                }
            }

        }

        // todo: refactor one to many links processing
        foreach (LinkFactory::getOneToManyLinks($this->getOwningDataMapper()->links()) as $link) {
            if ($link->localEntityPropertyToSet != $propertyName) {
                continue;
            }

            if ($accessType == 'get') {
                if (isset($this->_loadedLinkedEntities[$link->localEntityPropertyToSet])) {
                    return $this->_loadedLinkedEntities[$link->localEntityPropertyToSet];
                }

                $class = $link->linkedEntityClass;

                /**
                 * @var $class EntityInterface
                 */
                $class = (new $class());

                $foreignDataMapper = $class->getOwningDataMapper();

                $localEntityLinkValue = call_user_func(
                    [$this, 'get' . ucwords($link->localEntityLinkProperty)]
                );

                if (!empty($localEntityLinkValue)) {
                    // todo: extract to data mapper
                    $foreignEntities = $foreignDataMapper->getWithQuery(
                        function (Builder $query) use ($link, $localEntityLinkValue, $foreignDataMapper) {
                            return $query->where(
                                $foreignDataMapper->map()[$link->foreignEntityLinkProperty],
                                $localEntityLinkValue
                            )->orderBy($link->sortByForeignColumn, $link->sortByForeignDirection)->get();
                        },
                        true
                    );

                    if (empty($foreignEntities)) {
                        return [];
                    }

                    $this->_loadedLinkedEntities[$link->localEntityPropertyToSet] = $foreignEntities;

                    return $foreignEntities;
                }
            } elseif ($accessType == 'set') {

                // We dont want an infinite setting loop
                if ((isset($this->_loadedLinkedEntities[$link->localEntityPropertyToSet]) &&
                        $this->_loadedLinkedEntities[$link->localEntityPropertyToSet] !== $arguments[0]) ||
                    !isset($this->_loadedLinkedEntities[$link->localEntityPropertyToSet])
                ) {
                    if (!empty($arguments[0]) && is_array($arguments[0])) {
                        $this->_loadedLinkedEntities[$link->localEntityPropertyToSet] = $arguments[0];
                    }

                    if (empty($arguments[0])) {
                        $this->_loadedLinkedEntities[$link->localEntityPropertyToSet] = [];
                    }
                }
            }
        }

        // todo: refactor many to many links processing
        foreach (LinkFactory::getManyToManyLinks($this->getOwningDataMapper()->links()) as $link) {
            if ($link->localEntityPropertyToSet != $propertyName) {
                continue;
            }

            if ($accessType == 'get') {
                if (isset($this->_loadedLinkedEntities[$link->localEntityPropertyToSet])) {
                    return $this->_loadedLinkedEntities[$link->localEntityPropertyToSet];
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

                $localEntityLinkValue = call_user_func(
                    [$this, 'get' . ucwords($link->localEntityLinkProperty)]
                );

                if (!empty($localEntityLinkValue)) {

                    // todo: extract to data mapper
                    $linkEntities = $linkDataMapper->getWithQuery(
                        function (Builder $query) use ($link, $localEntityLinkValue, $linkDataMapper) {
                            return $query->where(
                                $linkDataMapper->map()[$link->pivotLocalEntityLinkProperty],
                                $localEntityLinkValue
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

                    $this->_loadedLinkedPivotEntities[$link->localEntityPropertyToSet] = $linkEntities;
                    $this->_loadedLinkedEntities[$link->localEntityPropertyToSet] = $foreignEntities;

                    return $foreignEntities;
                }
            } elseif ($accessType == 'set') {
                if (empty($arguments[1])) {
                    // first we need to make sure we are synced with the database
                    unset($this->_loadedLinkedEntities[$link->localEntityPropertyToSet]);
                    unset($this->_loadedLinkedPivotEntities[$link->localEntityPropertyToSet]);

                    call_user_func(
                        [$this, 'get' . ucwords($link->localEntityPropertyToSet)]
                    );

                    // then, delete all existing link table entities that are no in the set
                    if (!empty($this->_loadedLinkedPivotEntities[$link->localEntityPropertyToSet])) {
                        foreach ($this->_loadedLinkedPivotEntities[$link->localEntityPropertyToSet] as $index => $existingLinkEntity) {
                            $isBeingSetAlready = false;

                            foreach ($arguments[0] ?? [] as $newEntity) {
                                $newEntityPivotValue = call_user_func(
                                    [$newEntity, 'get' . ucwords($link->localEntityLinkProperty)]
                                );

                                if (call_user_func(
                                        [
                                            $existingLinkEntity,
                                            'get' . ucwords($link->pivotForeignEntityLinkProperty)
                                        ]
                                    ) == $newEntityPivotValue
                                ) {
                                    $isBeingSetAlready = true;
                                }
                            }

                            if (!$isBeingSetAlready) {
                                $existingLinkEntity->destroy();
                                unset($this->_loadedLinkedPivotEntities[$link->localEntityPropertyToSet][$index]);
                            }
                        }
                    }

                    $linkDataEntityClass = $link->pivotLinkEntityClass;

                    /**
                     * @var $linkDataEntityClass EntityInterface
                     */
                    $linkDataEntityClass = (new $linkDataEntityClass());

                    $entityLinks = [];

                    foreach ($arguments[0] ?? [] as $newEntity) {
                        $linkEntity = new $linkDataEntityClass();

                        call_user_func(
                            [$linkEntity, 'set' . ucwords($link->pivotLocalEntityLinkProperty)],
                            call_user_func([$this, 'get' . ucwords($link->localEntityLinkProperty)])
                        );

                        call_user_func(
                            [$linkEntity, 'set' . ucwords($link->pivotForeignEntityLinkProperty)],
                            call_user_func([$newEntity, 'get' . ucwords($link->foreignEntityLinkProperty)])
                        );

                        // todo: use mass update instead
                        $linkEntity->persist();
                    }

                    $this->_loadedLinkedEntities[$link->localEntityPropertyToSet] = $arguments[0];
                    $this->_loadedLinkedPivotEntities[$link->localEntityPropertyToSet] = $entityLinks;

                } else {
                    // this is only called from the data mapper
                    // this totally breaks link consistency, use carefully
                    $this->_loadedLinkedEntities[$link->localEntityPropertyToSet] = $arguments[0];
                    $this->_loadedLinkedPivotEntities[$link->localEntityPropertyToSet] = $arguments[1];
                }
            }
        }

        return null;
    }
}