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
     * @var bool
     */
    private $enabled = true;

    /**
     * @param $id
     * @param EntityInterface $entity
     * @return EntityInterface
     */
    public function getOrStore($id, EntityInterface $entity)
    {
        if (!$this->enabled) {
            return $entity;
        }

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

        if (!$this->enabled) {
            return;
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
        if (!$this->enabled) {
            return null;
        }

        if (isset($this->map[$entityClass][$id])) {
            return $this->map[$entityClass][$id];
        }

        return null;
    }

    /**
     * @param $entityClass
     * @param array $ids
     * @return EntityInterface[]
     */
    public function getMany($entityClass, array $ids)
    {
        if (!$this->enabled) {
            return [];
        }

        $entities = [];

        foreach ($ids as $id) {
            if (isset($this->map[$entityClass][$id])) {
                $entities[] = $this->map[$entityClass][$id];
            }
        }

        return $entities;
    }

    public function remove($entityClass, $id = null)
    {
        if (isset($this->map[$entityClass][$id])) {
            unset($this->map[$entityClass][$id]);
        }

        if (empty($id)) {
            unset($this->map[$entityClass]);
        }
    }

    public static function disable()
    {
        app(self::class)->enabled = false;
    }

    public static function empty()
    {
        app(self::class)->map = [];
    }

    public static function enable()
    {
        app(self::class)->enabled = true;
    }
}