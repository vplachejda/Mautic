<?php

namespace Mautic\CampaignBundle\Controller;

use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Model\EventLogModel;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\DateHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AjaxController.
 */
class AjaxController extends CommonAjaxController
{
    private DateHelper $dateHelper;

    public function __construct(CorePermissions $security, DateHelper $dateHelper)
    {
        $this->security   = $security;
        $this->dateHelper = $dateHelper;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function updateConnectionsAction(Request $request)
    {
        $session        = $request->getSession();
        $campaignId     = InputHelper::clean($request->query->get('campaignId'));
        $canvasSettings = $request->request->get('canvasSettings') ?? [];
        if (empty($campaignId)) {
            $dataArray = ['success' => 0];
        } else {
            $session->set('mautic.campaign.'.$campaignId.'.events.canvassettings', $canvasSettings);

            $dataArray = ['success' => 1];
        }

        return $this->sendJsonResponse($dataArray);
    }

    public function updateScheduledCampaignEventAction(Request $request)
    {
        $eventId      = (int) $request->request->get('eventId');
        $contactId    = (int) $request->request->get('contactId');
        $newDate      = InputHelper::clean($request->request->get('date'));
        $originalDate = InputHelper::clean($request->request->get('originalDate'));

        $dataArray = ['success' => 0, 'date' => $originalDate];

        if (!empty($eventId) && !empty($contactId) && !empty($newDate)) {
            if ($log = $this->getContactEventLog($eventId, $contactId)) {
                $newDate = new \DateTime($newDate);

                if ($newDate >= new \DateTime()) {
                    $log->setTriggerDate($newDate);

                    /** @var EventLogModel $logModel */
                    $logModel = $this->getModel('campaign.event_log');
                    $logModel->saveEntity($log);

                    $dataArray = [
                        'success' => 1,
                        'date'    => $newDate->format('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        // Format the date to match the view
        $dataArray['formattedDate'] = $this->dateHelper->toFull($dataArray['date']);

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function cancelScheduledCampaignEventAction(Request $request)
    {
        $dataArray = ['success' => 0];

        $eventId   = (int) $request->request->get('eventId');
        $contactId = (int) $request->request->get('contactId');
        if (!empty($eventId) && !empty($contactId)) {
            if ($log = $this->getContactEventLog($eventId, $contactId)) {
                $log->setIsScheduled(false);

                /** @var EventLogModel $logModel */
                $logModel           = $this->getModel('campaign.event_log');
                $metadata           = $log->getMetadata();
                $metadata['errors'] = $this->translator->trans(
                    'mautic.campaign.event.cancelled.time',
                    ['%date%' => $log->getTriggerDate()->format('Y-m-d H:i:s')]
                );
                $log->setMetadata($metadata);
                $logModel->getRepository()->saveEntity($log);

                $dataArray = ['success' => 1];
            }
        }

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * @param $eventId
     * @param $contactId
     *
     * @return LeadEventLog|null
     */
    protected function getContactEventLog($eventId, $contactId)
    {
        $contact = $this->getModel('lead')->getEntity($contactId);
        if ($contact) {
            if ($this->security->hasEntityAccess('lead:leads:editown', 'lead:leads:editother', $contact->getPermissionUser())) {
                /** @var EventLogModel $logModel */
                $logModel = $this->getModel('campaign.event_log');

                /** @var LeadEventLog $log */
                $log = $logModel->getRepository()
                                ->findOneBy(
                                    [
                                        'lead'  => $contactId,
                                        'event' => $eventId,
                                    ],
                                    ['dateTriggered' => 'desc']
                                );

                if ($log && ($log->getTriggerDate() > new \DateTime())) {
                    return $log;
                }
            }
        }

        return null;
    }
}
