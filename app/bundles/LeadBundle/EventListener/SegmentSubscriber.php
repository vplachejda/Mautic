<?php

namespace Mautic\LeadBundle\EventListener;

use Mautic\CoreBundle\Exception\RecordCanNotUnpublishException;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\LeadBundle\Event\LeadListEvent as SegmentEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\ListModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentSubscriber implements EventSubscriberInterface
{
    private \Mautic\CoreBundle\Helper\IpLookupHelper $ipLookupHelper;

    private \Mautic\CoreBundle\Model\AuditLogModel $auditLogModel;

    private \Symfony\Contracts\Translation\TranslatorInterface $translator;

    private \Mautic\LeadBundle\Model\ListModel $listModel;

    public function __construct(
        IpLookupHelper $ipLookupHelper,
        AuditLogModel $auditLogModel,
        ListModel $listModel,
        TranslatorInterface $translator
    ) {
        $this->ipLookupHelper    = $ipLookupHelper;
        $this->auditLogModel     = $auditLogModel;
        $this->listModel         = $listModel;
        $this->translator        = $translator;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            LeadEvents::LIST_PRE_UNPUBLISH => ['onSegmentPreUnpublish', 0],
            LeadEvents::LIST_POST_SAVE     => ['onSegmentPostSave', 0],
            LeadEvents::LIST_POST_DELETE   => ['onSegmentDelete', 0],
        ];
    }

    /**
     * Add a segment entry to the audit log.
     */
    public function onSegmentPostSave(SegmentEvent $event): void
    {
        $segment = $event->getList();
        if ($details = $event->getChanges()) {
            $log = [
                'bundle'    => 'lead',
                'object'    => 'segment',
                'objectId'  => $segment->getId(),
                'action'    => ($event->isNew()) ? 'create' : 'update',
                'details'   => $details,
                'ipAddress' => $this->ipLookupHelper->getIpAddressFromRequest(),
            ];
            $this->auditLogModel->writeToLog($log);
        }
    }

    public function onSegmentPreUnpublish(SegmentEvent $event): ?RecordCanNotUnpublishException
    {
        $leadList = $event->getList();
        $lists    = $this->listModel->getSegmentsWithDependenciesOnSegment($leadList->getId(), 'name');
        if (count($lists)) {
            $message = $this->translator->trans('mautic.lead_list.is_in_use', ['%segments%' => implode(',', $lists)], 'validators');
            throw new RecordCanNotUnpublishException($message);
        }

        return null;
    }

    /**
     * Add a segment delete entry to the audit log.
     */
    public function onSegmentDelete(SegmentEvent $event): void
    {
        $segment = $event->getList();
        $log     = [
            'bundle'    => 'lead',
            'object'    => 'segment',
            'objectId'  => $segment->deletedId,
            'action'    => 'delete',
            'details'   => ['name', $segment->getName()],
            'ipAddress' => $this->ipLookupHelper->getIpAddressFromRequest(),
        ];
        $this->auditLogModel->writeToLog($log);
    }
}
