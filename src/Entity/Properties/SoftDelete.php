<?php

namespace Railroad\Railmap\Entity\Properties;

trait SoftDelete
{
    protected $deletedAt;

    /**
     * @return string|null
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param string|null $deletedAt
     */
    public function setDeletedAt($deletedAt = null)
    {
        $this->deletedAt = $deletedAt;
    }
}