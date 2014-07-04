<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

//@todo - add support to editing more than one form at a time (i.e. opened in different tabs)

namespace Mautic\FormBundle\Controller;

use Mautic\CoreBundle\Controller\FormController as CommonFormController;
use Mautic\FormBundle\Helper\FormFieldHelper;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class FormController extends CommonFormController
{

    /**
     * @param int    $page
     * @param string $view
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($page = 1, $view = 'list', $activeForm = false)
    {
        //set some permissions
        $permissions = $this->get('mautic.security')->isGranted(array(
            'form:forms:viewown',
            'form:forms:viewother',
            'form:forms:create',
            'form:forms:editown',
            'form:forms:editother',
            'form:forms:deleteown',
            'form:forms:deleteother',
            'form:forms:publishown',
            'form:forms:publishother'

        ), "RETURN_ARRAY");

        if (!$permissions['form:forms:viewown'] && !$permissions['form:forms:viewother']) {
            return $this->accessDenied();
        }

        //set limits
        $limit = $this->get('session')->get('mautic.form.limit', $this->container->getParameter('mautic.default_pagelimit'));
        $start = ($page === 1) ? 0 : (($page-1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $this->get('session')->get('mautic.form.filter', ''));
        $this->get('session')->set('mautic.form.filter', $search);

        $filter      = array('string' => $search, 'force' => array());

        if (!$permissions['form:forms:viewother']) {
            $filter['force'] =
                array('column' => 'f.createdBy', 'expr' => 'eq', 'value' => $this->get('mautic.factory')->getUser());
        }

        $forms = $this->get('mautic.factory')->getModel('form.form')->getEntities(
            array(
                'start'      => $start,
                'limit'      => $limit,
                'filter'     => $filter,
                'orderByDir' => "DESC",
                'getTotalCount' => true
            ));

        $count = $forms['totalCount'];
        unset($forms['totalCount']);
        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current page so redirect to the last page
            if ($count === 1) {
                $lastPage = 1;
            } else {
                $lastPage = (floor($limit / $count)) ? : 1;
            }
            $this->get('session')->set('mautic.form.page', $lastPage);
            $returnUrl   = $this->generateUrl('mautic_form_index', array('page' => $lastPage));

            return $this->postActionRedirect(array(
                'returnUrl'       => $returnUrl,
                'viewParameters'  => array('page' => $lastPage),
                'contentTemplate' => 'MauticFormBundle:Form:index',
                'passthroughVars' => array(
                    'activeLink'    => '#mautic_form_index',
                    'mauticContent' => 'form'
                )
            ));
        }

        //set what page currently on so that we can return here after form submission/cancellation
        $this->get('session')->set('mautic.form.page', $page);

        //get active form
        if ($activeForm === false)
            $activeForm = ($count) ? $forms[0] : false;

        $tmpl = $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index';

        $parameters = array(
            'searchValue' => $search,
            'items'       => $forms,
            'page'        => $page,
            'limit'       => $limit,
            'totalCount'  => $count,
            'permissions' => $permissions,
            'activeForm'  => $activeForm,
            'tmpl'        => $tmpl,
            'security'    => $this->get('mautic.security')
        );

        $vars = array(
            'activeLink'    => '#mautic_form_index',
            'mauticContent' => 'form',
            'route'         => $this->generateUrl('mautic_form_index', array('page' => $page))
        );

        if ($tmpl == "index") {
            switch ($view) {
                case 'list':
                    $template = 'MauticFormBundle:Form:details.html.php';
                    break;
                case 'view':
                    $template = 'MauticFormBundle:Form:details.html.php';
                    $vars['route'] = $this->generateUrl('mautic_form_action', array(
                        'objectAction' => 'view',
                        'objectId'     => $activeForm->getId())
                    );
                    break;
            }
        } elseif ($tmpl == 'form') {
            $template       = 'MauticFormBundle:Form:details.html.php';
            $vars['target'] = '.bundle-main-inner-wrapper';
            $vars['route']  = $this->generateUrl('mautic_form_action', array(
                    'objectAction' => 'view',
                    'objectId'     => $activeForm->getId())
            );
        } else {
            $template      = 'MauticFormBundle:Form:list.html.php';
            if ($tmpl == 'list') {
                $vars['target'] = '.bundle-list';
            }
            $parameters['dateFormat'] = $this->get('mautic.factory')->getParam('date_format_full');
        }

        return $this->delegateView(array(
            'viewParameters'  => $parameters,
            'contentTemplate' => $template,
            'passthroughVars' => $vars
        ));
    }

    /**
     * Loads a specific form into the detailed panel
     *
     * @param $objectId
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        $activeForm  = $this->get('mautic.factory')->getModel('form.form')->getEntity($objectId);
        //set the page we came from
        $page    = $this->get('session')->get('mautic.form.page', 1);

        if ($activeForm === null) {
            //set the return URL
            $returnUrl  = $this->generateUrl('mautic_form_index', array('page' => $page));

            return $this->postActionRedirect(array(
                'returnUrl'       => $returnUrl,
                'viewParameters'  => array('page' => $page),
                'contentTemplate' => 'MauticFormBundle:Form:index',
                'passthroughVars' => array(
                    'activeLink'    => '#mautic_form_index',
                    'mauticContent' => 'form'
                ),
                'flashes'         =>array(
                    array(
                        'type' => 'error',
                        'msg'  => 'mautic.form.error.notfound',
                        'msgVars' => array('%id%' => $objectId)
                    )
                )
            ));
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'form:forms:viewown', 'form:forms:viewother', $activeForm->getCreatedBy()
        )) {
            return $this->accessDenied();
        }

        return $this->indexAction($page, 'view', $activeForm);
    }

    /**
     * Generates new form and processes post data
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction ()
    {
        $model   = $this->get('mautic.factory')->getModel('form.form');
        $entity  = $model->getEntity();
        $session = $this->get('session');

        if (!$this->get('mautic.security')->isGranted('form:forms:create')) {
            return $this->accessDenied();
        }

        //set the page we came from
        $page   = $this->get('session')->get('mautic.form.page', 1);

        //set added/updated fields
        $formFields    = $session->get('mautic.formfields.add', array());
        $deletedFields = $session->get('mautic.formfields.remove', array());

        //set added/updated actions
        $formActions    = $session->get('mautic.formactions.add', array());
        $deletedActions = $session->get('mautic.formactions.remove', array());

        $action = $this->generateUrl('mautic_form_action', array('objectAction' => 'new'));
        $form   = $model->createForm($entity, $this->get('form.factory'), $action);

        ///Check for a submitted form and process it
        if ($this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    $submits = $session->get('mautic.formfields.submits', array());

                    //only save fields that are not to be deleted
                    $fields   = array_diff_key($formFields, array_flip($deletedFields));
                    //only save actions that are not to be deleted
                    $actions  = array_diff_key($formActions, array_flip($deletedActions));

                    //make sure that at least one field is selected
                    if (empty($fields)) {
                        //set the error
                        $form->addError(new FormError(
                            $this->get('translator')->trans('mautic.form.form.fields.notempty', array(), 'validators')
                        ));
                        $valid = false;
                    } elseif (empty($submits)) {
                        //set the error
                        $form->addError(new FormError(
                            $this->get('translator')->trans('mautic.form.form.submits.notempty', array(), 'validators')
                        ));
                        $valid = false;
                    } else {
                        $model->setFields($entity, $fields);
                        $model->setActions($entity, $actions);

                        //form is valid so process the data
                        $model->saveEntity($entity);

                        $this->request->getSession()->getFlashBag()->add(
                            'notice',
                            $this->get('translator')->trans('mautic.form.notice.created', array(
                                '%name%' => $entity->getName(),
                                '%url%'          => $this->generateUrl('mautic_form_action', array(
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId()
                                ))
                            ), 'flashes')
                        );

                        if ($form->get('buttons')->get('save')->isClicked()) {
                            $viewParameters = array(
                                'objectAction' => 'view',
                                'objectId'     => $entity->getId()
                            );
                            $returnUrl      = $this->generateUrl('mautic_form_action', $viewParameters);
                            $template       = 'MauticFormBundle:Form:view';
                        } else {
                            //return edit view so that all the session stuff is loaded
                            return $this->editAction($entity->getId(), true);
                        }
                    }
                }
            } else {
                $viewParameters  = array('page' => $page);
                $returnUrl = $this->generateUrl('mautic_form_index', $viewParameters);
                $template  = 'MauticFormBundle:Form:index';
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                //clear temporary fields
                $this->clearSessionComponents();

                return $this->postActionRedirect(array(
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => $viewParameters,
                    'contentTemplate' => $template,
                    'passthroughVars' => array(
                        'activeLink'    => '#mautic_form_index',
                        'mauticContent' => 'form'
                    )
                ));
            }
        } else {
            //clear out existing fields in case the form was refreshed, browser closed, etc
            $this->clearSessionComponents();
        }

        //fire the form builder event
        $customComponents = $model->getCustomComponents();

        $fieldHelper = new FormFieldHelper($this->get('translator'));

        return $this->delegateView(array(
            'viewParameters'  => array(
                'fields'         => $fieldHelper->getList($customComponents['fields']),
                'actions'        => $customComponents['actions'],
                'formFields'     => $formFields,
                'formActions'    => $formActions,
                'deletedFields'  => $deletedFields,
                'deletedActions' => $deletedActions,
                'tmpl'           => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                'activeForm'     => $entity,
                'form'           => $form->createView()
            ),
            'contentTemplate' => 'MauticFormBundle:Builder:components.html.php',
            'passthroughVars' => array(
                'activeLink'    => '#mautic_form_index',
                'mauticContent' => 'form',
                'route'         => $this->generateUrl('mautic_form_action', array(
                    'objectAction' => (!empty($valid) ? 'edit' : 'new'), //valid means a new form was applied
                    'objectId'     => $entity->getId())
                )
            )
        ));
    }

    /**
     * Generates edit form and processes post data
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction ($objectId, $ignorePost = false)
    {
        $model      = $this->get('mautic.factory')->getModel('form.form');
        $entity     = $model->getEntity($objectId);
        $session    = $this->get('session');
        $cleanSlate = true;
        //set the page we came from
        $page    = $this->get('session')->get('mautic.form.page', 1);

        //set the return URL
        $returnUrl  = $this->generateUrl('mautic_form_index', array('page' => $page));

        $postActionVars = array(
            'returnUrl'       => $returnUrl,
            'viewParameters'  => array('page' => $page),
            'contentTemplate' => 'MauticFormBundle:Form:index',
            'passthroughVars' => array(
                'activeLink'    => '#mautic_form_index',
                'mauticContent' => 'form'
            )
        );
        //form not found
        if ($entity === null) {
            return $this->postActionRedirect(
                array_merge($postActionVars, array(
                    'flashes' => array(
                        array(
                            'type' => 'error',
                            'msg'  => 'mautic.form.error.notfound',
                            'msgVars' => array('%id%' => $objectId)
                        )
                    )
                ))
            );
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'form:forms:editown', 'form:forms:editother', $entity->getCreatedBy()
        )) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            //deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'form.form');
        }

        $action = $this->generateUrl('mautic_form_action', array('objectAction' => 'edit', 'objectId' => $objectId));
        $form   = $model->createForm($entity, $this->get('form.factory'), $action);

        ///Check for a submitted form and process it
        if (!$ignorePost && $this->request->getMethod() == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {

                //set added/updated fields
                $formFields     = $session->get('mautic.formfields.add', array());
                $deletedFields  = $session->get('mautic.formfields.remove', array());
                $fields         = array_diff_key($formFields, array_flip($deletedFields));
                //set added/updated actions
                $formActions    = $session->get('mautic.formactions.add', array());
                $deletedActions = $session->get('mautic.formactions.remove', array());
                $actions        = array_diff_key($formActions, array_flip($deletedActions));

                if ($valid = $this->isFormValid($form)) {
                    $submits = $session->get('mautic.formfields.submits', array());

                    //make sure that at least one field is selected
                    if (empty($fields)) {
                        //set the error
                        $form->addError(new FormError(
                            $this->get('translator')->trans('mautic.form.form.fields.notempty', array(), 'validators')
                        ));
                        $valid = false;
                    } elseif (empty($submits)) {
                        //set the error
                        $form->addError(new FormError(
                            $this->get('translator')->trans('mautic.form.form.submits.notempty', array(), 'validators')
                        ));
                        $valid = false;
                    } else {
                        $model->setFields($entity, $fields);
                        $model->setActions($entity, $actions);

                        //form is valid so process the data
                        $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());

                        //delete entities
                        $this->get('mautic.factory')->getModel('form.field')->deleteEntities($deletedFields);
                        $this->get('mautic.factory')->getModel('form.action')->deleteEntities($deletedActions);

                        $this->request->getSession()->getFlashBag()->add(
                            'notice',
                            $this->get('translator')->trans('mautic.form.notice.updated', array(
                                '%name%' => $entity->getName(),
                                '%url%'  => $this->generateUrl('mautic_form_action', array(
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId()
                                ))
                            ), 'flashes')
                        );
                    }
                }
            } else {
                //unlock the entity
                $model->unlockEntity($entity);
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                //remove fields from session
                $this->clearSessionComponents();

                return $this->postActionRedirect(
                    array_merge($postActionVars, array(
                        'returnUrl'       => $this->generateUrl('mautic_form_action', array(
                            'objectAction' => 'view',
                            'objectId'     => $entity->getId()
                        )),
                        'viewParameters'  => array('objectId' => $entity->getId()),
                        'contentTemplate' => 'MauticFormBundle:Form:view'
                    ))
                );
            } elseif ($form->get('buttons')->get('apply')->isClicked()) {
                //rebuild everything to include new ids
                $cleanSlate = true;
            }
        } else {
            $cleanSlate = true;

            //lock the entity
            $model->lockEntity($entity);
        }

        if ($cleanSlate) {
            //clean slate
            $this->clearSessionComponents();

            //load existing fields into session
            $formFields     = array();
            $submits        = array();
            $existingFields = $entity->getFields()->toArray();
            foreach ($existingFields as $f) {
                $id = $f->getId();
                $field = $f->convertToArray();
                unset($field['form']);
                $formFields[$id] = $field;
                if ($field['type'] == 'button') {
                    if ($field['properties']['type'] == 'submit') {
                        $submits[] = $id;
                    }
                }
            }
            $session->set('mautic.formfields.add', $formFields);
            $session->set('mautic.formfields.submits', $submits);
            $deletedFields = array();

            //load existing actions into session
            $formActions     = array();
            $existingActions = $entity->getActions()->toArray();
            foreach ($existingActions as $a) {
                $id     = $a->getId();
                $action = $a->convertToArray();
                unset($action['form']);
                $formActions[$id] = $action;
            }
            $session->set('mautic.formactions.add', $formActions);
            $deletedActions = array();
        }

        $customComponents = $model->getCustomComponents();

        $fieldHelper = new FormFieldHelper($this->get('translator'));

        return $this->delegateView(array(
            'viewParameters'  => array(
                'fields'         => $fieldHelper->getList($customComponents['fields']),
                'actions'        => $customComponents['actions'],
                'formFields'     => $formFields,
                'formActions'    => $formActions,
                'deletedFields'  => $deletedFields,
                'deletedActions' => $deletedActions,
                'tmpl'           => $this->request->isXmlHttpRequest() ? $this->request->get('tmpl', 'index') : 'index',
                'activeForm'     => $entity,
                'form'           => $form->createView()
            ),
            'contentTemplate' => 'MauticFormBundle:Builder:components.html.php',
            'passthroughVars' => array(
                'activeLink'    => '#mautic_form_index',
                'mauticContent' => 'form',
                'route'         => $this->generateUrl('mautic_form_action', array(
                    'objectAction' => 'edit',
                    'objectId'     => $entity->getId())
                )
            )
        ));
    }

    /**
     * Gives a preview of the form
     *
     * @param $objectId
     */
    public function previewAction ($objectId)
    {
        $model = $this->get('mautic.factory')->getModel('form.form');
        $form  = $model->getEntity($objectId);

        if ($form === null) {
            $html =
                '<h1>'.
                    $this->get('translator')->trans('mautic.form.error.notfound', array('%id%' => $objectId), 'flashes') .
                '</h1>';
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'form:forms:editown', 'form:forms:editother', $form->getCreatedBy()
        ))  {
            $html = '<h1>' . $this->get('translator')->trans('mautic.core.accessdenied') . '</h1>';
        } else {
            $html = $form->getCachedHtml();
        }

        $response = new Response();
        $response->setContent('<html><body>'.$html.'</body></html>');
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'text/html');
        return $response;
    }

    /**
     * Deletes the entity
     *
     * @param         $objectId
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction($objectId) {
        $page        = $this->get('session')->get('mautic.form.page', 1);
        $returnUrl   = $this->generateUrl('mautic_form_index', array('page' => $page));
        $flashes     = array();

        $postActionVars = array(
            'returnUrl'       => $returnUrl,
            'viewParameters'  => array('page' => $page),
            'contentTemplate' => 'MauticFormBundle:Form:index',
            'passthroughVars' => array(
                'activeLink'    => '#mautic_form_index',
                'mauticContent' => 'form'
            )
        );

        if ($this->request->getMethod() == 'POST') {
            $model  = $this->get('mautic.factory')->getModel('form.form');
            $entity = $model->getEntity($objectId);

            if ($entity === null) {
                $flashes[] = array(
                    'type'    => 'error',
                    'msg'     => 'mautic.form.error.notfound',
                    'msgVars' => array('%id%' => $objectId)
                );
            } elseif (!$this->get('mautic.security')->hasEntityAccess(
                'form:forms:deleteown', 'form:forms:deleteother', $entity->getCreatedBy()
            )) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'form.form');
            }

            $model->deleteEntity($entity);

            $identifier = $this->get('translator')->trans($entity->getName());
            $flashes[] = array(
                'type' => 'notice',
                'msg'  => 'mautic.form.notice.deleted',
                'msgVars' => array(
                    '%name%' => $identifier,
                    '%id%'   => $objectId
                )
            );
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge($postActionVars, array(
                'flashes' => $flashes
            ))
        );
    }

    /**
     * Clear field and actions from the session
     */
    public function clearSessionComponents()
    {
        $session = $this->get('session');
        $session->remove('mautic.formfields.add');
        $session->remove('mautic.formfields.remove');
        $session->remove('mautic.formfields.submits');

        $session->remove('mautic.formactions.add');
        $session->remove('mautic.formactions.remove');
    }
}