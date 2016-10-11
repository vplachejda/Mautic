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
    'menu' => [
        'main' => [
            'items' => [
                'mautic.dynamicContent.dynamicContent' => [
                    'route'    => 'mautic_dynamicContent_index',
                    'access'   => ['dynamicContent:dynamicContents:viewown', 'dynamicContent:dynamicContents:viewother'],
                    'parent'   => 'mautic.core.components',
                    'priority' => 90,
                ],
            ],
        ],
    ],
    'routes' => [
        'main' => [
            'mautic_dynamicContent_index' => [
                'path'       => '/dwc/{page}',
                'controller' => 'MauticDynamicContentBundle:DynamicContent:index',
            ],
            'mautic_dynamicContent_action' => [
                'path'       => '/dwc/{objectAction}/{objectId}',
                'controller' => 'MauticDynamicContentBundle:DynamicContent:execute',
            ],
        ],
        'public' => [
            'mautic_api_dynamicContent_index' => [
                'path'       => '/dwc',
                'controller' => 'MauticDynamicContentBundle:Api\DynamicContentApi:getEntities',
            ],
            'mautic_api_dynamicContent_action' => [
                'path'       => '/dwc/{objectAlias}',
                'controller' => 'MauticDynamicContentBundle:Api\DynamicContentApi:process',
            ],
        ],
    ],
    'services' => [
        'events' => [
            'mautic.dynamicContent.campaignbundle.subscriber' => [
                'class'     => 'Mautic\DynamicContentBundle\EventListener\CampaignSubscriber',
                'arguments' => [
                    'mautic.lead.model.lead',
                    'mautic.dynamicContent.model.dynamicContent',
                    'session',
                ],
            ],
            'mautic.dynamicContent.js.subscriber' => [
                'class' => 'Mautic\DynamicContentBundle\EventListener\BuildJsSubscriber',
            ],
            'mautic.dynamicContent.subscriber' => [
                'class'     => 'Mautic\DynamicContentBundle\EventListener\DynamicContentSubscriber',
                'arguments' => [
                    'mautic.page.model.trackable',
                    'mautic.page.helper.token',
                    'mautic.asset.helper.token',
                    'mautic.core.model.auditlog',
                ],
            ],
        ],
        'forms' => [
            'mautic.form.type.dwc' => [
                'class'     => 'Mautic\DynamicContentBundle\Form\Type\DynamicContentType',
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
                'alias' => 'dwc',
            ],
            'mautic.form.type.dwcsend_list' => [
                'class'     => 'Mautic\DynamicContentBundle\Form\Type\DynamicContentSendType',
                'arguments' => [
                    'router',
                ],
                'alias' => 'dwcsend_list',
            ],
            'mautic.form.type.dwcdecision_list' => [
                'class'     => 'Mautic\DynamicContentBundle\Form\Type\DynamicContentDecisionType',
                'arguments' => [
                    'router',
                ],
                'alias' => 'dwcdecision_list',
            ],
            'mautic.form.type.dwc_list' => [
                'class'     => 'Mautic\DynamicContentBundle\Form\Type\DynamicContentListType',
                'arguments' => 'mautic.factory',
                'alias'     => 'dwc_list',
            ],
        ],
        'models' => [
            'mautic.dynamicContent.model.dynamicContent' => [
                'class'     => 'Mautic\DynamicContentBundle\Model\DynamicContentModel',
                'arguments' => [

                ],
            ],
        ],
        'other' => [
            'mautic.helper.dynamicContent' => [
                'class'     => 'Mautic\DynamicContentBundle\Helper\DynamicContentHelper',
                'arguments' => [
                    'mautic.dynamicContent.model.dynamicContent',
                    'mautic.campaign.model.event',
                    'event_dispatcher',
                ],
            ], ],
    ],
];
