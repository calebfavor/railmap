<?php

namespace Railroad\Railmap\Entity\Links;

class OneToMany extends LinkBase
{
    public $localEntityLinkProperty; // Ex. id
    public $foreignEntityLinkProperty; // Ex. contentId

    public $localEntityPropertyToSet; // Ex. contentFields

    public $sortByForeignColumn; // Ex. created_at
    public $sortByForeignDirection; // Ex. desc

    /**
     * OneToMany constructor.
     *
     * @param $linkedEntityClass
     * @param $localEntityLinkProperty
     * @param $foreignEntityLinkProperty
     * @param $localEntityPropertyToSet
     * @param string $sortByForeignColumn
     * @param string $sortByForeignDirection
     * @param callable|null $queryCustomizeCallback
     */
    public function __construct(
        $linkedEntityClass,
        $localEntityLinkProperty,
        $foreignEntityLinkProperty,
        $localEntityPropertyToSet,
        $sortByForeignColumn = 'id',
        $sortByForeignDirection = 'asc',
        $queryCustomizeCallback = null
    ) {
        $this->linkedEntityClass = $linkedEntityClass;
        $this->localEntityLinkProperty = $localEntityLinkProperty;
        $this->foreignEntityLinkProperty = $foreignEntityLinkProperty;
        $this->localEntityPropertyToSet = $localEntityPropertyToSet;
        $this->sortByForeignColumn = $sortByForeignColumn;
        $this->sortByForeignDirection = $sortByForeignDirection;
        $this->queryCustomizeCallback = $queryCustomizeCallback;
    }
}