<?php

namespace Railroad\Railmap\Entity\Properties;

trait Versioned
{
    /**
     * @var int
     */
    protected $versionMasterId;

    /**
     * @var string
     */
    protected $versionSavedAt;

    /**
     * @return int
     */
    public function getVersionMasterId()
    {
        return $this->versionMasterId;
    }

    /**
     * @param int $versionMasterId
     */
    public function setVersionMasterId($versionMasterId)
    {
        $this->versionMasterId = $versionMasterId;
    }

    /**
     * @return string
     */
    public function getVersionSavedAt()
    {
        return $this->versionSavedAt;
    }

    /**
     * @param string $versionSavedAt
     */
    public function setVersionSavedAt($versionSavedAt)
    {
        $this->versionSavedAt = $versionSavedAt;
    }
}