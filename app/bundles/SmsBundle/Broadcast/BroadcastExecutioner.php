<?php

namespace Mautic\SmsBundle\Broadcast;

use Mautic\CampaignBundle\Executioner\ContactFinder\Limiter\ContactLimiter;
use Mautic\ChannelBundle\Event\ChannelBroadcastEvent;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\SmsBundle\Entity\Sms;
use Mautic\SmsBundle\Model\SmsModel;
use Symfony\Contracts\Translation\TranslatorInterface;

class BroadcastExecutioner
{
    /**
     * @var SmsModel
     */
    private $smsModel;

    private LeadRepository $leadRepository;

    /**
     * @var ContactLimiter
     */
    private $contactLimiter;

    /**
     * @var BroadcastQuery
     */
    private $broadcastQuery;

    /**
     * @var BroadcastResult
     */
    private $result;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * BroadcastExecutioner constructor.
     */
    public function __construct(SmsModel $smsModel, BroadcastQuery $broadcastQuery, TranslatorInterface $translator, LeadRepository $leadRepository)
    {
        $this->smsModel       = $smsModel;
        $this->broadcastQuery = $broadcastQuery;
        $this->translator     = $translator;
        $this->leadRepository = $leadRepository;
    }

    public function execute(ChannelBroadcastEvent $event)
    {
        // Get list of published broadcasts or broadcast if there is only a single ID
        $smses = $this->smsModel->getRepository()->getPublishedBroadcasts($event->getId());
        while (false !== ($next = $smses->next())) {
            $sms                  = reset($next);
            $this->contactLimiter = new ContactLimiter($event->getBatch(), null, $event->getMinContactIdFilter(), $event->getMaxContactIdFilter(), [], $event->getThreadId(), $event->getMaxThreads(), $event->getLimit());
            $this->result         = new BroadcastResult();
            try {
                $this->send($sms);
            } catch (\Exception $exception) {
            }
            $event->setResults(
                sprintf('%s: %s', $this->translator->trans('mautic.sms.sms'), $sms->getName()),
                $this->result->getSentCount(),
                $this->result->getFailedCount()
            );
        }
    }

    /**
     * @throws LimitQuotaException
     * @throws \Mautic\CampaignBundle\Executioner\Exception\NoContactsFoundException
     */
    private function send(Sms $sms)
    {
        $contacts = $this->broadcastQuery->getPendingContacts($sms, $this->contactLimiter);
        while (!empty($contacts)) {
            $reduction = 0;
            $leads     = [];
            foreach ($contacts as $contact) {
                $contactId  = $contact['id'];
                $results    = $this->smsModel->sendSms($sms, $contactId, [
                    'channel'=> [
                        'sms', $sms->getId(),
                    ],
                    'listId'=> $contact['listId'],
                ], $leads);
                $this->result->process($results);
                $reduction += count($results);
            }

            $this->contactLimiter->setBatchMinContactId($contactId + 1);

            if ($this->contactLimiter->hasCampaignLimit()) {
                $this->contactLimiter->reduceCampaignLimitRemaining($reduction);
            }

            $this->leadRepository->detachEntities($leads);

            // Next batch
            $contacts = $this->broadcastQuery->getPendingContacts($sms, $this->contactLimiter);
        }
    }
}
