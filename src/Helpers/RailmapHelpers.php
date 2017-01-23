<?php

namespace Railroad\Railmap\Helpers;

class RailmapHelpers
{
    /**
     * @param array $entities
     * @param $getMethod
     * @return array
     */
    public static function entityArrayColumn(array $entities, $getMethod)
    {
        $values = [];

        foreach ($entities as $entity) {
            if (method_exists($entity, $getMethod)) {
                $values[] = $entity->$getMethod();
            }
        }

        return $values;
    }
}