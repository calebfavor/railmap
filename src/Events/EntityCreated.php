<?php

namespace Railroad\Railmap\Events;

use Railroad\Railmap\Entity\EntityInterface;

class EntityCreated
{
    /**
     * @var EntityInterface
     */
    public $entity;

    /**
     * EntityCreated constructor.
     *
     * @param EntityInterface $entity
     */
    public function __construct(EntityInterface $entity)
    {
        $this->entity = $entity;
    }
}