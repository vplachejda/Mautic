<?php

/*
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
            'mautic_integration_config' => [
                'path'       => '/integration/{integration}/config',
                'controller' => 'IntegrationsBundle:Config:edit',
            ],
            'mautic_integration_config_field_pagination' => [
                'path'       => '/integration/{integration}/config/{object}/{page}',
                'controller' => 'IntegrationsBundle:FieldPagination:paginate',
                'defaults' => [
                    'page' => 1,
                ],
            ],
            'mautic_integration_config_field_update' => [
                'path'       => '/integration/{integration}/config/{object}/field/{field}',
                'controller' => 'IntegrationsBundle:UpdateField:update',
            ],
        ],
        'public' => [
            'mautic_integration_public_callback' => [
                'path'       => '/integration/{integration}/callback',
                'controller' => 'IntegrationsBundle:Auth:callback',
            ],
        ],
        'api' => [
        ],
    ],
    'menu' => [
    ],
    'services' => [
        'commands' => [
            'mautic.integrations.command.sync' => [
                'class'     => \MauticPlugin\IntegrationsBundle\Command\SyncCommand::class,
                'arguments' => [
                    'mautic.integrations.sync.service',
                ],
                'tag' => 'console.command',
            ],
        ],
        'events' => [
            'mautic.integrations.subscriber.lead' => [
                'class'     => \MauticPlugin\IntegrationsBundle\EventListener\LeadSubscriber::class,
                'arguments' => [
                    'mautic.integrations.repository.field_change',
                    'mautic.integrations.helper.variable_expresser',
                    'mautic.integrations.helper.sync_integrations',
                ],
            ],
            'mautic.integrations.subscriber.controller' => [
                'class' => \MauticPlugin\IntegrationsBundle\EventListener\ControllerSubscriber::class,
                'arguments' => [
                    'mautic.integrations.helper',
                    'controller_resolver',
                ],
            ],
            'mautic.integrations.subscriber.ui_contact_integrations_tab' => [
                'class' => \MauticPlugin\IntegrationsBundle\EventListener\UIContactIntegrationsTabSubscriber::class,
                'arguments' => [
                    'mautic.integrations.repository.object_mapping',
                ],
            ],
            'mautic.integrations.subscriber.contact_timeline_events' => [
                'class' => \MauticPlugin\IntegrationsBundle\EventListener\TimelineSubscriber::class,
                'arguments' => [
                    'mautic.lead.repository.lead_event_log',
                    'translator',
                ],
            ],
        ],
        'forms' => [
            'mautic.integrations.form.config.integration' => [
                'class' => \MauticPlugin\IntegrationsBundle\Form\Type\IntegrationConfigType::class,
                'arguments' => [
                    'mautic.integrations.helper.config_integrations',
                ],
            ],
            'mautic.integrations.form.config.feature_settings' => [
                'class' => \MauticPlugin\IntegrationsBundle\Form\Type\IntegrationFeatureSettingsType::class,
            ],
            'mautic.integrations.form.config.sync_settings' => [
                'class' => \MauticPlugin\IntegrationsBundle\Form\Type\IntegrationSyncSettingsType::class,
            ],
            'mautic.integrations.form.config.sync_settings_field_mappings' => [
                'class' => \MauticPlugin\IntegrationsBundle\Form\Type\IntegrationSyncSettingsFieldMappingsType::class,
                'arguments' => [
                    'monolog.logger.mautic',
                    'translator',
                ],
            ],
            'mautic.integrations.form.config.sync_settings_object_field_directions' => [
                'class' => \MauticPlugin\IntegrationsBundle\Form\Type\IntegrationSyncSettingsObjectFieldType::class,
            ],
            'mautic.integrations.form.config.sync_settings_object_field_mapping' => [
                'class' => \MauticPlugin\IntegrationsBundle\Form\Type\IntegrationSyncSettingsObjectFieldMappingType::class,
                'arguments' => [
                    'translator',
                    'mautic.integrations.sync.data_exchange.mautic.field_helper',
                ]
            ],
            'mautic.integrations.form.config.sync_settings_object_field' => [
                'class' => \MauticPlugin\IntegrationsBundle\Form\Type\IntegrationSyncSettingsObjectFieldType::class,
            ],
            'mautic.integrations.form.config.feature_settings.activity_list' => [
                'class' => \MauticPlugin\IntegrationsBundle\Form\Type\ActivityListType::class,
                'arguments' => [
                    'mautic.lead.model.lead',
                ],
            ],
        ],
        'helpers' => [
            'mautic.integrations.helper.variable_expresser' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\VariableExpresser\VariableExpresserHelper::class,
            ],
            'mautic.integrations.helper' => [
                'class' => \MauticPlugin\IntegrationsBundle\Helper\IntegrationsHelper::class,
                'arguments' => [
                    'mautic.plugin.integrations.repository.integration',
                    'mautic.integrations.service.encryption',
                    'event_dispatcher',
                ],
            ],
            'mautic.integrations.helper.auth_integrations' => [
                'class' => \MauticPlugin\IntegrationsBundle\Helper\AuthIntegrationsHelper::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ]
            ],
            'mautic.integrations.helper.sync_integrations' => [
                'class' => \MauticPlugin\IntegrationsBundle\Helper\SyncIntegrationsHelper::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
            'mautic.integrations.helper.config_integrations' => [
                'class' => \MauticPlugin\IntegrationsBundle\Helper\ConfigIntegrationsHelper::class,
                'arguments' => [
                    'mautic.integrations.helper',
                ],
            ],
            'mautic.integrations.helper.field_validator' => [
                'class' => \MauticPlugin\IntegrationsBundle\Helper\FieldValidationHelper::class,
                'arguments' => [
                    'mautic.integrations.sync.data_exchange.mautic.field_helper',
                    'translator',
                ],
            ],
        ],
        'other' => [
            'mautic.integrations.service.encryption' => [
                'class' => \MauticPlugin\IntegrationsBundle\Facade\EncryptionService::class,
                'arguments' => [
                    'mautic.helper.encryption',
                ],
            ],
            'mautic.http.client' => [
                'class' => GuzzleHttp\Client::class
            ],
            'mautic.integrations.auth_provider.api_key' => [
                'class' => \MauticPlugin\IntegrationsBundle\Auth\Provider\ApiKey\HttpFactory::class,
            ],
            'mautic.integrations.auth_provider.basic_auth' => [
                'class' => \MauticPlugin\IntegrationsBundle\Auth\Provider\BasicAuth\HttpFactory::class,
            ],
            'mautic.integrations.auth_provider.oauth1atwolegged' => [
                'class' => \MauticPlugin\IntegrationsBundle\Auth\Provider\Oauth1aTwoLegged\HttpFactory::class,
            ],
            'mautic.integrations.auth_provider.oauth2twolegged' => [
                'class' => \MauticPlugin\IntegrationsBundle\Auth\Provider\Oauth2TwoLegged\HttpFactory::class,
            ],
            'mautic.integrations.auth_provider.oauth2threelegged' => [
                'class' => \MauticPlugin\IntegrationsBundle\Auth\Provider\Oauth2ThreeLegged\HttpFactory::class,
            ],
            'mautic.integrations.auth_provider.token_persistence_factory' => [
                'class' => \MauticPlugin\IntegrationsBundle\Auth\Support\Oauth2\Token\TokenPersistenceFactory::class,
                'arguments' => ['mautic.integrations.helper']
            ] ,
        ],
        'repositories' => [
            'mautic.integrations.repository.field_change' => [
                'class'     => \Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \MauticPlugin\IntegrationsBundle\Entity\FieldChange::class,
                ],
            ],
            'mautic.integrations.repository.object_mapping' => [
                'class'     => \Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \MauticPlugin\IntegrationsBundle\Entity\ObjectMapping::class
                ],
            ],
            // Placeholder till the plugin bundle implements this
            'mautic.plugin.integrations.repository.integration' => [
                'class'     => \Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \Mautic\PluginBundle\Entity\Integration::class
                ],
            ],
        ],
        'sync' => [
            'mautic.sync.logger' => [
                'class' =>  \MauticPlugin\IntegrationsBundle\Sync\Logger\DebugLogger::class,
                'arguments' => [
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.integrations.helper.sync_judge' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncJudge\SyncJudge::class,
            ],
            'mautic.integrations.helper.contact_object' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ObjectHelper\ContactObjectHelper::class,
                'arguments' => [
                    'mautic.lead.model.lead',
                    'mautic.lead.repository.lead',
                    'doctrine.dbal.default_connection',
                    'mautic.lead.model.field',
                    'mautic.lead.model.dnc'
                ],
            ],
            'mautic.integrations.helper.company_object' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ObjectHelper\CompanyObjectHelper::class,
                'arguments' => [
                    'mautic.lead.model.company',
                    'mautic.lead.repository.company',
                    'doctrine.dbal.default_connection',
                ],
            ],
            'mautic.integrations.sync.data_exchange.mautic.order_executioner' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\Executioner\OrderExecutioner::class,
                'arguments' => [
                    'mautic.integrations.helper.sync_mapping',
                    'mautic.integrations.helper.contact_object',
                    'mautic.integrations.helper.company_object',
                ],
            ],
            'mautic.integrations.sync.data_exchange.mautic.field_helper' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Helper\FieldHelper::class,
                'arguments' => [
                    'mautic.lead.model.field',
                    'mautic.integrations.helper.variable_expresser',
                    'mautic.channel.helper.channel_list',
                    'translator',
                    'event_dispatcher',
                ],
            ],
            'mautic.integrations.sync.sync_process.value_helper' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncProcess\Direction\Helper\ValueHelper::class,
                'arguments' => [
                    'translator',
                ],
            ],
            'mautic.integrations.sync.data_exchange.mautic.field_builder' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ReportBuilder\FieldBuilder::class,
                'arguments' => [
                    'router',
                    'mautic.integrations.sync.data_exchange.mautic.field_helper',
                    'mautic.integrations.helper.contact_object',
                ],
            ],
            'mautic.integrations.sync.data_exchange.mautic.full_object_report_builder' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ReportBuilder\FullObjectReportBuilder::class,
                'arguments' => [
                    'mautic.integrations.helper.contact_object',
                    'mautic.integrations.helper.company_object',
                    'mautic.integrations.sync.data_exchange.mautic.field_builder'
                ],
            ],
            'mautic.integrations.sync.data_exchange.mautic.partial_object_report_builder' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ReportBuilder\PartialObjectReportBuilder::class,
                'arguments' => [
                    'mautic.integrations.repository.field_change',
                    'mautic.integrations.sync.data_exchange.mautic.field_helper',
                    'mautic.integrations.helper.contact_object',
                    'mautic.integrations.helper.company_object',
                    'mautic.integrations.sync.data_exchange.mautic.field_builder',
                ],
            ],
            'mautic.integrations.sync.data_exchange.mautic' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange::class,
                'arguments' => [
                    'mautic.integrations.repository.field_change',
                    'mautic.integrations.sync.data_exchange.mautic.field_helper',
                    'mautic.integrations.helper.sync_mapping',
                    'mautic.integrations.sync.data_exchange.mautic.full_object_report_builder',
                    'mautic.integrations.sync.data_exchange.mautic.partial_object_report_builder',
                    'mautic.integrations.sync.data_exchange.mautic.order_executioner',
                ],
            ],
            'mautic.integrations.sync.integration_process.object_change_generator' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncProcess\Direction\Integration\ObjectChangeGenerator::class,
                'arguments' => [
                    'mautic.integrations.sync.sync_process.value_helper'
                ],
            ],
            'mautic.integrations.sync.integration_process' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncProcess\Direction\Integration\IntegrationSyncProcess::class,
                'arguments' => [
                    'mautic.integrations.helper.sync_date',
                    'mautic.integrations.helper.sync_mapping',
                    'mautic.integrations.sync.integration_process.object_change_generator',
                ],
            ],
            'mautic.integrations.sync.internal_process.object_change_generator' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncProcess\Direction\Internal\ObjectChangeGenerator::class,
                'arguments' => [
                    'mautic.integrations.helper.sync_judge',
                    'mautic.integrations.sync.sync_process.value_helper',
                    'mautic.integrations.sync.data_exchange.mautic.field_helper',
                ],
            ],
            'mautic.integrations.sync.internal_process' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncProcess\Direction\Internal\MauticSyncProcess::class,
                'arguments' => [
                    'mautic.integrations.helper.sync_date',
                    'mautic.integrations.sync.internal_process.object_change_generator',
                ],
            ],
            'mautic.integrations.sync.service' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\SyncService\SyncService::class,
                'arguments' => [
                    'mautic.integrations.sync.data_exchange.mautic',
                    'mautic.integrations.helper.sync_date',
                    'mautic.integrations.helper.sync_mapping',
                    'mautic.integrations.helper.sync_integrations',
                    'event_dispatcher',
                    'mautic.integrations.sync.notifier',
                    'mautic.integrations.sync.integration_process',
                    'mautic.integrations.sync.internal_process',
                ],
                'methodCalls' => [
                    'initiateDebugLogger' => ['mautic.sync.logger'],
                ],
            ],
            'mautic.integrations.helper.sync_date' => [
                'class'     => \MauticPlugin\IntegrationsBundle\Sync\Helper\SyncDateHelper::class,
                'arguments' => [
                    'doctrine.dbal.default_connection',
                ],
            ],
            'mautic.integrations.helper.sync_mapping' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Helper\MappingHelper::class,
                'arguments' => [
                    'mautic.lead.model.field',
                    'mautic.integrations.repository.object_mapping',
                    'mautic.integrations.helper.contact_object',
                    'mautic.integrations.helper.company_object',
                ],
            ],
            'mautic.integrations.sync.notifier' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Notifier::class,
                'arguments' => [
                    'mautic.integrations.sync.notification.handler_container',
                    'mautic.integrations.helper.sync_integrations',
                    'mautic.integrations.helper.config_integrations',
                ],
            ],
            'mautic.integrations.sync.notification.writer' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Writer::class,
                'arguments' => [
                    'mautic.core.model.notification',
                    'mautic.core.model.auditlog',
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.integrations.sync.notification.handler_container' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Handler\HandlerContainer::class,
            ],
            'mautic.integrations.sync.notification.handler_company' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Handler\CompanyNotificationHandler::class,
                'arguments' => [
                    'mautic.integrations.sync.notification.writer',
                    'mautic.integrations.sync.notification.helper_user_notification',
                    'mautic.integrations.sync.notification.helper_company',
                ],
                'tag' => 'mautic.sync.notification_handler',
            ],
            'mautic.integrations.sync.notification.handler_contact' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Handler\ContactNotificationHandler::class,
                'arguments' => [
                    'mautic.integrations.sync.notification.writer',
                    'mautic.lead.repository.lead_event_log',
                    'translator',
                    'doctrine.orm.entity_manager',
                    'mautic.integrations.sync.notification.helper_user_summary_notification',
                ],
                'tag' => 'mautic.sync.notification_handler',
            ],
            'mautic.integrations.sync.notification.helper_company' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Helper\CompanyHelper::class,
                'arguments' => [
                    'doctrine.dbal.default_connection',
                ],
            ],
            'mautic.integrations.sync.notification.helper_user' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Helper\UserHelper::class,
                'arguments' => [
                    'doctrine.dbal.default_connection',
                ],
            ],
            'mautic.integrations.sync.notification.helper_route' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Helper\RouteHelper::class,
                'arguments' => [
                    'router',
                ],
            ],
            'mautic.integrations.sync.notification.helper_user_notification' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Helper\UserNotificationHelper::class,
                'arguments' => [
                    'mautic.integrations.sync.notification.writer',
                    'mautic.integrations.sync.notification.helper_user',
                    'mautic.integrations.sync.notification.helper_route',
                    'translator',
                ],
            ],
            'mautic.integrations.sync.notification.helper_user_summary_notification' => [
                'class' => \MauticPlugin\IntegrationsBundle\Sync\Notification\Helper\UserSummaryNotificationHelper::class,
                'arguments' => [
                    'mautic.integrations.sync.notification.writer',
                    'mautic.integrations.sync.notification.helper_user',
                    'mautic.integrations.sync.notification.helper_route',
                    'translator',
                ],
            ],
        ],
    ],
];
