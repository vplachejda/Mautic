<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Executioner\Logger;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Entity\LeadEventLogRepository;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\AbstractEventAccessor;
use Mautic\CampaignBundle\Helper\ChannelExtractor;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Tracker\ContactTracker;

class EventLogger
{
    /**
     * @var IpLookupHelper
     */
    private $ipLookupHelper;

    /**
     * @var ContactTracker
     */
    private $contactTracker;

    /**
     * @var LeadEventLogRepository
     */
    private $repo;

    /**
     * @var ArrayCollection
     */
    private $persistQueue;

    /**
     * @var ArrayCollection
     */
    private $logs;

    /**
     * LogHelper constructor.
     *
     * @param IpLookupHelper         $ipLookupHelper
     * @param ContactTracker         $contactTracker
     * @param LeadEventLogRepository $repo
     */
    public function __construct(IpLookupHelper $ipLookupHelper, ContactTracker $contactTracker, LeadEventLogRepository $repo)
    {
        $this->ipLookupHelper      = $ipLookupHelper;
        $this->contactTracker      = $contactTracker;
        $this->repo                = $repo;

        $this->persistQueue    = new ArrayCollection();
        $this->logs            = new ArrayCollection();
    }

    /**
     * @param LeadEventLog $log
     */
    public function queueToPersist(LeadEventLog $log)
    {
        $this->persistQueue->add($log);

        if ($this->persistQueue->count() >= 20) {
            $this->persistQueuedLogs();
        }
    }

    /**
     * @param LeadEventLog $log
     */
    public function persistLog(LeadEventLog $log)
    {
        $this->repo->saveEntity($log);
    }

    /**
     * @param Event     $event
     * @param Lead|null $lead
     * @param bool      $isInactiveEvent
     *
     * @return LeadEventLog
     */
    public function buildLogEntry(Event $event, Lead $lead = null, $isInactiveEvent = false)
    {
        $log = new LeadEventLog();

        $log->setIpAddress($this->ipLookupHelper->getIpAddress());

        $log->setEvent($event);
        $log->setCampaign($event->getCampaign());

        if (null === $lead) {
            $lead = $this->contactTracker->getContact();
        }
        $log->setLead($lead);

        if ($isInactiveEvent) {
            $log->setNonActionPathTaken(true);
        }

        $log->setDateTriggered(new \DateTime());
        $log->setSystemTriggered(defined('MAUTIC_CAMPAIGN_SYSTEM_TRIGGERED'));

        return $log;
    }

    /**
     * Persist the queue, clear the entities from memory, and reset the queue.
     */
    public function persistQueuedLogs()
    {
        if ($this->persistQueue->count()) {
            $this->repo->saveEntities($this->persistQueue->getValues());
        }

        // Push them into the logs ArrayCollection to be used later.
        /** @var LeadEventLog $log */
        foreach ($this->persistQueue as $log) {
            $this->logs->set($log->getId(), $log);
        }

        $this->persistQueue->clear();
    }

    /**
     * @return ArrayCollection
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * @param ArrayCollection $collection
     *
     * @return $this
     */
    public function persistCollection(ArrayCollection $collection)
    {
        if (!$collection->count()) {
            return $this;
        }

        $this->repo->saveEntities($collection->getValues());

        return $this;
    }

    /**
     * @param ArrayCollection $collection
     *
     * @return $this
     */
    public function clearCollection(ArrayCollection $collection)
    {
        $this->repo->detachEntities($collection->getValues());

        return $this;
    }

    /**
     * Persist logs entities after they've been updated.
     *
     * @return $this
     */
    public function persist()
    {
        if (!$this->logs->count()) {
            return $this;
        }

        $this->repo->saveEntities($this->logs->getValues());

        return $this;
    }

    /**
     * @return $this
     */
    public function clear()
    {
        $this->logs->clear();
        $this->repo->clear();

        return $this;
    }

    /**
     * @param ArrayCollection $logs
     *
     * @return ArrayCollection
     */
    public function extractContactsFromLogs(ArrayCollection $logs)
    {
        $contacts = new ArrayCollection();

        /** @var LeadEventLog $log */
        foreach ($logs as $log) {
            $contact = $log->getLead();
            $contacts->set($contact->getId(), $contact);
        }

        return $contacts;
    }

    /**
     * @param Event                 $event
     * @param AbstractEventAccessor $config
     * @param ArrayCollection       $contacts
     * @param bool                  $isInactiveEvent
     *
     * @return ArrayCollection
     */
    public function generateLogsFromContacts(Event $event, AbstractEventAccessor $config, ArrayCollection $contacts, $isInactiveEntry = false)
    {
        $isDecision = Event::TYPE_DECISION === $event->getEventType();

        // Ensure each contact has a log entry to prevent them from being picked up again prematurely
        foreach ($contacts as $contact) {
            $log = $this->buildLogEntry($event, $contact, $isInactiveEntry);
            $log->setIsScheduled(false);
            $log->setDateTriggered(new \DateTime());

            ChannelExtractor::setChannel($log, $event, $config);

            if ($isDecision) {
                // Do not pre-persist decision logs as they must be evaluated first
                $this->logs->add($log);
            } else {
                $this->queueToPersist($log);
            }
        }

        $this->persistQueuedLogs();

        return $this->logs;
    }
}
