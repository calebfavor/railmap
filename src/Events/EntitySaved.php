<?php

namespace Railroad\Railmap\Events;

use Railroad\Railmap\Entity\EntityInterface;

class EntitySaved implements EntityEventInterface
{
    /**
     * @var EntityInterface
     */
    public $newEntity;

    /**
     * @var EntityInterface|null
     */
    public $oldEntity;

    /**
     * EntitySaved constructor.
     *
     * @param EntityInterface $newEntity
     * @param null|EntityInterface $oldEntity
     */
    public function __construct(EntityInterface $newEntity, EntityInterface $oldEntity = null)
    {
        $this->newEntity = $newEntity;
        $this->oldEntity = $oldEntity;
    }
}