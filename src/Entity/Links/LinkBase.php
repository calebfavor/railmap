<?php

namespace Railroad\Railmap\Entity\Links;

abstract class LinkBase
{
    public $linkedEntityClass; // Ex. App\Entities\User\User
    public $localEntityPropertyToSet; // Ex. user
}