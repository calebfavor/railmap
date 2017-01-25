<?php

namespace Railroad\Railmap\Entity\Properties;

trait Versioned
{
    /**
     * @var int|null
     */
    protected $versionMasterId;

    /**
     * @var string|null
     */
    protected $versionSavedAt;

    /**
     * @return int|null
     */
    public function getVersionMasterId()
    {
        return $this->versionMasterId;
    }

    /**
     * @param int|null $versionMasterId
     */
    public function setVersionMasterId($versionMasterId)
    {
        $this->versionMasterId = $versionMasterId;
    }

    /**
     * @return string|null
     */
    public function getVersionSavedAt()
    {
        return $this->versionSavedAt;
    }

    /**
     * @param string|null $versionSavedAt
     */
    public function setVersionSavedAt($versionSavedAt)
    {
        $this->versionSavedAt = $versionSavedAt;
    }
}