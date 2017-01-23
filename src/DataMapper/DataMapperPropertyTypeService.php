<?php

namespace Railroad\Railmap\DataMapper;

class DataMapperPropertyTypeService
{
    /**
     * @param $columnTypes
     * @param $columnData
     * @return mixed
     */
    public static function process($columnTypes, $columnData)
    {
        foreach ($columnData as $columnName => $columnValue) {
            if (isset($columnTypes[$columnName])) {
                $actions = explode('|', $columnTypes[$columnName]);

                foreach ($actions as $action) {
                    $actionParameters = explode(':', $action);
                    $action = $actionParameters[0];

                    if (isset($actionParameters[1])) {
                        $actionParameters = explode(',', $actionParameters[1]);
                    } else {
                        $actionParameters = [];
                    }

                    if (method_exists(self::class, $action)) {
                        $columnData[$columnName] = call_user_func(
                            [self::class, $action],
                            $columnValue,
                            $actionParameters
                        );
                    }
                }
            }
        }

        return $columnData;
    }

    /**
     * @param $stringOrArray
     * @param $actionParameters
     * @return array|string
     */
    public static function json($stringOrArray, $actionParameters)
    {
        if (is_array($stringOrArray)) {
            return json_encode($stringOrArray);
        }

        return json_decode($stringOrArray);
    }

    /**
     * @param $entityOrColumn
     * @param $actionParameters
     * @return array|string
     */
    public static function linked_entity_property($entityOrColumn, $actionParameters)
    {
        if (is_object($entityOrColumn)) {
            return call_user_func([$entityOrColumn, 'get' . ucwords($actionParameters[0])]);
        }

        return null;
    }

    // todo: add all other types
}