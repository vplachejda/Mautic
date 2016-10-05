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
            'mautic.sms.campaignbundle.subscriber' => [
                'class'     => 'Mautic\SmsBundle\EventListener\CampaignSubscriber',
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'mautic.lead.model.lead',
                    'mautic.sms.model.sms',
                    'mautic.sms.api',
                    'mautic.helper.sms',
                ],
            ],
            'mautic.sms.configbundle.subscriber' => [
                'class' => 'Mautic\SmsBundle\EventListener\ConfigSubscriber',
            ],
            'mautic.sms.smsbundle.subscriber' => [
                'class'     => 'Mautic\SmsBundle\EventListener\SmsSubscriber',
                'arguments' => [
                    'mautic.core.model.auditlog',
                    'mautic.page.model.trackable',
                    'mautic.page.helper.token',
                    'mautic.asset.helper.token',
                ],
            ],
        ],
        'forms' => [
            'mautic.form.type.sms' => [
                'class'     => 'Mautic\SmsBundle\Form\Type\SmsType',
                'arguments' => 'mautic.factory',
                'alias'     => 'sms',
            ],
            'mautic.form.type.smsconfig' => [
                'class' => 'Mautic\SmsBundle\Form\Type\ConfigType',
                'alias' => 'smsconfig',
            ],
            'mautic.form.type.smssend_list' => [
                'class'     => 'Mautic\SmsBundle\Form\Type\SmsSendType',
                'arguments' => 'router',
                'alias'     => 'smssend_list',
            ],
            'mautic.form.type.sms_list' => [
                'class'     => 'Mautic\SmsBundle\Form\Type\SmsListType',
                'arguments' => 'mautic.factory',
                'alias'     => 'sms_list',
            ],
        ],
        'helpers' => [
            'mautic.helper.sms' => [
                'class'     => 'Mautic\SmsBundle\Helper\SmsHelper',
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'mautic.lead.model.lead',
                    'mautic.helper.phone_number',
                    'mautic.sms.model.sms',
                    '%mautic.sms_frequency_number%',
                ],
                'alias' => 'sms_helper',
            ],
        ],
        'other' => [
            'mautic.sms.api' => [
                'class'     => 'Mautic\SmsBundle\Api\TwilioApi',
                'arguments' => [
                    'mautic.page.model.trackable',
                    'mautic.twilio.service',
                    'mautic.helper.phone_number',
                    '%mautic.sms_sending_phone_number%',
                    'monolog.logger.mautic',
                ],
                'alias' => 'sms_api',
            ],
            'mautic.twilio.service' => [
                'class'     => 'Services_Twilio',
                'arguments' => [
                    '%mautic.sms_username%',
                    '%mautic.sms_password%',
                ],
                'alias' => 'twilio_service',
            ],
        ],
        'models' => [
            'mautic.sms.model.sms' => [
                'class'     => 'Mautic\SmsBundle\Model\SmsModel',
                'arguments' => [
                    'mautic.page.model.trackable',
                ],
            ],
        ],
    ],
    'routes' => [
        'main' => [
            'mautic_sms_index' => [
                'path'       => '/sms/{page}',
                'controller' => 'MauticSmsBundle:Sms:index',
            ],
            'mautic_sms_action' => [
                'path'       => '/sms/{objectAction}/{objectId}',
                'controller' => 'MauticSmsBundle:Sms:execute',
            ],
            'mautic_sms_contacts' => [
                'path'       => '/sms/view/{objectId}/contact/{page}',
                'controller' => 'MauticSmsBundle:Sms:contacts',
            ],
        ],
        'public' => [
            'mautic_receive_sms' => [
                'path'       => '/sms/receive',
                'controller' => 'MauticSmsBundle:Api\SmsApi:receive',
            ],
        ],
    ],
    'menu' => [
        'main' => [
            'items' => [
                'mautic.sms.smses' => [
                    'route'  => 'mautic_sms_index',
                    'access' => ['sms:smses:viewown', 'sms:smses:viewother'],
                    'parent' => 'mautic.core.channels',
                    'checks' => [
                        'parameters' => [
                            'sms_enabled' => true,
                        ],
                    ],
                ],
            ],
        ],
    ],
    'parameters' => [
        'sms_enabled'              => false,
        'sms_username'             => null,
        'sms_password'             => null,
        'sms_sending_phone_number' => null,
        'sms_frequency_number'     => null,
        'sms_frequency_time'       => null,
    ],
];
