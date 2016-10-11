<?php
/**
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'services' => [
        'events' => [
            'mautic.notification.campaignbundle.subscriber' => [
                'class'     => 'Mautic\NotificationBundle\EventListener\CampaignSubscriber',
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'mautic.lead.model.lead',
                    'mautic.notification.model.notification',
                    'mautic.notification.api',
                ],
            ],
            'mautic.notification.configbundle.subscriber' => [
                'class' => 'Mautic\NotificationBundle\EventListener\ConfigSubscriber',
            ],
            'mautic.notification.pagebundle.subscriber' => [
                'class'     => 'Mautic\NotificationBundle\EventListener\PageSubscriber',
                'arguments' => [
                    'templating.helper.assets',
                    'mautic.helper.core_parameters',
                ],
            ],
            'mautic.core.js.subscriber' => [
                'class' => 'Mautic\NotificationBundle\EventListener\BuildJsSubscriber',
            ],
            'mautic.notification.notificationbundle.subscriber' => [
                'class'     => 'Mautic\NotificationBundle\EventListener\NotificationSubscriber',
                'arguments' => [
                    'mautic.core.model.auditlog',
                    'mautic.page.model.trackable',
                    'mautic.page.helper.token',
                    'mautic.asset.helper.token',
                ],
            ],
        ],
        'forms' => [
            'mautic.form.type.notification' => [
                'class'     => 'Mautic\NotificationBundle\Form\Type\NotificationType',
                'arguments' => 'mautic.factory',
                'alias'     => 'notification',
            ],
            'mautic.form.type.notificationconfig' => [
                'class' => 'Mautic\NotificationBundle\Form\Type\ConfigType',
                'alias' => 'notificationconfig',
            ],
            'mautic.form.type.notificationsend_list' => [
                'class'     => 'Mautic\NotificationBundle\Form\Type\NotificationSendType',
                'arguments' => 'router',
                'alias'     => 'notificationsend_list',
            ],
            'mautic.form.type.notification_list' => [
                'class'     => 'Mautic\NotificationBundle\Form\Type\NotificationListType',
                'arguments' => 'mautic.factory',
                'alias'     => 'notification_list',
            ],
        ],
        'helpers' => [
            'mautic.helper.notification' => [
                'class'     => 'Mautic\NotificationBundle\Helper\NotificationHelper',
                'arguments' => 'mautic.factory',
                'alias'     => 'notification_helper',
            ],
        ],
        'other' => [
            'mautic.notification.api' => [
                'class'     => 'Mautic\NotificationBundle\Api\OneSignalApi',
                'arguments' => [
                    'mautic.factory',
                    'mautic.http.connector',
                ],
                'alias' => 'notification_api',
            ],
        ],
        'models' => [
            'mautic.notification.model.notification' => [
                'class'     => 'Mautic\NotificationBundle\Model\NotificationModel',
                'arguments' => [
                    'mautic.page.model.trackable',
                ],
            ],
        ],
    ],
    'routes' => [
        'main' => [
            'mautic_notification_index' => [
                'path'       => '/notifications/{page}',
                'controller' => 'MauticNotificationBundle:Notification:index',
            ],
            'mautic_notification_action' => [
                'path'       => '/notifications/{objectAction}/{objectId}',
                'controller' => 'MauticNotificationBundle:Notification:execute',
            ],
            'mautic_notification_contacts' => [
                'path'       => '/notifications/view/{objectId}/contact/{page}',
                'controller' => 'MauticNotificationBundle:Notification:contacts',
            ],
        ],
        'public' => [
            'mautic_receive_notification' => [
                'path'       => '/notification/receive',
                'controller' => 'MauticNotificationBundle:Api\NotificationApi:receive',

            ],
            'mautic_subscribe_notification' => [
                'path'       => '/notification/subscribe',
                'controller' => 'MauticNotificationBundle:Api\NotificationApi:subscribe',
            ],
            'mautic_notification_popup' => [
                'path'       => '/notification',
                'controller' => 'MauticNotificationBundle:Popup:index',
            ],

            // JS / Manifest URL's
            'mautic_onesignal_worker' => [
                'path'       => '/OneSignalSDKWorker.js',
                'controller' => 'MauticNotificationBundle:Js:worker',
            ],
            'mautic_onesignal_updater' => [
                'path'       => '/OneSignalSDKUpdaterWorker.js',
                'controller' => 'MauticNotificationBundle:Js:updater',
            ],
            'mautic_onesignal_manifest' => [
                'path'       => '/manifest.json',
                'controller' => 'MauticNotificationBundle:Js:manifest',
            ],
        ],
    ],
    'menu' => [
        'main' => [
            'items' => [
                'mautic.notification.notifications' => [
                    'route'  => 'mautic_notification_index',
                    'access' => ['notification:notifications:viewown', 'notification:notifications:viewother'],
                    'checks' => [
                        'parameters' => [
                            'notification_enabled' => true,
                        ],
                    ],
                    'parent'   => 'mautic.core.channels',
                    'priority' => 80,
                ],
            ],
        ],
    ],
    //'categories' => [
    //    'notification' => null
    //],
    'parameters' => [
        'notification_enabled'       => false,
        'notification_app_id'        => null,
        'notification_rest_api_key'  => null,
        'notification_safari_web_id' => null,
    ],
];
