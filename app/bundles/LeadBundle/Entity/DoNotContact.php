<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace Mautic\LeadBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Lead;

/**
 * Class DoNotContact
 *
 * @package Mautic\EmailBundle\Entity
 */
class DoNotContact
{
    /**
     * Lead is currently subscribed
     */
    const SUBSCRIBED = 0;

    /**
     * Lead unsubscribed themselves.
     */
    const UNSUBSCRIBED = 1;

    /**
     * Lead was unsubscribed due to an unsuccessful send.
     */
    const BOUNCED = 2;

    /**
     * Lead was manually unsubscribed by user.
     */
    const MANUAL = 3;

    /**
     * @var int
     */
    private $id;

    /**
     * @var Lead
     */
    private $lead;

    /**
     * @var \DateTime
     */
    private $dateAdded;

    /**
     * @var int
     */
    private $reason = 0;

    /**
     * @var string
     */
    private $comments;

    /**
     * @var string
     */
    private $channel;

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata (ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('lead_donotcontact')
            ->setCustomRepositoryClass('Mautic\LeadBundle\Entity\DoNotContactRepository')
            ->addIndex(array('reason'), 'dnc_search');

        $builder->addId();

        $builder->addLead(true, 'CASCADE', false, 'doNotContact');

        $builder->addDateAdded();

        $builder->createField('reason', 'integer')
            ->build();

        $builder->createField('channel', 'string')
            ->build();

        $builder->createField('comments', 'text')
            ->nullable()
            ->build();
    }

    /**
     * @return mixed
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @param mixed $comments
     */
    public function setComments($comments)
    {
        $this->comments = $comments;
    }

    /**
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param string $channel
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return Lead
     */
    public function getLead()
    {
        return $this->lead;
    }

    /**
     * @param Lead $lead
     */
    public function setLead(Lead $lead)
    {
        $this->lead = $lead;
    }

    /**
     * @return int
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @param int $reason
     */
    public function setReason($reason)
    {
        $this->reason = $reason;
    }

    /**
     * @return \DateTime
     */
    public function getDateAdded()
    {
        return $this->dateAdded;
    }

    /**
     * @param mixed $dateAdded
     */
    public function setDateAdded($dateAdded)
    {
        $this->dateAdded = $dateAdded;
    }
}