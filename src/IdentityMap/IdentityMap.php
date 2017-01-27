<?php

namespace Railroad\Railmap\IdentityMap;

use Railroad\Railmap\Entity\EntityInterface;
use Railroad\Railmap\Exceptions\EntityHasNoIdentifierException;

class IdentityMap
{
    /**
     * Objects are stored by: map[full/class/name][id] = $object;
     *
     * @var array
     */
    private $map = [];

    /**
     * @param $id
     * @param EntityInterface $entity
     * @return EntityInterface
     */
    public function getOrStore($id, EntityInterface $entity)
    {
        if (!empty($existingEntity = $this->get(get_class($entity), $id))) {
            return $existingEntity;
        }

        $this->store($entity, $id);

        return $entity;
    }

    /**
     * @param EntityInterface $entity
     * @param $id
     * @throws EntityHasNoIdentifierException
     */
    public function store(EntityInterface $entity, $id)
    {
        if (empty($id)) {
            throw new EntityHasNoIdentifierException();
        }

        $entityClass = get_class($entity);

        if (empty($this->get($entityClass, $id))) {
            $this->map[$entityClass][$id] = $entity;
        }
    }

    /**
     * @param $entityClass
     * @param $id
     * @return null|EntityInterface
     */
    public function get($entityClass, $id)
    {
        if (isset($this->map[$entityClass][$id])) {
            return $this->map[$entityClass][$id];
        }

        return null;
    }

    public function remove($entityClass, $id)
    {
        if (isset($this->map[$entityClass][$id])) {
            unset($this->map[$entityClass][$id]);
        }
    }
}