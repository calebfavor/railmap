<?php

namespace Railroad\Railmap\Events;

use Railroad\Railmap\Entity\EntityInterface;

class EntityVersioned implements EntityEventInterface
{
    /**
     * @var EntityInterface
     */
    public $versionEntity;

    /**
     * @var EntityInterface
     */
    public $primaryEntity;

    /**
     * EntitySaved constructor.
     *
     * @param EntityInterface $versionEntity
     * @param EntityInterface $primaryEntity
     */
    public function __construct(EntityInterface $versionEntity, EntityInterface $primaryEntity)
    {
        $this->versionEntity = $versionEntity;
        $this->primaryEntity = $primaryEntity;
    }
}