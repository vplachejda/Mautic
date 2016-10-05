<?php
/**
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\NotificationBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\NotificationBundle\Api\AbstractNotificationApi;
use Mautic\NotificationBundle\Event\NotificationSendEvent;
use Mautic\NotificationBundle\Model\NotificationModel;
use Mautic\NotificationBundle\NotificationEvents;

/**
 * Class CampaignSubscriber.
 */
class CampaignSubscriber extends CommonSubscriber
{
    /**
     * @var LeadModel
     */
    protected $leadModel;

    /**
     * @var NotificationModel
     */
    protected $notificationModel;

    /**
     * @var AbstractNotificationApi
     */
    protected $notificationApi;

    /**
     * @var CoreParametersHelper
     */
    protected $coreParametersHelper;

    /**
     * CampaignSubscriber constructor.
     *
     * @param CoreParametersHelper    $coreParametersHelper
     * @param LeadModel               $leadModel
     * @param NotificationModel       $notificationModel
     * @param AbstractNotificationApi $notificationApi
     */
    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        LeadModel $leadModel,
        NotificationModel $notificationModel,
        AbstractNotificationApi $notificationApi
    ) {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->leadModel            = $leadModel;
        $this->notificationModel    = $notificationModel;
        $this->notificationApi      = $notificationApi;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD              => ['onCampaignBuild', 0],
            NotificationEvents::ON_CAMPAIGN_TRIGGER_ACTION => ['onCampaignTriggerAction', 0],
        ];
    }

    /**
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        if ($this->coreParametersHelper->getParameter('notification_enabled')) {
            $event->addAction(
                'notification.send_notification',
                [
                    'label'            => 'mautic.notification.campaign.send_notification',
                    'description'      => 'mautic.notification.campaign.send_notification.tooltip',
                    'eventName'        => NotificationEvents::ON_CAMPAIGN_TRIGGER_ACTION,
                    'formType'         => 'notificationsend_list',
                    'formTypeOptions'  => ['update_select' => 'campaignevent_properties_notification'],
                    'formTheme'        => 'MauticNotificationBundle:FormTheme\NotificationSendList',
                    'timelineTemplate' => 'MauticNotificationBundle:SubscribedEvents\Timeline:index.html.php',
                ]
            );
        }
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerAction(CampaignExecutionEvent $event)
    {
        $lead = $event->getLead();

        if ($this->leadModel->isContactable($lead, 'notification') !== DoNotContact::IS_CONTACTABLE) {
            return $event->setFailed('mautic.notification.campaign.failed.not_contactable');
        }

        // If lead has subscribed on multiple devices, get all of them.
        /** @var \Mautic\NotificationBundle\Entity\PushID[] $pushIDs */
        $pushIDs = $lead->getPushIDs();

        $playerID = [];

        foreach ($pushIDs as $pushID) {
            $playerID[] = $pushID->getPushID();
        }

        if (empty($playerID)) {
            return $event->setFailed('mautic.notification.campaign.failed.not_subscribed');
        }

        $notificationId = (int) $event->getConfig()['notification'];

        /** @var \Mautic\NotificationBundle\Entity\Notification $notification */
        $notification = $this->notificationModel->getEntity($notificationId);

        if ($notification->getId() !== $notificationId) {
            return $event->setFailed('mautic.notification.campaign.failed.missing_entity');
        }

        if ($url = $notification->getUrl()) {
            $url = $this->notificationApi->convertToTrackedUrl(
                $url,
                [
                    'notification' => $notification->getId(),
                    'lead'         => $lead->getId(),
                ]
            );
        }

        $tokenEvent = $this->dispatcher->dispatch(
            NotificationEvents::TOKEN_REPLACEMENT,
            new TokenReplacementEvent(
                $notification->getMessage(),
                $lead,
                ['channel' => ['notification', $notification->getId()]]
            )
        );

        $sendEvent = $this->dispatcher->dispatch(
            NotificationEvents::NOTIFICATION_ON_SEND,
            new NotificationSendEvent($tokenEvent->getContent(), $notification->getHeading(), $lead)
        );

        $response = $this->notificationApi->sendNotification(
            $playerID,
            $sendEvent->getMessage(),
            $sendEvent->getHeading(),
            $url
        );

        $event->setChannel('notification', $notification->getId());

        // If for some reason the call failed, tell mautic to try again by return false
        if ($response->code !== 200) {
            return $event->setResult(false);
        }

        $this->notificationModel->createStatEntry($notification, $lead);
        $this->notificationModel->getRepository()->upCount($notificationId);

        $result = [
            'status'  => 'mautic.notification.timeline.status.delivered',
            'type'    => 'mautic.notification.notification',
            'id'      => $notification->getId(),
            'name'    => $notification->getName(),
            'heading' => $event->getHeading(),
            'content' => $event->getMessage(),
        ];

        $event->setResult($result);
    }
}
