<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Helper\BuilderTokenHelper;
use Mautic\CoreBundle\Helper\EmojiHelper;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\EmailBundle\Entity\Email;
use Symfony\Component\HttpFoundation\Response;

class EmailController extends FormController
{
    /**
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($page = 1)
    {
        $model = $this->getModel('email');

        //set some permissions
        $permissions = $this->get('mautic.security')->isGranted(
            [
                'email:emails:viewown',
                'email:emails:viewother',
                'email:emails:create',
                'email:emails:editown',
                'email:emails:editother',
                'email:emails:deleteown',
                'email:emails:deleteother',
                'email:emails:publishown',
                'email:emails:publishother',
            ],
            'RETURN_ARRAY'
        );

        if (!$permissions['email:emails:viewown'] && !$permissions['email:emails:viewother']) {
            return $this->accessDenied();
        }

        if ($this->request->getMethod() == 'POST') {
            $this->setListFilters();
        }

        $session = $this->get('session');

        $listFilters = [
            'filters' => [
                'placeholder' => $this->get('translator')->trans('mautic.email.filter.placeholder'),
                'multiple'    => true,
            ],
        ];

        // Reset available groups
        $listFilters['filters']['groups'] = [];

        //set limits
        $limit = $session->get('mautic.email.limit', $this->coreParametersHelper->getParameter('default_pagelimit'));
        $start = ($page === 1) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $session->get('mautic.email.filter', ''));
        $session->set('mautic.email.filter', $search);

        $filter = [
            'string' => $search,
            'force'  => [
                ['column' => 'e.variantParent', 'expr' => 'isNull'],
                ['column' => 'e.translationParent', 'expr' => 'isNull'],
            ],
        ];
        if (!$permissions['email:emails:viewother']) {
            $filter['force'][] =
                ['column' => 'e.createdBy', 'expr' => 'eq', 'value' => $this->user->getId()];
        }

        //retrieve a list of Lead Lists
        $listFilters['filters']['groups']['mautic.core.filter.lists'] = [
            'options' => $this->getModel('lead.list')->getUserLists(),
            'prefix'  => 'list',
        ];

        //retrieve a list of themes
        $listFilters['filters']['groups']['mautic.core.filter.themes'] = [
            'options' => $this->factory->getInstalledThemes('email'),
            'prefix'  => 'theme',
        ];

        $currentFilters = $session->get('mautic.email.list_filters', []);
        $updatedFilters = $this->request->get('filters', false);
        $ignoreListJoin = true;

        if ($updatedFilters) {
            // Filters have been updated

            // Parse the selected values
            $newFilters     = [];
            $updatedFilters = json_decode($updatedFilters, true);

            if ($updatedFilters) {
                foreach ($updatedFilters as $updatedFilter) {
                    list($clmn, $fltr) = explode(':', $updatedFilter);

                    $newFilters[$clmn][] = $fltr;
                }

                $currentFilters = $newFilters;
            } else {
                $currentFilters = [];
            }
        }
        $session->set('mautic.email.list_filters', $currentFilters);

        if (!empty($currentFilters)) {
            $listIds = $catIds = $templates = [];
            foreach ($currentFilters as $type => $typeFilters) {
                switch ($type) {
                    case 'list':
                        $key = 'lists';
                        break;
                    case 'category':
                        $key = 'categories';
                        break;
                    case 'theme':
                        $key = 'themes';
                        break;
                }

                $listFilters['filters']['groups']['mautic.core.filter.'.$key]['values'] = $typeFilters;

                foreach ($typeFilters as $fltr) {
                    switch ($type) {
                        case 'list':
                            $listIds[] = (int) $fltr;
                            break;
                        case 'category':
                            $catIds[] = (int) $fltr;
                            break;
                        case 'theme':
                            $templates[] = $fltr;
                            break;
                    }
                }
            }

            if (!empty($listIds)) {
                $filter['force'][] = ['column' => 'l.id', 'expr' => 'in', 'value' => $listIds];
                $ignoreListJoin    = false;
            }

            if (!empty($catIds)) {
                $filter['force'][] = ['column' => 'c.id', 'expr' => 'in', 'value' => $catIds];
            }

            if (!empty($templates)) {
                $filter['force'][] = ['column' => 'e.template', 'expr' => 'in', 'value' => $templates];
            }
        }

        $orderBy    = $session->get('mautic.email.orderby', 'e.subject');
        $orderByDir = $session->get('mautic.email.orderbydir', 'DESC');

        $emails = $model->getEntities(
            [
                'start'          => $start,
                'limit'          => $limit,
                'filter'         => $filter,
                'orderBy'        => $orderBy,
                'orderByDir'     => $orderByDir,
                'ignoreListJoin' => $ignoreListJoin,
            ]
        );

        $count = count($emails);
        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current page so redirect to the last page
            if ($count === 1) {
                $lastPage = 1;
            } else {
                $lastPage = (floor($count / $limit)) ?: 1;
            }

            $session->set('mautic.email.page', $lastPage);
            $returnUrl = $this->generateUrl('mautic_email_index', ['page' => $lastPage]);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => ['page' => $lastPage],
                    'contentTemplate' => 'MauticEmailBundle:Email:index',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_email_index',
                        'mauticContent' => 'email',
                    ],
                ]
            );
        }
        $session->set('mautic.email.page', $page);

        return $this->delegateView(
            [
                'viewParameters' => [
                    'searchValue' => $search,
                    'filters'     => $listFilters,
                    'items'       => $emails,
                    'totalItems'  => $count,
                    'page'        => $page,
                    'limit'       => $limit,
                    'tmpl'        => $this->request->get('tmpl', 'index'),
                    'permissions' => $permissions,
                    'model'       => $model,
                    'security'    => $this->get('mautic.security'),
                ],
                'contentTemplate' => 'MauticEmailBundle:Email:list.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_email_index',
                    'mauticContent' => 'email',
                    'route'         => $this->generateUrl('mautic_email_index', ['page' => $page]),
                ],
            ]
        );
    }

    /**
     * Loads a specific form into the detailed panel.
     *
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model    = $this->getModel('email');
        $security = $this->get('mautic.security');

        /** @var \Mautic\EmailBundle\Entity\Email $email */
        $email = $model->getEntity($objectId);
        //set the page we came from
        $page = $this->get('session')->get('mautic.email.page', 1);

        // Init the date range filter form
        $dateRangeValues = $this->request->get('daterange', []);
        $action          = $this->generateUrl('mautic_email_action', ['objectAction' => 'view', 'objectId' => $objectId]);
        $dateRangeForm   = $this->get('form.factory')->create('daterange', $dateRangeValues, ['action' => $action]);

        if ($email === null) {
            //set the return URL
            $returnUrl = $this->generateUrl('mautic_email_index', ['page' => $page]);

            return $this->postActionRedirect(
                [
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => ['page' => $page],
                    'contentTemplate' => 'MauticEmailBundle:Email:index',
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_email_index',
                        'mauticContent' => 'email',
                    ],
                    'flashes' => [
                        [
                            'type'    => 'error',
                            'msg'     => 'mautic.email.error.notfound',
                            'msgVars' => ['%id%' => $objectId],
                        ],
                    ],
                ]
            );
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'email:emails:viewown',
            'email:emails:viewother',
            $email->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        }

        //get A/B test information
        list($parent, $children) = $email->getVariants();
        $properties              = [];
        $variantError            = false;
        $weight                  = 0;
        if (count($children)) {
            foreach ($children as $c) {
                $variantSettings = $c->getVariantSettings();

                if (is_array($variantSettings) && isset($variantSettings['winnerCriteria'])) {
                    if ($c->isPublished()) {
                        if (!isset($lastCriteria)) {
                            $lastCriteria = $variantSettings['winnerCriteria'];
                        }

                        //make sure all the variants are configured with the same criteria
                        if ($lastCriteria != $variantSettings['winnerCriteria']) {
                            $variantError = true;
                        }

                        $weight += $variantSettings['weight'];
                    }
                } else {
                    $variantSettings['winnerCriteria'] = '';
                    $variantSettings['weight']         = 0;
                }

                $properties[$c->getId()] = $variantSettings;
            }

            $properties[$parent->getId()]['weight']         = 100 - $weight;
            $properties[$parent->getId()]['winnerCriteria'] = '';
        }

        $abTestResults = [];
        $criteria      = $model->getBuilderComponents($email, 'abTestWinnerCriteria');
        if (!empty($lastCriteria) && empty($variantError)) {
            if (isset($criteria['criteria'][$lastCriteria])) {
                $testSettings = $criteria['criteria'][$lastCriteria];

                $args = [
                    'factory'    => $this->factory,
                    'email'      => $email,
                    'parent'     => $parent,
                    'children'   => $children,
                    'properties' => $properties,
                ];

                //execute the callback
                if (is_callable($testSettings['callback'])) {
                    if (is_array($testSettings['callback'])) {
                        $reflection = new \ReflectionMethod($testSettings['callback'][0], $testSettings['callback'][1]);
                    } elseif (strpos($testSettings['callback'], '::') !== false) {
                        $parts      = explode('::', $testSettings['callback']);
                        $reflection = new \ReflectionMethod($parts[0], $parts[1]);
                    } else {
                        $reflection = new \ReflectionMethod(null, $testSettings['callback']);
                    }

                    $pass = [];
                    foreach ($reflection->getParameters() as $param) {
                        if (isset($args[$param->getName()])) {
                            $pass[] = $args[$param->getName()];
                        } else {
                            $pass[] = null;
                        }
                    }
                    $abTestResults = $reflection->invokeArgs($this, $pass);
                }
            }
        }

        //get related translations
        list($translationParent, $translationChildren) = $email->getTranslations();

        // Prepare stats for bargraph
        if ($chartStatsSource = $this->request->query->get('stats', false)) {
            $includeVariants = ('all' == $chartStatsSource);
        } else {
            $includeVariants = (($email->isVariant() && $parent === $email) || ($email->isTranslation() && $translationParent === $email));
        }

        if ($email->getEmailType() == 'template') {
            $stats = $model->getEmailGeneralStats(
                $email,
                $includeVariants,
                null,
                new \DateTime($dateRangeForm->get('date_from')->getData()),
                new \DateTime($dateRangeForm->get('date_to')->getData())
            );
        } else {
            $stats = $model->getEmailListStats(
                $email,
                $includeVariants,
                new \DateTime($dateRangeForm->get('date_from')->getData()),
                new \DateTime($dateRangeForm->get('date_to')->getData())
            );
        }

        $statsDevices = $model->getEmailDeviceStats(
            $email,
            $includeVariants,
            new \DateTime($dateRangeForm->get('date_from')->getData()),
            new \DateTime($dateRangeForm->get('date_to')->getData())
        );

        // Audit Log
        $logs = $this->getModel('core.auditLog')->getLogForObject('email', $email->getId(), $email->getDateAdded());

        // Get click through stats
        $trackableLinks = $model->getEmailClickStats($email->getId());

        return $this->delegateView(
            [
                'returnUrl' => $this->generateUrl(
                    'mautic_email_action',
                    [
                        'objectAction' => 'view',
                        'objectId'     => $email->getId(),
                    ]
                ),
                'viewParameters' => [
                    'email'        => $email,
                    'stats'        => $stats,
                    'statsDevices' => $statsDevices,
                    'showAllStats' => $includeVariants,
                    'trackables'   => $trackableLinks,
                    'pending'      => $model->getPendingLeads($email, null, true),
                    'logs'         => $logs,
                    'variants'     => [
                        'parent'     => $parent,
                        'children'   => $children,
                        'properties' => $properties,
                        'criteria'   => $criteria['criteria'],
                    ],
                    'translations' => [
                        'parent'   => $translationParent,
                        'children' => $translationChildren,
                    ],
                    'permissions' => $security->isGranted(
                        [
                            'email:emails:viewown',
                            'email:emails:viewother',
                            'email:emails:create',
                            'email:emails:editown',
                            'email:emails:editother',
                            'email:emails:deleteown',
                            'email:emails:deleteother',
                            'email:emails:publishown',
                            'email:emails:publishother',
                        ],
                        'RETURN_ARRAY'
                    ),
                    'abTestResults' => $abTestResults,
                    'security'      => $security,
                    'previewUrl'    => $this->generateUrl(
                        'mautic_email_preview',
                        ['objectId' => $email->getId()],
                        true
                    ),
                    'dateRangeForm' => $dateRangeForm->createView(),
                ],
                'contentTemplate' => 'MauticEmailBundle:Email:details.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_email_index',
                    'mauticContent' => 'email',
                ],
            ]
        );
    }

    /**
     * Generates new form and processes post data.
     *
     * @param \Mautic\EmailBundle\Entity\Email $entity
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction($entity = null)
    {
        $model = $this->getModel('email');

        if (!($entity instanceof Email)) {
            /** @var \Mautic\EmailBundle\Entity\Email $entity */
            $entity = $model->getEntity();
        }

        $method  = $this->request->getMethod();
        $session = $this->get('session');
        if (!$this->get('mautic.security')->isGranted('email:emails:create')) {
            return $this->accessDenied();
        }

        //set the page we came from
        $page   = $session->get('mautic.email.page', 1);
        $action = $this->generateUrl('mautic_email_action', ['objectAction' => 'new']);

        $updateSelect = ($method == 'POST')
            ? $this->request->request->get('emailform[updateSelect]', false, true)
            : $this->request->get(
                'updateSelect',
                false
            );

        if ($updateSelect) {
            // Force type to template
            $entity->setEmailType('template');
        }

        //create the form
        $form = $model->createForm($entity, $this->get('form.factory'), $action, ['update_select' => $updateSelect]);

        ///Check for a submitted form and process it
        if ($method == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    $content = $entity->getCustomHtml();

                    // Parse visual placeholders into tokens
                    BuilderTokenHelper::replaceVisualPlaceholdersWithTokens($content);

                    $entity->setCustomHtml($content);

                    //form is valid so process the data
                    $model->saveEntity($entity);

                    $this->addFlash(
                        'mautic.core.notice.created',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_email_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_email_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ]
                    );

                    if ($form->get('buttons')->get('save')->isClicked()) {
                        $viewParameters = [
                            'objectAction' => 'view',
                            'objectId'     => $entity->getId(),
                        ];
                        $returnUrl = $this->generateUrl('mautic_email_action', $viewParameters);
                        $template  = 'MauticEmailBundle:Email:view';
                    } else {
                        //return edit view so that all the session stuff is loaded
                        return $this->editAction($entity->getId(), true);
                    }
                }
            } else {
                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl('mautic_email_index', $viewParameters);
                $template       = 'MauticEmailBundle:Email:index';
                //clear any modified content
                $session->remove('mautic.emailbuilder.'.$entity->getSessionId().'.content');
            }

            $passthrough = [
                'activeLink'    => 'mautic_email_index',
                'mauticContent' => 'email',
            ];

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                        'group'        => $entity->getLanguage(),
                    ]
                );
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                        'passthroughVars' => $passthrough,
                    ]
                );
            }
        }

        $slotTypes   = $model->getBuilderComponents($entity, 'slotTypes');
        $sectionForm = $this->get('form.factory')->create('builder_section');

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'          => $this->setFormTheme($form, 'MauticEmailBundle:Email:form.html.php', 'MauticEmailBundle:FormTheme\Email'),
                    'isVariant'     => $entity->isVariant(true),
                    'tokens'        => $model->getBuilderComponents($entity, 'tokenSections'),
                    'email'         => $entity,
                    'slots'         => $this->buildSlotForms($slotTypes),
                    'themes'        => $this->factory->getInstalledThemes('email', true),
                    'builderAssets' => trim(preg_replace('/\s+/', ' ', $this->getAssetsForBuilder())), // strip new lines
                    'sectionForm'   => $sectionForm->createView(),
                ],
                'contentTemplate' => 'MauticEmailBundle:Email:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_email_index',
                    'mauticContent' => 'email',
                    'updateSelect'  => InputHelper::clean($this->request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'mautic_email_action',
                        [
                            'objectAction' => 'new',
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * @param      $objectId
     * @param bool $ignorePost
     * @param bool $forceTypeSelection
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editAction($objectId, $ignorePost = false, $forceTypeSelection = false)
    {
        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model  = $this->getModel('email');
        $method = $this->request->getMethod();

        $entity  = $model->getEntity($objectId);
        $session = $this->get('session');
        $page    = $this->get('session')->get('mautic.email.page', 1);

        //set the return URL
        $returnUrl = $this->generateUrl('mautic_email_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticEmailBundle:Email:index',
            'passthroughVars' => [
                'activeLink'    => 'mautic_email_index',
                'mauticContent' => 'email',
            ],
        ];

        //not found
        if ($entity === null) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.email.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'email:emails:editown',
            'email:emails:editother',
            $entity->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            //deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'email');
        }

        //Create the form
        $action = $this->generateUrl('mautic_email_action', ['objectAction' => 'edit', 'objectId' => $objectId]);

        $updateSelect = ($method == 'POST')
            ? $this->request->request->get('emailform[updateSelect]', false, true)
            : $this->request->get(
                'updateSelect',
                false
            );

        if ($updateSelect) {
            // Force type to template
            $entity->setEmailType('template');
        }

        $form = $model->createForm($entity, $this->get('form.factory'), $action, ['update_select' => $updateSelect]);

        ///Check for a submitted form and process it
        if (!$ignorePost && $method == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    $content = $entity->getCustomHtml();
                    BuilderTokenHelper::replaceVisualPlaceholdersWithTokens($content);

                    $entity->setCustomHtml($content);

                    //form is valid so process the data
                    $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());

                    $this->addFlash(
                        'mautic.core.notice.updated',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_email_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_email_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ],
                        'warning'
                    );
                }
            } else {
                //clear any modified content
                $session->remove('mautic.emailbuilder.'.$objectId.'.content');
                //unlock the entity
                $model->unlockEntity($entity);
            }

            $template    = 'MauticEmailBundle:Email:view';
            $passthrough = [
                'activeLink'    => 'mautic_email_index',
                'mauticContent' => 'email',
            ];

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'emailId'      => $entity->getId(),
                        'emailSubject' => $entity->getSubject(),
                        'emailName'    => $entity->getName(),
                        'emailLang'    => $entity->getLanguage(),
                    ]
                );
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                $viewParameters = [
                    'objectAction' => 'view',
                    'objectId'     => $entity->getId(),
                ];

                return $this->postActionRedirect(
                    array_merge(
                        $postActionVars,
                        [
                            'returnUrl'       => $this->generateUrl('mautic_email_action', $viewParameters),
                            'viewParameters'  => $viewParameters,
                            'contentTemplate' => $template,
                            'passthroughVars' => $passthrough,
                        ]
                    )
                );
            } elseif ($valid && $form->get('buttons')->get('apply')->isClicked()) {
                // Rebuild the form in the case apply is clicked so that DEC content is properly populated if all were removed
                $form = $model->createForm($entity, $this->get('form.factory'), $action, ['update_select' => $updateSelect]);
            }
        } else {
            //lock the entity
            $model->lockEntity($entity);

            //clear any modified content
            $session->remove('mautic.emailbuilder.'.$objectId.'.content');

            // Parse tokens into view data
            $tokens = $model->getBuilderComponents($entity, ['tokens', 'visualTokens', 'tokenSections']);

            // Set to view content
            $template = $entity->getTemplate();
            if (empty($template)) {
                $content = $entity->getCustomHtml();
                $form['customHtml']->setData($content);
            }
        }

        $assets         = $form['assetAttachments']->getData();
        $attachmentSize = $this->getModel('asset')->getTotalFilesize($assets);

        $slotTypes   = $model->getBuilderComponents($entity, 'slotTypes');
        $sectionForm = $this->get('form.factory')->create('builder_section');

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'               => $this->setFormTheme($form, 'MauticEmailBundle:Email:form.html.php', 'MauticEmailBundle:FormTheme\Email'),
                    'isVariant'          => $entity->isVariant(true),
                    'tokens'             => (!empty($tokens)) ? $tokens['tokenSections'] : $model->getBuilderComponents($entity, 'tokenSections'),
                    'slots'              => $this->buildSlotForms($slotTypes),
                    'themes'             => $this->factory->getInstalledThemes('email', true),
                    'email'              => $entity,
                    'forceTypeSelection' => $forceTypeSelection,
                    'attachmentSize'     => $attachmentSize,
                    'builderAssets'      => trim(preg_replace('/\s+/', ' ', $this->getAssetsForBuilder())), // strip new lines
                    'sectionForm'        => $sectionForm->createView(),
                ],
                'contentTemplate' => 'MauticEmailBundle:Email:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_email_index',
                    'mauticContent' => 'email',
                    'updateSelect'  => InputHelper::clean($this->request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'mautic_email_action',
                        [
                            'objectAction' => 'edit',
                            'objectId'     => $entity->getId(),
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * Clone an entity.
     *
     * @param $objectId
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function cloneAction($objectId)
    {
        $model = $this->getModel('email');
        /** @var Email $entity */
        $entity = $model->getEntity($objectId);

        if ($entity != null) {
            if (!$this->get('mautic.security')->isGranted('email:emails:create')
                || !$this->get('mautic.security')->hasEntityAccess(
                    'email:emails:viewown',
                    'email:emails:viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }

            $entity      = clone $entity;
            $session     = $this->get('session');
            $contentName = 'mautic.emailbuilder.'.$entity->getSessionId().'.content';

            $session->set($contentName, $entity->getCustomHtml());
        }

        return $this->newAction($entity);
    }

    /**
     * Deletes the entity.
     *
     * @param   $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction($objectId)
    {
        $page      = $this->get('session')->get('mautic.email.page', 1);
        $returnUrl = $this->generateUrl('mautic_email_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticEmailBundle:Email:index',
            'passthroughVars' => [
                'activeLink'    => 'mautic_email_index',
                'mauticContent' => 'email',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model  = $this->getModel('email');
            $entity = $model->getEntity($objectId);

            if ($entity === null) {
                $flashes[] = [
                    'type'    => 'error',
                    'msg'     => 'mautic.email.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->get('mautic.security')->hasEntityAccess(
                'email:emails:deleteown',
                'email:emails:deleteother',
                $entity->getCreatedBy()
            )
            ) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'email');
            }

            $model->deleteEntity($entity);

            $flashes[] = [
                'type'    => 'notice',
                'msg'     => 'mautic.core.notice.deleted',
                'msgVars' => [
                    '%name%' => $entity->getName(),
                    '%id%'   => $objectId,
                ],
            ];
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }

    /**
     * Activate the builder.
     *
     * @param $objectId
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     *
     * @throws \Exception
     * @throws \Mautic\CoreBundle\Exception\FileNotFoundException
     */
    public function builderAction($objectId)
    {
        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model = $this->getModel('email');

        //permission check
        if (strpos($objectId, 'new') !== false) {
            $isNew = true;
            if (!$this->get('mautic.security')->isGranted('email:emails:create')) {
                return $this->accessDenied();
            }
            $entity = $model->getEntity();
            $entity->setSessionId($objectId);
        } else {
            $isNew  = false;
            $entity = $model->getEntity($objectId);
            if ($entity == null
                || !$this->get('mautic.security')->hasEntityAccess(
                    'email:emails:viewown',
                    'email:emails:viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }
        }

        $template = InputHelper::clean($this->request->query->get('template'));
        $slots    = $this->factory->getTheme($template)->getSlots('email');

        //merge any existing changes
        $newContent = $this->get('session')->get('mautic.emailbuilder.'.$objectId.'.content', []);
        $content    = $entity->getContent();

        if (is_array($newContent)) {
            $content = array_merge($content, $newContent);
            // Update the content for processSlots
            $entity->setContent($content);
        }

        // Replace short codes to emoji
        $content = EmojiHelper::toEmoji($content, 'short');

        $this->processSlots($slots, $entity);

        $logicalName = $this->factory->getHelper('theme')->checkForTwigTemplate(':'.$template.':email.html.php');

        return $this->render(
            $logicalName,
            [
                'isNew'    => $isNew,
                'slots'    => $slots,
                'content'  => $content,
                'email'    => $entity,
                'template' => $template,
                'basePath' => $this->request->getBasePath(),
            ]
        );
    }

    /**
     * Create an AB test.
     *
     * @param $objectId
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function abtestAction($objectId)
    {
        $model  = $this->getModel('email');
        $entity = $model->getEntity($objectId);

        if ($entity != null) {
            $parent = $entity->getVariantParent();

            if ($parent || !$this->get('mautic.security')->isGranted('email:emails:create')
                || !$this->get('mautic.security')->hasEntityAccess(
                    'email:emails:viewown',
                    'email:emails:viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }

            // Note this since it's cleared on __clone()
            $emailType = $entity->getEmailType();

            $clone = clone $entity;

            //reset
            $clone->clearStats();
            $clone->setSentCount(0);
            $clone->setRevision(0);
            $clone->setVariantSentCount(0);
            $clone->setVariantStartDate(null);
            $clone->setIsPublished(false);
            $clone->setEmailType($emailType);
            $clone->setVariantParent($entity);
        }

        return $this->newAction($clone);
    }

    /**
     * Make the variant the main.
     *
     * @param $objectId
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function winnerAction($objectId)
    {
        //todo - add confirmation to button click
        $page      = $this->get('session')->get('mautic.email', 1);
        $returnUrl = $this->generateUrl('mautic_email_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticEmailBundle:Page:index',
            'passthroughVars' => [
                'activeLink'    => 'mautic_email_index',
                'mauticContent' => 'page',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model  = $this->getModel('email');
            $entity = $model->getEntity($objectId);

            if ($entity === null) {
                $flashes[] = [
                    'type'    => 'error',
                    'msg'     => 'mautic.email.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->get('mautic.security')->hasEntityAccess(
                'email:emails:editown',
                'email:emails:editother',
                $entity->getCreatedBy()
            )
            ) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'email');
            }

            $model->convertVariant($entity);

            $flashes[] = [
                'type'    => 'notice',
                'msg'     => 'mautic.email.notice.activated',
                'msgVars' => [
                    '%name%' => $entity->getName(),
                    '%id%'   => $objectId,
                ],
            ];

            $postActionVars['viewParameters'] = [
                'objectAction' => 'view',
                'objectId'     => $objectId,
            ];
            $postActionVars['returnUrl']       = $this->generateUrl('mautic_page_action', $postActionVars['viewParameters']);
            $postActionVars['contentTemplate'] = 'MauticEmailBundle:Page:view';
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }

    /**
     * Manually sends emails.
     *
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function sendAction($objectId)
    {
        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model   = $this->getModel('email');
        $entity  = $model->getEntity($objectId);
        $session = $this->get('session');
        $page    = $session->get('mautic.email.page', 1);

        //set the return URL
        $returnUrl = $this->generateUrl('mautic_email_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticEmailBundle:Email:index',
            'passthroughVars' => [
                'activeLink'    => 'mautic_email_index',
                'mauticContent' => 'email',
            ],
        ];

        //not found
        if ($entity === null) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.email.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        } elseif ($entity->getEmailType() == 'template'
            || !$this->get('mautic.security')->hasEntityAccess(
                'email:emails:viewown',
                'email:emails:viewother',
                $entity->getCreatedBy()
            )
        ) {
            return $this->accessDenied();
        }

        // Check that the parent is getting sent
        if ($variantParent = $entity->getVariantParent()) {
            return $this->redirect($this->generateUrl('mautic_email_action',
                [
                    'objectAction' => 'send',
                    'objectId'     => $variantParent->getId(),
                ]
            ));
        }
        if ($translationParent = $entity->getTranslationParent()) {
            return $this->redirect($this->generateUrl('mautic_email_action',
                [
                    'objectAction' => 'send',
                    'objectId'     => $translationParent->getId(),
                ]
            ));
        }

        // Make sure email and category are published
        $category     = $entity->getCategory();
        $catPublished = (!empty($category)) ? $category->isPublished() : true;
        $published    = $entity->isPublished();

        if (!$catPublished || !$published) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.email.error.send',
                                'msgVars' => ['%name%' => $entity->getName()],
                            ],
                        ],
                    ]
                )
            );
        }

        $action   = $this->generateUrl('mautic_email_action', ['objectAction' => 'send', 'objectId' => $objectId]);
        $pending  = $model->getPendingLeads($entity, null, true);
        $form     = $this->get('form.factory')->create('batch_send', [], ['action' => $action]);
        $complete = $this->request->request->get('complete', false);

        if ($this->request->getMethod() == 'POST' && ($complete || $this->isFormValid($form))) {
            if (!$complete) {
                $progress = [0, (int) $pending];
                $session->set('mautic.email.send.progress', $progress);

                $stats = ['sent' => 0, 'failed' => 0, 'failedRecipients' => []];
                $session->set('mautic.email.send.stats', $stats);

                $status     = 'inprogress';
                $batchlimit = $form['batchlimit']->getData();

                $session->set('mautic.email.send.active', false);
            } else {
                $stats      = $session->get('mautic.email.send.stats');
                $progress   = $session->get('mautic.email.send.progress');
                $batchlimit = 100;
                $status     = (!empty($stats['failed'])) ? 'with_errors' : 'success';
            }

            $contentTemplate = 'MauticEmailBundle:Send:progress.html.php';
            $viewParameters  = [
                'progress'   => $progress,
                'stats'      => $stats,
                'status'     => $status,
                'email'      => $entity,
                'batchlimit' => $batchlimit,
            ];
        } else {
            //process and send
            $contentTemplate = 'MauticEmailBundle:Send:form.html.php';
            $viewParameters  = [
                'form'    => $form->createView(),
                'email'   => $entity,
                'pending' => $pending,
            ];
        }

        return $this->delegateView(
            [
                'viewParameters'  => $viewParameters,
                'contentTemplate' => $contentTemplate,
                'passthroughVars' => [
                    'mauticContent' => 'emailSend',
                    'route'         => $action,
                ],
            ]
        );
    }

    /**
     * Send example email to current user.
     *
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function exampleAction($objectId)
    {
        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model  = $this->getModel('email');
        $entity = $model->getEntity($objectId);

        //not found or not allowed
        if ($entity === null
            || (!$this->get('mautic.security')->hasEntityAccess(
                'email:emails:viewown',
                'email:emails:viewother',
                $entity->getCreatedBy()
            ))
        ) {
            return $this->viewAction($objectId);
        }

        // Prepare a fake lead
        /** @var \Mautic\LeadBundle\Model\FieldModel $fieldModel */
        $fieldModel = $this->getModel('lead.field');
        $fields     = $fieldModel->getFieldList(false, false);
        array_walk(
            $fields,
            function (&$field) {
                $field = "[$field]";
            }
        );
        $fields['id'] = 0;

        // Send to current user
        $user  = $this->user;
        $users = [
            [
                'id'        => $user->getId(),
                'firstname' => $user->getFirstName(),
                'lastname'  => $user->getLastName(),
                'email'     => $user->getEmail(),
            ],
        ];

        // Send to current user
        $errors = $model->sendEmailToUser($entity, $users, $fields, [], [], false);
        if (count($errors)) {
            $this->addFlash(implode('; ', $errors));
        } else {
            $this->addFlash('mautic.email.notice.test_sent.success');
        }

        return $this->viewAction($objectId);
    }

    /**
     * Deletes a group of entities.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        $page      = $this->get('session')->get('mautic.email.page', 1);
        $returnUrl = $this->generateUrl('mautic_email_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticEmailBundle:Email:index',
            'passthroughVars' => [
                'activeLink'    => '#mautic_email_index',
                'mauticContent' => 'email',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model = $this->getModel('email');
            $ids   = json_decode($this->request->query->get('ids', '{}'));

            $deleteIds = [];

            // Loop over the IDs to perform access checks pre-delete
            foreach ($ids as $objectId) {
                $entity = $model->getEntity($objectId);

                if ($entity === null) {
                    $flashes[] = [
                        'type'    => 'error',
                        'msg'     => 'mautic.email.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->get('mautic.security')->hasEntityAccess(
                    'email:emails:viewown',
                    'email:emails:viewother',
                    $entity->getCreatedBy()
                )
                ) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, 'email', true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            // Delete everything we are able to
            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => 'mautic.email.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities),
                    ],
                ];
            }
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                [
                    'flashes' => $flashes,
                ]
            )
        );
    }

    /**
     * PreProcess page slots for public view.
     *
     * @param array $slots
     * @param Email $entity
     */
    private function processSlots($slots, $entity)
    {
        /** @var \Mautic\CoreBundle\Templating\Helper\SlotsHelper $slotsHelper */
        $slotsHelper = $this->factory->getHelper('template.slots');
        /** @var \Mautic\CoreBundle\Templating\Helper\TranslatorHelper $translatorHelper */
        $translatorHelper = $this->factory->getHelper('template.translator');

        $content = $entity->getContent();

        //Set the slots
        foreach ($slots as $slot => $slotConfig) {
            //support previous format where email slots are not defined with config array
            if (is_numeric($slot)) {
                $slot       = $slotConfig;
                $slotConfig = [];
            }

            $value       = isset($content[$slot]) ? $content[$slot] : '';
            $placeholder = isset($slotConfig['placeholder']) ? $slotConfig['placeholder'] : 'mautic.page.builder.addcontent';
            $slotsHelper->set($slot, "<div data-slot=\"text\" id=\"slot-{$slot}\">{$value}</div>");
        }

        //add builder toolbar
        $slotsHelper->start('builder'); ?>
        <input type="hidden" id="builder_entity_id" value="<?php echo $entity->getSessionId(); ?>"/>
        <?php
        $slotsHelper->stop();
    }

    /**
     * Get assets for builder.
     */
    private function getAssetsForBuilder()
    {
        /** @var \Mautic\CoreBundle\Templating\Helper\AssetsHelper $assetsHelper */
        $assetsHelper = $this->factory->getHelper('template.assets');
        /** @var \Symfony\Bundle\FrameworkBundle\Templating\Helper\RouterHelper $routerHelper */
        $routerHelper = $this->factory->getHelper('template.router');

        $existingAssets = $assetsHelper->getAssets();

        $assetsHelper->addScriptDeclaration("var mauticBasePath    = '".$this->request->getBasePath()."';");
        $assetsHelper->addScriptDeclaration("var mauticAjaxUrl     = '".$routerHelper->generate('mautic_core_ajax')."';");
        $assetsHelper->addScriptDeclaration("var mauticBaseUrl     = '".$routerHelper->generate('mautic_base_index')."';");
        $assetsHelper->addScriptDeclaration("var mauticAssetPrefix = '".$assetsHelper->getAssetPrefix(true)."';");
        $assetsHelper->addCustomDeclaration($assetsHelper->getSystemScripts(true, true));
        $assetsHelper->addStylesheet('app/bundles/CoreBundle/Assets/css/libraries/builder.css');

        // Use the assetsHelper to auto-build the asset html
        $builderAssets = $assetsHelper->getHeadDeclarations();

        // Reset the assets helper to what it was before.
        $assetsHelper->setAssets($existingAssets);

        return $builderAssets;
    }

    /**
     * @param $slotTypes
     *
     * @return mixed
     */
    private function buildSlotForms($slotTypes)
    {
        foreach ($slotTypes as &$slotType) {
            if (isset($slotType['form'])) {
                $slotForm         = $this->get('form.factory')->create($slotType['form']);
                $slotType['form'] = $slotForm->createView();
            }
        }

        return $slotTypes;
    }
}
