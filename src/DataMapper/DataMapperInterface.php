<?php

namespace Railroad\Railmap\DataMapper;

use Railroad\Railmap\Entity\EntityInterface;
use Railroad\Railmap\Entity\Links\LinkBase;
use Railroad\Railmap\Entity\Links\LinkInterface;

interface DataMapperInterface
{

    /**
     * @param int|int[] $idOrIds
     * @return null|EntityInterface|EntityInterface[]
     */
    public function get($idOrIds);

    /**
     * Callback will be called with argument set to return value of $this->gettingQuery()
     * Must return array (or arrayable) of rows or single row (key => value array)
     *
     * @param callable $queryCallback
     * @param bool $forceArrayReturn
     * @return EntityInterface|EntityInterface[]
     */
    public function getWithQuery(callable $queryCallback, $forceArrayReturn = false);

    /**
     * Callback will be called with argument set to return value of $this->gettingQuery()
     * $queryCallback MUST return a query object, NOT rows.
     *
     * @param callable|null $queryCallback
     * @return int
     */
    public function count(callable $queryCallback = null);

    /**
     * Callback will be called with argument set to return value of $this->gettingQuery()
     * $queryCallback MUST return a query object, NOT rows.
     *
     * @param callable|null $queryCallback
     * @return boolean
     */
    public function exists(callable $queryCallback = null);

    /**
     * Must return array with keys as the entity attribute names and values as the extracted/column names.
     * Use a pipe to denote a linked entity attribute.
     *
     * This is used when pulling entities from the data source.
     *
     * Ex. return ['firstName' => 'first_name', 'user' => 'user_id];
     *
     * @return []
     */
    public function mapFrom();

    /**
     * Must return array with keys as the entity attribute names and values as the extracted/column names.
     * Use a pipe to denote a linked entity attribute.
     *
     * This is used when sending entity data back to the source (extracting).
     *
     * Ex. return ['firstName' => 'first_name', 'user' => 'user_id];
     *
     * @return []
     */
    public function mapTo();

    /**
     * Should a new instance of this data mappers default entity instance.
     *
     * @return EntityInterface
     */
    public function entity();

    /**
     * This must return this data mappers query object, usually selecting the table.
     * Ex. \Illuminate\Database\Query\Builder
     *
     * @return mixed
     */
    public function baseQuery();

    /**
     * This must return this data mappers query object for pulling data, joins go here.
     * Ex. \Illuminate\Database\Query\Builder
     *
     * @return mixed
     */
    public function gettingQuery();

    /**
     * This must return this data mappers query object for saving data, usually same as base query.
     * Ex. \Illuminate\Database\Query\Builder
     *
     * @return mixed
     */
    public function settingQuery();

    /**
     * This must return the dispatched used to trigger events.
     * Ex. \Illuminate\Events\Dispatcher
     *
     * @return mixed
     */
    public function dispatcher();

    /**
     * Returns array declaring special storage types for attributes, such as json storage, date storage, etc.
     * You can have multiple types per attribute using a pipe.
     *
     * Ex. return ['column_name' => 'json', 'another_column' => 'date|json'];
     *
     * @return []
     */
    public function types();

    /**
     * Must return array of linked property names to always be linked/loaded when pulling this entity.
     * Property link must be defined in links() function.
     *
     * Ex. return ['user'];
     *
     * Means the link which fill the entity 'user' field will always be pulled.
     *
     * You can override during runtime by passing the link names in.
     *
     * Ex. $dataMapper->with('link')->get(1);
     *
     * @param array|null $withOverride
     * @return array|DatabaseDataMapperBase
     */
    public function with($withOverride = null);

    /**
     * Must return array of link classes.
     *
     * Ex. return [new OneToOne(...)];
     *
     * @return LinkBase[]
     */
    public function links();

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
    public function fill(EntityInterface $entity, array $data, $dataKeyPrefix = '');

    /**
     * This maps an entity back to an array of row/columns.
     * If set, will prepend $dataKeyPrefix to all keys.
     *
     * @param EntityInterface $entity
     * @param string $dataKeyPrefix
     * @return []
     */
    public function extract(EntityInterface $entity, $dataKeyPrefix = '');

    /**
     * @param EntityInterface|EntityInterface[] $entityOrEntities
     */
    public function persist($entityOrEntities);

    /**
     * @param EntityInterface|EntityInterface[]|integer|integer[]
     */
    public function destroy($entityOrEntitiesOrIdIds);

    /**
     * Decorator for changing data before it is sent to the persist function.
     *
     * @param array $extractedData
     * @return array
     */
    public function beforePersist(array $extractedData);

    /**
     * Decorator for changing data before it is sent to the entity fill function.
     *
     * @param array $gotData
     * @return array
     */
    public function afterGet(array $gotData);
}