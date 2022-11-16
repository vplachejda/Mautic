<?php

return [
    'routes' => [
        'main' => [
            'mautic_page_index' => [
                'path'       => '/pages/{page}',
                'controller' => 'Mautic\PageBundle\Controller\PageController::indexAction',
            ],
            'mautic_page_action' => [
                'path'       => '/pages/{objectAction}/{objectId}',
                'controller' => 'Mautic\PageBundle\Controller\PageController::executeAction',
            ],
            'mautic_page_results' => [
                'path'       => '/pages/results/{objectId}/{page}',
                'controller' => 'Mautic\PageBundle\Controller\PageController::resultsAction',
            ],
            'mautic_page_export' => [
                'path'       => '/pages/results/{objectId}/export/{format}',
                'controller' => 'Mautic\PageBundle\Controller\PageController::exportAction',
                'defaults'   => [
                    'format' => 'csv',
                ],
            ],
        ],
        'public' => [
            'mautic_page_tracker' => [
                'path'       => '/mtracking.gif',
                'controller' => 'Mautic\PageBundle\Controller\PublicController::trackingImageAction',
            ],
            'mautic_page_tracker_cors' => [
                'path'       => '/mtc/event',
                'controller' => 'Mautic\PageBundle\Controller\PublicController::trackingAction',
            ],
            'mautic_page_tracker_getcontact' => [
                'path'       => '/mtc',
                'controller' => 'Mautic\PageBundle\Controller\PublicController::getContactIdAction',
            ],
            'mautic_url_redirect' => [
                'path'       => '/r/{redirectId}',
                'controller' => 'Mautic\PageBundle\Controller\PublicController::redirectAction',
            ],
            'mautic_page_redirect' => [
                'path'       => '/redirect/{redirectId}',
                'controller' => 'Mautic\PageBundle\Controller\PublicController::redirectAction',
            ],
            'mautic_page_preview' => [
                'path'       => '/page/preview/{id}',
                'controller' => 'Mautic\PageBundle\Controller\PublicController::previewAction',
            ],
            'mautic_gated_video_hit' => [
                'path'       => '/video/hit',
                'controller' => 'Mautic\PageBundle\Controller\PublicController::hitVideoAction',
            ],
        ],
        'api' => [
            'mautic_api_pagesstandard' => [
                'standard_entity' => true,
                'name'            => 'pages',
                'path'            => '/pages',
                'controller'      => 'Mautic\PageBundle\Controller\Api\PageApiController',
            ],
        ],
        'catchall' => [
            'mautic_page_public' => [
                'path'         => '/{slug}',
                'controller'   => 'Mautic\PageBundle\Controller\PublicController::indexAction',
                'requirements' => [
                    'slug' => '^(?!(_(profiler|wdt)|css|images|js|favicon.ico|apps/bundles/|plugins/)).+',
                ],
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'items' => [
                'mautic.page.pages' => [
                    'route'    => 'mautic_page_index',
                    'access'   => ['page:pages:viewown', 'page:pages:viewother'],
                    'parent'   => 'mautic.core.components',
                    'priority' => 100,
                ],
            ],
        ],
    ],

    'categories' => [
        'page' => null,
    ],

    'services' => [
        'models' => [
            'mautic.page.model.page' => [
                'class'     => \Mautic\PageBundle\Model\PageModel::class,
                'arguments' => [
                    'mautic.helper.cookie',
                    'mautic.helper.ip_lookup',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.field',
                    'mautic.page.model.redirect',
                    'mautic.page.model.trackable',
                    'mautic.queue.service',
                    'mautic.lead.model.company',
                    'mautic.tracker.device',
                    'mautic.tracker.contact',
                    'mautic.helper.core_parameters',
                    'mautic.lead.helper.contact_request_helper',
                ],
                'methodCalls' => [
                    'setCatInUrl' => [
                        '%mautic.cat_in_page_url%',
                    ],
                ],
            ],
            'mautic.page.model.redirect' => [
                'class'     => 'Mautic\PageBundle\Model\RedirectModel',
                'arguments' => [
                    'mautic.helper.url',
                ],
            ],
            'mautic.page.model.trackable' => [
                'class'     => \Mautic\PageBundle\Model\TrackableModel::class,
                'arguments' => [
                    'mautic.page.model.redirect',
                    'mautic.lead.repository.field',
                ],
            ],
            'mautic.page.model.video' => [
                'class'     => 'Mautic\PageBundle\Model\VideoModel',
                'arguments' => [
                    'mautic.helper.ip_lookup',
                    'mautic.tracker.contact',
                ],
            ],
            'mautic.page.model.tracking.404' => [
                'class'     => \Mautic\PageBundle\Model\Tracking404Model::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'mautic.tracker.contact',
                    'mautic.page.model.page',
                ],
            ],
        ],
        'repositories' => [
            'mautic.page.repository.hit' => [
                'class'     => Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \Mautic\PageBundle\Entity\Hit::class,
                ],
            ],
            'mautic.page.repository.page' => [
                'class'     => Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \Mautic\PageBundle\Entity\Page::class,
                ],
            ],
            'mautic.page.repository.redirect' => [
                'class'     => Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \Mautic\PageBundle\Entity\Redirect::class,
                ],
            ],
        ],
        'fixtures' => [
            'mautic.page.fixture.page_category' => [
                'class'     => \Mautic\PageBundle\DataFixtures\ORM\LoadPageCategoryData::class,
                'tag'       => \Doctrine\Bundle\FixturesBundle\DependencyInjection\CompilerPass\FixturesCompilerPass::FIXTURE_TAG,
                'arguments' => ['mautic.category.model.category'],
            ],
            'mautic.page.fixture.page' => [
                'class'     => \Mautic\PageBundle\DataFixtures\ORM\LoadPageData::class,
                'tag'       => \Doctrine\Bundle\FixturesBundle\DependencyInjection\CompilerPass\FixturesCompilerPass::FIXTURE_TAG,
                'arguments' => ['mautic.page.model.page'],
            ],
            'mautic.page.fixture.page_hit' => [
                'class'     => \Mautic\PageBundle\DataFixtures\ORM\LoadPageHitData::class,
                'tag'       => \Doctrine\Bundle\FixturesBundle\DependencyInjection\CompilerPass\FixturesCompilerPass::FIXTURE_TAG,
                'arguments' => ['mautic.page.model.page'],
            ],
        ],
        'other' => [
            'mautic.page.helper.token' => [
                'class'     => 'Mautic\PageBundle\Helper\TokenHelper',
                'arguments' => 'mautic.page.model.page',
            ],
            'mautic.page.helper.tracking' => [
                'class'     => 'Mautic\PageBundle\Helper\TrackingHelper',
                'arguments' => [
                    'session',
                    'mautic.helper.core_parameters',
                    'request_stack',
                    'mautic.tracker.contact',
                ],
            ],
        ],
    ],

    'parameters' => [
        'cat_in_page_url'       => false,
        'google_analytics'      => null,
        'track_contact_by_ip'   => false,
        'track_by_tracking_url' => false,
        'redirect_list_types'   => [
            '301' => 'mautic.page.form.redirecttype.permanent',
            '302' => 'mautic.page.form.redirecttype.temporary',
        ],
        'google_analytics_id'                   => null,
        'google_analytics_trackingpage_enabled' => false,
        'google_analytics_landingpage_enabled'  => false,
        'google_analytics_anonymize_ip'         => false,
        'facebook_pixel_id'                     => null,
        'facebook_pixel_trackingpage_enabled'   => false,
        'facebook_pixel_landingpage_enabled'    => false,
        'do_not_track_404_anonymous'            => false,
    ],
];
