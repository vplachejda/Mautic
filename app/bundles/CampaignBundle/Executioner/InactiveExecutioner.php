<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Executioner;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Executioner\ContactFinder\InactiveContactFinder;
use Mautic\CampaignBundle\Executioner\ContactFinder\Limiter\ContactLimiter;
use Mautic\CampaignBundle\Executioner\Exception\NoContactsFoundException;
use Mautic\CampaignBundle\Executioner\Exception\NoEventsFoundException;
use Mautic\CampaignBundle\Executioner\Helper\InactiveHelper;
use Mautic\CampaignBundle\Executioner\Result\Counter;
use Mautic\CampaignBundle\Executioner\Scheduler\EventScheduler;
use Mautic\CoreBundle\Helper\ProgressBarHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\TranslatorInterface;

class InactiveExecutioner implements ExecutionerInterface
{
    /**
     * @var Campaign
     */
    private $campaign;

    /**
     * @var ContactLimiter
     */
    private $limiter;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ProgressBar
     */
    private $progressBar;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var EventScheduler
     */
    private $scheduler;

    /**
     * @var EventExecutioner
     */
    private $executioner;

    /**
     * @var Counter
     */
    private $counter;

    /**
     * @var InactiveContactFinder
     */
    private $inactiveContactFinder;

    /**
     * @var ArrayCollection
     */
    private $decisions;

    /**
     * @var InactiveHelper
     */
    private $helper;

    /**
     * @var int
     */
    private $startAtContactId = 0;

    /**
     * InactiveExecutioner constructor.
     *
     * @param InactiveContactFinder $inactiveContactFinder
     * @param LoggerInterface       $logger
     * @param TranslatorInterface   $translator
     * @param EventScheduler        $scheduler
     * @param InactiveHelper        $helper
     * @param EventExecutioner      $executioner
     */
    public function __construct(
        InactiveContactFinder $inactiveContactFinder,
        LoggerInterface $logger,
        TranslatorInterface $translator,
        EventScheduler $scheduler,
        InactiveHelper $helper,
        EventExecutioner $executioner
    ) {
        $this->inactiveContactFinder = $inactiveContactFinder;
        $this->logger                = $logger;
        $this->translator            = $translator;
        $this->scheduler             = $scheduler;
        $this->helper                = $helper;
        $this->executioner           = $executioner;
    }

    /**
     * @param Campaign             $campaign
     * @param ContactLimiter       $limiter
     * @param OutputInterface|null $output
     *
     * @return Counter|mixed
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    public function execute(Campaign $campaign, ContactLimiter $limiter, OutputInterface $output = null)
    {
        $this->campaign = $campaign;
        $this->limiter  = $limiter;
        $this->output   = ($output) ? $output : new NullOutput();
        $this->counter  = new Counter();

        try {
            $this->decisions = $this->campaign->getEventsByType(Event::TYPE_DECISION);

            $this->prepareForExecution();
            $this->executeEvents();
        } catch (NoContactsFoundException $exception) {
            $this->logger->debug('CAMPAIGN: No more contacts to process');
        } catch (NoEventsFoundException $exception) {
            $this->logger->debug('CAMPAIGN: No events to process');
        } finally {
            if ($this->progressBar) {
                $this->progressBar->finish();
                $this->output->writeln("\n");
            }
        }

        return $this->counter;
    }

    /**
     * @param                      $decisionId
     * @param ContactLimiter       $limiter
     * @param OutputInterface|null $output
     *
     * @return Counter
     *
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    public function validate($decisionId, ContactLimiter $limiter, OutputInterface $output = null)
    {
        $this->limiter = $limiter;
        $this->output  = ($output) ? $output : new NullOutput();
        $this->counter = new Counter();

        try {
            $this->decisions = $this->helper->getCollectionByDecisionId($decisionId);

            $this->checkCampaignIsPublished();
            $this->prepareForExecution();
            $this->executeEvents();
        } catch (NoContactsFoundException $exception) {
            $this->logger->debug('CAMPAIGN: No more contacts to process');
        } catch (NoEventsFoundException $exception) {
            $this->logger->debug('CAMPAIGN: No events to process');
        } finally {
            if ($this->progressBar) {
                $this->progressBar->finish();
                $this->output->writeln("\n");
            }
        }

        return $this->counter;
    }

    /**
     * @throws NoEventsFoundException
     */
    private function checkCampaignIsPublished()
    {
        if (!$this->decisions->count()) {
            throw new NoEventsFoundException();
        }

        $this->campaign = $this->decisions->first()->getCampaign();
        if (!$this->campaign->isPublished()) {
            throw new NoEventsFoundException();
        }
    }

    /**
     * @throws NoContactsFoundException
     * @throws NoEventsFoundException
     */
    private function prepareForExecution()
    {
        $this->logger->debug('CAMPAIGN: Triggering inaction events');

        $this->helper->removeDecisionsWithoutNegativeChildren($this->decisions);

        $totalDecisions = $this->decisions->count();
        if (!$totalDecisions) {
            throw new NoEventsFoundException();
        }

        $totalContacts = $this->inactiveContactFinder->getContactCount($this->campaign->getId(), $this->decisions->getKeys(), $this->limiter);
        $this->output->writeln(
            $this->translator->trans(
                'mautic.campaign.trigger.decision_count_analyzed',
                [
                    '%decisions%' => $totalDecisions,
                    '%leads%'     => $totalContacts,
                    '%batch%'     => $this->limiter->getBatchLimit(),
                ]
            )
        );

        if (!$totalContacts) {
            throw new NoContactsFoundException();
        }

        // Approximate total count because the query to fetch contacts will filter out those that have not arrived to this point in the campaign yet
        $this->progressBar = ProgressBarHelper::init($this->output, $totalContacts * $totalDecisions);
        $this->progressBar->start();
    }

    /**
     * @throws Dispatcher\Exception\LogNotProcessedException
     * @throws Dispatcher\Exception\LogPassedAndFailedException
     * @throws Exception\CannotProcessEventException
     * @throws NoContactsFoundException
     * @throws Scheduler\Exception\NotSchedulableException
     */
    private function executeEvents()
    {
        // Use the same timestamp across all contacts processed
        $now = new \DateTime();

        /** @var Event $decisionEvent */
        foreach ($this->decisions as $decisionEvent) {
            // We need the parent ID of the decision in order to fetch the time the contact executed this event
            $parentEvent   = $decisionEvent->getParent();
            $parentEventId = ($parentEvent) ? $parentEvent->getId() : null;

            // Because timing may not be appropriate, the starting row of the query may or may not change.
            // So use the max contact ID to filter/sort results.
            $this->startAtContactId = $this->limiter->getMinContactId() ?: 0;

            // Ge the first batch of contacts
            $contacts = $this->inactiveContactFinder->getContacts($this->campaign->getId(), $decisionEvent, $this->startAtContactId, $this->limiter);

            // Loop over all contacts till we've processed all those applicable for this decision
            while ($contacts->count()) {
                // Get the max contact ID before any are removed
                $startAtContactId = $this->getStartingContactIdForNextBatch($contacts);

                $this->progressBar->advance($contacts->count());
                $this->counter->advanceEvaluated($contacts->count());

                $inactiveEvents = $decisionEvent->getNegativeChildren();
                $this->helper->removeContactsThatAreNotApplicable($now, $contacts, $parentEventId, $inactiveEvents);
                $earliestLastActiveDateTime = $this->helper->getEarliestInactiveDateTime();

                $this->logger->debug(
                    'CAMPAIGN: ('.$decisionEvent->getId().') Earliest date for inactivity for this batch of contacts is '.
                    $earliestLastActiveDateTime->format('Y-m-d H:i:s T')
                );

                if ($contacts->count()) {
                    // Execute or schedule the events attached to the inactive side of the decision
                    $this->executeLogsForInactiveEvents($inactiveEvents, $contacts, $this->counter, $earliestLastActiveDateTime);
                    // Record decision for these contacts
                    $this->executioner->recordLogsAsExecutedForEvent($decisionEvent, $contacts, true);
                }

                // Clear contacts from memory
                $this->inactiveContactFinder->clear();

                if ($this->limiter->getContactId()) {
                    // No use making another call
                    break;
                }

                $this->logger->debug('CAMPAIGN: Fetching the next batch of inactive contacts after contact ID '.$startAtContactId);

                // Get the next batch, starting with the max contact ID
                $contacts = $this->inactiveContactFinder->getContacts($this->campaign->getId(), $decisionEvent, $startAtContactId, $this->limiter);
            }
        }
    }

    /**
     * @param ArrayCollection $contacts
     *
     * @return mixed
     *
     * @throws NoContactsFoundException
     */
    private function getStartingContactIdForNextBatch(ArrayCollection $contacts)
    {
        $maxId = max($contacts->getKeys());

        // Prevent a never ending loop if the contact ID never changes due to the last batch of contacts
        // getting removed because previously executed events are scheduled
        if ($this->startAtContactId === $maxId) {
            throw new NoContactsFoundException();
        }

        $this->startAtContactId = $maxId;

        return $maxId;
    }

    /**
     * @param ArrayCollection $children
     * @param ArrayCollection $contacts
     * @param Counter         $childrenCounter
     * @param \DateTime       $earliestLastActiveDateTime
     *
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogPassedAndFailedException
     * @throws \Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    private function executeLogsForInactiveEvents(ArrayCollection $events, ArrayCollection $contacts, Counter $childrenCounter, \DateTime $earliestLastActiveDateTime)
    {
        $eventExecutionDates = $this->scheduler->getSortedExecutionDates($events, $earliestLastActiveDateTime);

        /** @var \DateTime $earliestExecutionDate */
        $earliestExecutionDate = reset($eventExecutionDates);

        $executionDate = $this->executioner->getExecutionDate();

        foreach ($events as $event) {
            // Ignore decisions
            if (Event::TYPE_DECISION == $event->getEventType()) {
                $this->logger->debug('CAMPAIGN: Ignoring child event ID '.$event->getId().' as a decision');
                continue;
            }

            $eventExecutionDate = $this->scheduler->getExecutionDateForInactivity(
                $eventExecutionDates[$event->getId()],
                $earliestExecutionDate,
                $executionDate
            );

            $this->logger->debug(
                'CAMPAIGN: Event ID# '.$event->getId().
                ' to be executed on '.$eventExecutionDate->format('Y-m-d H:i:s')
            );

            if ($this->scheduler->shouldSchedule($eventExecutionDate, $executionDate)) {
                $childrenCounter->advanceTotalScheduled($contacts->count());
                $this->scheduler->schedule($event, $eventExecutionDate, $contacts, true);
                continue;
            }

            $this->executioner->executeForContacts($event, $contacts, $childrenCounter, true);
        }
    }
}
