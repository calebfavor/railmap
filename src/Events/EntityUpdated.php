<?php

namespace Railroad\Railmap\Events;

use Railroad\Railmap\Entity\EntityInterface;

class EntityUpdated
{
    /**
     * @var EntityInterface
     */
    public $newEntity;

    /**
     * @var EntityInterface
     */
    public $oldEntity;

    /**
     * EntitySaved constructor.
     *
     * @param EntityInterface $newEntity
     * @param EntityInterface $oldEntity
     */
    public function __construct(EntityInterface $newEntity, EntityInterface $oldEntity)
    {
        $this->newEntity = $newEntity;
        $this->oldEntity = $oldEntity;
    }
}