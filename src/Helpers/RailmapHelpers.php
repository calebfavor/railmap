<?php

namespace Railroad\Railmap\Helpers;

use Railroad\Railmap\Entity\EntityInterface;

class RailmapHelpers
{
    /**
     * @param EntityInterface[] $entities
     * @param $getMethod
     * @return array
     */
    public static function entityArrayColumn(array $entities, $getMethod)
    {
        $values = [];

        foreach ($entities as $entity) {
            if (method_exists($entity, $getMethod) || method_exists($entity, '__call')) {
                $values[] = $entity->$getMethod();
            }
        }

        return $values;
    }

    /**
     * @param array $objects
     * @param $property
     * @return array
     */
    public static function objectArrayColumn(array $objects, $property)
    {
        $values = [];

        foreach ($objects as $object) {
            if (property_exists($object, $property)) {
                $values[] = $object->$property;
            }
        }

        return $values;
    }

    /**
     * @param EntityInterface[] $entities
     * @param $attribute
     * @param $direction
     * @return array|EntityInterface[]
     */
    public static function sortEntitiesByIntAttribute(array $entities, $attribute, $direction)
    {
        usort(
            $entities,
            function ($entityA, $entityB) use ($attribute, $direction) {
                $getter = 'get' . ucwords($attribute);

                if ($direction == 'desc') {
                    return $entityB->$getter() - $entityA->$getter();
                } else {
                    return $entityA->$getter() - $entityB->$getter();
                }
            }
        );

        return $entities;
    }

    /**
     * @param EntityInterface[] $entities
     * @param $attribute
     * @param $direction
     * @return array|EntityInterface[]
     */
    public static function sortEntitiesByDateAttribute(array $entities, $attribute, $direction)
    {
        usort(
            $entities,
            function ($entityA, $entityB) use ($attribute, $direction) {
                $getter = 'get' . ucwords($attribute);

                if ($direction == 'desc') {
                    return strcasecmp($entityB->$getter(), $entityA->$getter());
                } else {
                    return strcasecmp($entityA->$getter(), $entityB->$getter());
                }
            }
        );

        return $entities;
    }

    /**
     * @param $string
     * @return string
     */
    public static function sanitizeForSlug($string)
    {
        return strtolower(
            preg_replace(
                '/(\-)+/',
                '-',
                str_replace(' ', '-', preg_replace('/[^ \w]+/', '', str_replace('&', 'and', trim($string))))
            )
        );
    }
}