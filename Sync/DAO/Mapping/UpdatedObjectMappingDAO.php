<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Sync\DAO\Mapping;


use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Order\ObjectChangeDAO;

class UpdatedObjectMappingDAO
{
    /**
     * @var ObjectChangeDAO
     */
    private $objectChangeDAO;

    /**
     * @var mixed
     */
    private $objectId;

    /**
     * @var string
     */
    private $objectName;

    /**
     * @var \DateTime
     */
    private $objectModifiedDate;

    /**
     * @param ObjectChangeDAO    $objectChangeDAO
     * @param mixed              $objectId
     * @param string             $objectName
     * @param \DateTimeInterface $objectModifiedDate
     */
    public function __construct(ObjectChangeDAO $objectChangeDAO, $objectId, $objectName, \DateTimeInterface $objectModifiedDate)
    {
        $this->objectChangeDAO    = $objectChangeDAO;
        $this->objectId           = $objectId;
        $this->objectName         = $objectName;
        $this->objectModifiedDate = ($objectModifiedDate instanceof \DateTimeImmutable) ? new \DateTime(
            $objectModifiedDate->format('Y-m-d H:i:s'),
            $objectModifiedDate->getTimezone()
        ) : $objectModifiedDate;
    }

    /**
     * @return ObjectChangeDAO
     */
    public function getObjectChangeDAO(): ObjectChangeDAO
    {
        return $this->objectChangeDAO;
    }

    /**
     * @return mixed
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    /**
     * @return string
     */
    public function getObjectName(): string
    {
        return $this->objectName;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getObjectModifiedDate(): \DateTimeInterface
    {
        return $this->objectModifiedDate;
    }
}