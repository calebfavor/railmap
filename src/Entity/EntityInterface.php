<?php

namespace Railroad\Railmap\Entity;

use Railroad\Railmap\DataMapper\DataMapperBase;

interface EntityInterface
{
    /**
     * @return int
     */
    public function getId();

    /**
     * @param int $id
     */
    public function setId($id);

    /**
     * @return DataMapperBase
     */
    public function getOwningDataMapper();

    /**
     * @param DataMapperBase $owningDataMapper
     */
    public function setOwningDataMapper(DataMapperBase $owningDataMapper);

    /**
     * @return array
     */
    public function getLoadedLinkedEntities();

    /**
     * @return array
     */
    public function getLoadedLinkedPivotEntities();

    /**
     * @param array $data
     * @param string $dataKeyPrefix
     */
    public function fill(array $data, $dataKeyPrefix = '');

    /**
     * @param string $dataKeyPrefix
     * @return []
     */
    public function extract($dataKeyPrefix = '');

    /**
     * @param string $dataKeyPrefix
     * @return []
     */
    public function flatten($dataKeyPrefix = '');

    /**
     * @param EntityInterface|EntityInterface[]|null $entityOrEntities
     */
    public function persist($entityOrEntities = null);

    /**
     * @param EntityInterface|EntityInterface[]|null $entityOrEntities
     */
    public function destroy($entityOrEntities = null);

    /**
     * Fills entity with random data.
     */
    public function randomize();
}