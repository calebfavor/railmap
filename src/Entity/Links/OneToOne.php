<?php

namespace Railroad\Railmap\Entity\Links;

class OneToOne extends LinkBase
{
    public $localEntityLinkProperty; // Ex. userId
    public $foreignEntityLinkProperty; // Ex. id
    public $localEntityPropertyToSet; // Ex. user

    /**
     * OneToOne constructor.
     *
     * @param $linkedEntityClass
     * @param $localEntityLinkProperty
     * @param $foreignEntityLinkProperty
     * @param $localEntityPropertyToSet
     */
    public function __construct(
        $linkedEntityClass,
        $localEntityLinkProperty,
        $foreignEntityLinkProperty,
        $localEntityPropertyToSet
    ) {

        $this->linkedEntityClass = $linkedEntityClass;
        $this->localEntityLinkProperty = $localEntityLinkProperty;
        $this->foreignEntityLinkProperty = $foreignEntityLinkProperty;
        $this->localEntityPropertyToSet = $localEntityPropertyToSet;
    }
}