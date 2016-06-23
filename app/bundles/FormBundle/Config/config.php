<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'routes'   => [
        'main'   => [
            'mautic_form_pagetoken_index' => [
                'path'       => '/forms/pagetokens/{page}',
                'controller' => 'MauticFormBundle:SubscribedEvents\BuilderToken:index'
            ],
            'mautic_formaction_action'    => [
                'path'       => '/forms/action/{objectAction}/{objectId}',
                'controller' => 'MauticFormBundle:Action:execute'
            ],
            'mautic_formfield_action'     => [
                'path'       => '/forms/field/{objectAction}/{objectId}',
                'controller' => 'MauticFormBundle:Field:execute'
            ],
            'mautic_form_index'           => [
                'path'       => '/forms/{page}',
                'controller' => 'MauticFormBundle:Form:index'
            ],
            'mautic_form_results'         => [
                'path'       => '/forms/results/{objectId}/{page}',
                'controller' => 'MauticFormBundle:Result:index',
            ],
            'mautic_form_export'          => [
                'path'       => '/forms/results/{objectId}/export/{format}',
                'controller' => 'MauticFormBundle:Result:export',
                'defaults'   => [
                    'format' => 'csv'
                ]
            ],
            'mautic_form_results_delete'   => [
                'path'       => '/forms/results/{formId}/delete/{objectId}',
                'controller' => 'MauticFormBundle:Result:delete',
                'defaults'   => [
                    'objectId' => 0
                ]
            ],
            'mautic_form_action'          => [
                'path'       => '/forms/{objectAction}/{objectId}',
                'controller' => 'MauticFormBundle:Form:execute'
            ]
        ],
        'api'    => [
            'mautic_api_getforms' => [
                'path'       => '/forms',
                'controller' => 'MauticFormBundle:Api\FormApi:getEntities'
            ],
            'mautic_api_getform'  => [
                'path'       => '/forms/{id}',
                'controller' => 'MauticFormBundle:Api\FormApi:getEntity'
            ]
        ],
        'public' => [
            'mautic_form_postresults'  => [
                'path'       => '/form/submit',
                'controller' => 'MauticFormBundle:Public:submit'
            ],
            'mautic_form_generateform' => [
                'path'       => '/form/generate.js',
                'controller' => 'MauticFormBundle:Public:generate'
            ],
            'mautic_form_postmessage'  => [
                'path'       => '/form/message',
                'controller' => 'MauticFormBundle:Public:message'
            ],
            'mautic_form_preview'      => [
                'path'       => '/form/{id}',
                'controller' => 'MauticFormBundle:Public:preview',
                'defaults'   => [
                    'id' => '0'
                ]
            ]
        ]
    ],

    'menu'     => [
        'main' => [
            'items'    => [
                'mautic.form.forms' => [
                    'route'     => 'mautic_form_index',
                    'access'    => ['form:forms:viewown', 'form:forms:viewother'],
                    'parent'    => 'mautic.core.components',
                    'priority'  => 200
                ]
            ]
        ]
    ],

    'categories' => [
        'form' => null
    ],

    'services' => [
        'events' => [
            'mautic.form.subscriber'                => [
                'class' => 'Mautic\FormBundle\EventListener\FormSubscriber'
            ],
            'mautic.form.pagebundle.subscriber'     => [
                'class' => 'Mautic\FormBundle\EventListener\PageSubscriber'
            ],
            'mautic.form.pointbundle.subscriber'    => [
                'class' => 'Mautic\FormBundle\EventListener\PointSubscriber'
            ],
            'mautic.form.reportbundle.subscriber'   => [
                'class' => 'Mautic\FormBundle\EventListener\ReportSubscriber'
            ],
            'mautic.form.campaignbundle.subscriber' => [
                'class' => 'Mautic\FormBundle\EventListener\CampaignSubscriber',
                'arguments' => [
                    'mautic.factory',
                    'mautic.form.model.form',
                    'mautic.form.model.submission'
                ]
            ],
            'mautic.form.calendarbundle.subscriber' => [
                'class' => 'Mautic\FormBundle\EventListener\CalendarSubscriber'
            ],
            'mautic.form.leadbundle.subscriber'     => [
                'class'       => 'Mautic\FormBundle\EventListener\LeadSubscriber',
            ],
            'mautic.form.emailbundle.subscriber'    => [
                'class' => 'Mautic\FormBundle\EventListener\EmailSubscriber'
            ],
            'mautic.form.search.subscriber'         => [
                'class' => 'Mautic\FormBundle\EventListener\SearchSubscriber'
            ],
            'mautic.form.webhook.subscriber'        => [
                'class' => 'Mautic\FormBundle\EventListener\WebhookSubscriber'
            ],
            'mautic.form.dashboard.subscriber'      => [
                'class' => 'Mautic\FormBundle\EventListener\DashboardSubscriber'
            ]
        ],
        'forms'  => [
            'mautic.form.type.form'                      => [
                'class'     => 'Mautic\FormBundle\Form\Type\FormType',
                'arguments' => 'mautic.factory',
                'alias'     => 'mauticform'
            ],
            'mautic.form.type.field'                     => [
                'class' => 'Mautic\FormBundle\Form\Type\FieldType',
                'alias' => 'formfield'
            ],
            'mautic.form.type.action'                    => [
                'class' => 'Mautic\FormBundle\Form\Type\ActionType',
                'alias' => 'formaction'
            ],
            'mautic.form.type.field_propertytext'        => [
                'class' => 'Mautic\FormBundle\Form\Type\FormFieldTextType',
                'alias' => 'formfield_text'
            ],
            'mautic.form.type.field_propertyplaceholder' => [
                'class' => 'Mautic\FormBundle\Form\Type\FormFieldPlaceholderType',
                'alias' => 'formfield_placeholder'
            ],
            'mautic.form.type.field_propertyselect'      => [
                'class' => 'Mautic\FormBundle\Form\Type\FormFieldSelectType',
                'alias' => 'formfield_select'
            ],
            'mautic.form.type.field_propertycaptcha'     => [
                'class' => 'Mautic\FormBundle\Form\Type\FormFieldCaptchaType',
                'alias' => 'formfield_captcha'
            ],
            'mautic.form.type.field_propertygroup'      => [
                'class' => 'Mautic\FormBundle\Form\Type\FormFieldGroupType',
                'alias' => 'formfield_group'
            ],
            'mautic.form.type.pointaction_formsubmit'    => [
                'class' => 'Mautic\FormBundle\Form\Type\PointActionFormSubmitType',
                'alias' => 'pointaction_formsubmit'
            ],
            'mautic.form.type.form_list'                 => [
                'class'     => 'Mautic\FormBundle\Form\Type\FormListType',
                'arguments' => 'mautic.factory',
                'alias'     => 'form_list'
            ],
            'mautic.form.type.campaignevent_formsubmit'  => [
                'class' => 'Mautic\FormBundle\Form\Type\CampaignEventFormSubmitType',
                'alias' => 'campaignevent_formsubmit'
            ],
            'mautic.form.type.campaignevent_form_field_value'  => [
                'class' => 'Mautic\FormBundle\Form\Type\CampaignEventFormFieldValueType',
                'arguments' => 'mautic.factory',
                'alias' => 'campaignevent_form_field_value'
            ],
            'mautic.form.type.form_submitaction_sendemail'  => [
                'class'     => 'Mautic\FormBundle\Form\Type\SubmitActionEmailType',
                'arguments' => 'mautic.factory',
                'alias'     => 'form_submitaction_sendemail'
            ]
        ],
        'models' =>  [
            'mautic.form.model.action' => [
                'class' => 'Mautic\FormBundle\Model\ActionModel'
            ],
            'mautic.form.model.field' => [
                'class' => 'Mautic\FormBundle\Model\FieldModel'
            ],
            'mautic.form.model.form' => [
                'class' => 'Mautic\FormBundle\Model\FormModel',
                'arguments' => [
                    'request_stack',
                    'mautic.helper.templating',
                    'mautic.schema.helper.factory',
                    'mautic.form.model.action',
                    'mautic.form.model.field'
                ]
            ],
            'mautic.form.model.submission' => [
                'class' => 'Mautic\FormBundle\Model\SubmissionModel',
                'arguments' => [
                    'mautic.helper.ip_lookup',
                    'mautic.helper.templating',
                    'mautic.form.model.form',
                    'mautic.page.model.page',
                    'mautic.lead.model.lead',
                    'mautic.campaign.model.campaign',
                    'mautic.lead.model.field'
                ]
            ]
        ]
    ]
];
