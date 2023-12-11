<?php

namespace Mautic\NotificationBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\NotificationBundle\Entity\Notification;

class SendingNotificationEvent extends CommonEvent
{
    /**
     * @var Notification
     */
    protected $entity;

    public function __construct(
        Notification $notification,
        protected Lead $lead
    ) {
        $this->entity = $notification;
    }

    /**
     * @return Notification
     */
    public function getNotification()
    {
        return $this->entity;
    }

    /**
     * @return $this
     */
    public function setNotifiction(Notification $notification)
    {
        $this->entity = $notification;

        return $this;
    }

    /**
     * @return Lead
     */
    public function getLead()
    {
        return $this->lead;
    }

    /**
     * @return $this
     */
    public function setLead(Lead $lead)
    {
        $this->lead = $lead;

        return $this;
    }
}
