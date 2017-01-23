<?php

namespace Railroad\Railmap\Entity\Links;

class ManyToMany extends LinkBase
{
    public $pivotLinkEntityClass; // Ex. ContentAccessLevel::class
    public $pivotLocalEntityLinkProperty; // Ex. contentId
    public $pivotForeignEntityLinkProperty; // Ex. levelId

    public $localEntityLinkProperty; // Ex. id
    public $foreignEntityLinkProperty; // Ex. id

    public $localEntityPropertyToSet; // Ex. accessLevels

    public $sortByForeignColumn; // Ex. created_at
    public $sortByForeignDirection; // Ex. desc

    /**
     * ManyToMany constructor.
     *
     * @param $linkedEntityClass
     * @param $pivotLinkEntityClass
     * @param $pivotLocalEntityLinkProperty
     * @param $pivotForeignEntityLinkProperty
     * @param $localEntityLinkProperty
     * @param $foreignEntityLinkProperty
     * @param $localEntityPropertyToSet
     * @param string $sortByForeignColumn
     * @param string $sortByForeignDirection
     */
    public function __construct(
        $linkedEntityClass,
        $pivotLinkEntityClass,
        $pivotLocalEntityLinkProperty,
        $pivotForeignEntityLinkProperty,
        $localEntityLinkProperty,
        $foreignEntityLinkProperty,
        $localEntityPropertyToSet,
        $sortByForeignColumn = 'id',
        $sortByForeignDirection = 'asc'
    ) {
        $this->linkedEntityClass = $linkedEntityClass;
        $this->pivotLinkEntityClass = $pivotLinkEntityClass;
        $this->pivotLocalEntityLinkProperty = $pivotLocalEntityLinkProperty;
        $this->pivotForeignEntityLinkProperty = $pivotForeignEntityLinkProperty;
        $this->localEntityLinkProperty = $localEntityLinkProperty;
        $this->foreignEntityLinkProperty = $foreignEntityLinkProperty;
        $this->localEntityPropertyToSet = $localEntityPropertyToSet;
        $this->sortByForeignColumn = $sortByForeignColumn;
        $this->sortByForeignDirection = $sortByForeignDirection;
    }
}