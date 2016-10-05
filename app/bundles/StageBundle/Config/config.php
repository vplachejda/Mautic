<?php
/**
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'routes' => [
        'main' => [
            'mautic_stage_index' => [
                'path'       => '/stages/{page}',
                'controller' => 'MauticStageBundle:Stage:index',
            ],
            'mautic_stage_action' => [
                'path'       => '/stages/{objectAction}/{objectId}',
                'controller' => 'MauticStageBundle:Stage:execute',
            ],
        ],
        'api' => [
            'mautic_api_getstages' => [
                'path'       => '/stages',
                'controller' => 'MauticStageBundle:Api\StageApi:getEntities',
            ],
            'mautic_api_getstage' => [
                'path'       => '/stages/{id}',
                'controller' => 'MauticStageBundle:Api\StageApi:getEntity',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.stages.menu.index' => [
                'route'     => 'mautic_stage_index',
                'iconClass' => 'fa-tachometer',
                'access'    => ['stage:stages:view'],
                'priority'  => 25,
            ],
        ],
    ],

    'categories' => [
        'stage' => null,
    ],

    'services' => [
        'events' => [
            'mautic.stage.campaignbundle.subscriber' => [
                'class'     => 'Mautic\StageBundle\EventListener\CampaignSubscriber',
                'arguments' => [
                    'mautic.lead.model.lead',
                    'mautic.stage.model.stage',
                ],
            ],
            'mautic.stage.subscriber' => [
                'class'     => 'Mautic\StageBundle\EventListener\StageSubscriber',
                'arguments' => [
                    'mautic.helper.ip_lookup',
                    'mautic.core.model.auditlog',
                ],
            ],
            'mautic.stage.leadbundle.subscriber' => [
                'class' => 'Mautic\StageBundle\EventListener\LeadSubscriber',
            ],
            'mautic.stage.search.subscriber' => [
                'class'     => 'Mautic\StageBundle\EventListener\SearchSubscriber',
                'arguments' => [
                    'mautic.stage.model.stage',
                ],
            ],
            'mautic.stage.dashboard.subscriber' => [
                'class'     => 'Mautic\StageBundle\EventListener\DashboardSubscriber',
                'arguments' => [
                    'mautic.stage.model.stage',
                ],
            ],
        ],
        'forms' => [
            'mautic.stage.type.form' => [
                'class'     => 'Mautic\StageBundle\Form\Type\StageType',
                'arguments' => 'mautic.factory',
                'alias'     => 'stage',
            ],
            'mautic.stage.type.action' => [
                'class'     => 'Mautic\StageBundle\Form\Type\StageActionType',
                'arguments' => 'mautic.factory',
                'alias'     => 'stageaction',
            ],
            'mautic.stage.type.action_list' => [
                'class'     => 'Mautic\StageBundle\Form\Type\StageActionListType',
                'arguments' => 'mautic.factory',
                'alias'     => 'stageaction_list',
            ],
            'mautic.stage.type.action_change' => [
                'class'     => 'Mautic\StageBundle\Form\Type\StageActionChangeType',
                'arguments' => 'mautic.factory',
                'alias'     => 'stageaction_change',
            ],
            'mautic.stage.type.stage_list' => [
                'class'     => 'Mautic\StageBundle\Form\Type\StageListType',
                'arguments' => 'mautic.factory',
                'alias'     => 'stage_list',
            ],
            'mautic.point.type.genericstage_settings' => [
                'class' => 'Mautic\StageBundle\Form\Type\GenericStageSettingsType',
                'alias' => 'genericstage_settings',
            ],
        ],
        'models' => [
            'mautic.stage.model.stage' => [
                'class'     => 'Mautic\StageBundle\Model\StageModel',
                'arguments' => [
                    'mautic.lead.model.lead',
                    'session',
        ],
    ],
],
    ],
];
