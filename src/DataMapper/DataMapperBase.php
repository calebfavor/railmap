<?php

namespace Railroad\Railmap\DataMapper;

use Railroad\Railmap\IdentityMap\IdentityMap;

abstract class DataMapperBase implements DataMapperInterface
{
    /**
     * @var $identityMap IdentityMap
     */
    protected $identityMap;

    /**
     * @var string[]
     */
    public $with = [];

    /**
     * @var string
     */
    public $table = '';

    /**
     * @var int|null
     */
    protected $cacheTime;

    public function __construct()
    {
        $this->identityMap = app(IdentityMap::class);
    }

    public function mapFrom()
    {
        return $this->mapTo();
    }
}