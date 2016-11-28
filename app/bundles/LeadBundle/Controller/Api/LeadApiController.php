<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Controller\Api;

use FOS\RestBundle\Util\Codes;
use JMS\Serializer\SerializationContext;
use Mautic\ApiBundle\Controller\CommonApiController;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\FieldModel;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class LeadApiController.
 */
class LeadApiController extends CommonApiController
{
    public function initialize(FilterControllerEvent $event)
    {
        parent::initialize($event);
        $this->model            = $this->getModel('lead.lead');
        $this->entityClass      = 'Mautic\LeadBundle\Entity\Lead';
        $this->entityNameOne    = 'contact';
        $this->entityNameMulti  = 'contacts';
        $this->permissionBase   = 'lead:leads';
        $this->serializerGroups = ['leadDetails', 'userList', 'publishDetails', 'ipAddress', 'tagList'];
    }

    /**
     * Creates a new lead or edits if one is found with same email.  You should make a call to /api/leads/list/fields in order to get a list of custom fields that will be accepted. The key should be the alias of the custom field. You can also pass in a ipAddress parameter if the IP of the lead is different than that of the originating request.
     */
    public function newEntityAction()
    {
        // Check for an email to see if the lead already exists
        $parameters = $this->request->request->all();

        $uniqueLeadFields    = $this->getModel('lead.field')->getUniqueIdentiferFields();
        $uniqueLeadFieldData = [];

        foreach ($parameters as $k => $v) {
            if (array_key_exists($k, $uniqueLeadFields) && !empty($v)) {
                $uniqueLeadFieldData[$k] = $v;
            }
        }

        if (count($uniqueLeadFieldData)) {
            if (count($uniqueLeadFieldData)) {
                $existingLeads = $this->get('doctrine.orm.entity_manager')->getRepository('MauticLeadBundle:Lead')->getLeadsByUniqueFields($uniqueLeadFieldData);

                if (!empty($existingLeads)) {
                    // Lead found so edit rather than create a new one

                    return parent::editEntityAction($existingLeads[0]->getId());
                }
            }
        }

        return parent::newEntityAction();
    }

    /**
     * @return array
     */
    protected function getEntityFormOptions()
    {
        $fields = $this->getModel('lead.field')->getEntities(
            [
                'force' => [
                    [
                        'column' => 'f.isPublished',
                        'expr'   => 'eq',
                        'value'  => true,
                    ],
                    [
                        'column' => 'f.object',
                        'expr'   => 'eq',
                        'value'  => 'lead',
                    ],
                ],
                'hydration_mode' => 'HYDRATE_ARRAY',
            ]
        );

        return ['fields' => $fields];
    }

    /**
     * Obtains a list of users for lead owner edits.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getOwnersAction()
    {
        if (!$this->get('mautic.security')->isGranted(
            ['lead:leads:create', 'lead:leads:editown', 'lead:leads:editother'],
            'MATCH_ONE'
        )
        ) {
            return $this->accessDenied();
        }

        $filter  = $this->request->query->get('filter', null);
        $limit   = $this->request->query->get('limit', null);
        $start   = $this->request->query->get('start', null);
        $users   = $this->model->getLookupResults('user', $filter, $limit, $start);
        $view    = $this->view($users, Codes::HTTP_OK);
        $context = SerializationContext::create()->setGroups(['userList']);
        $view->setSerializationContext($context);

        return $this->handleView($view);
    }

    /**
     * Obtains a list of custom fields.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getFieldsAction()
    {
        if (!$this->get('mautic.security')->isGranted(['lead:leads:editown', 'lead:leads:editother'], 'MATCH_ONE')) {
            return $this->accessDenied();
        }

        $fields = $this->getModel('lead.field')->getEntities(
            [
                'filter' => [
                    'force' => [
                        [
                            'column' => 'f.isPublished',
                            'expr'   => 'eq',
                            'value'  => true,
                            'object' => 'lead',
                        ],
                    ],
                ],
            ]
        );

        $view    = $this->view($fields, Codes::HTTP_OK);
        $context = SerializationContext::create()->setGroups(['leadFieldList']);
        $view->setSerializationContext($context);

        return $this->handleView($view);
    }

    /**
     * Obtains a list of notes on a specific lead.
     *
     * @param $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getNotesAction($id)
    {
        $entity = $this->model->getEntity($id);
        if ($entity !== null) {
            if (!$this->get('mautic.security')->hasEntityAccess('lead:leads:viewown', 'lead:leads:viewother', $entity->getPermissionUser())) {
                return $this->accessDenied();
            }

            $results = $this->getModel('lead.note')->getEntities(
                [
                    'start'  => $this->request->query->get('start', 0),
                    'limit'  => $this->request->query->get('limit', $this->coreParametersHelper->getParameter('default_pagelimit')),
                    'filter' => [
                        'string' => $this->request->query->get('search', ''),
                        'force'  => [
                            [
                                'column' => 'n.lead',
                                'expr'   => 'eq',
                                'value'  => $entity,
                            ],
                        ],
                    ],
                    'orderBy'    => $this->request->query->get('orderBy', 'n.dateAdded'),
                    'orderByDir' => $this->request->query->get('orderByDir', 'DESC'),
                ]
            );

            list($notes, $count) = $this->prepareEntitiesForView($results);

            $view = $this->view(
                [
                    'total' => $count,
                    'notes' => $notes,
                ],
                Codes::HTTP_OK
            );

            $context = SerializationContext::create()->setGroups(['leadNoteDetails']);
            $view->setSerializationContext($context);

            return $this->handleView($view);
        }

        return $this->notFound();
    }

    /**
     * Obtains a list of lead lists the lead is in.
     *
     * @param $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getListsAction($id)
    {
        $entity = $this->model->getEntity($id);
        if ($entity !== null) {
            if (!$this->get('mautic.security')->hasEntityAccess('lead:leads:viewown', 'lead:leads:viewother', $entity->getPermissionUser())) {
                return $this->accessDenied();
            }

            $lists = $this->model->getLists($entity, true, true);

            foreach ($lists as &$l) {
                unset($l['leads'][0]['leadlist_id']);
                unset($l['leads'][0]['lead_id']);

                $l = array_merge($l, $l['leads'][0]);

                unset($l['leads']);
            }

            $view = $this->view(
                [
                    'total' => count($lists),
                    'lists' => $lists,
                ],
                Codes::HTTP_OK
            );

            return $this->handleView($view);
        }

        return $this->notFound();
    }

    /**
     * Obtains a list of campaigns the lead is part of.
     *
     * @param $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getCampaignsAction($id)
    {
        $entity = $this->model->getEntity($id);
        if ($entity !== null) {
            if (!$this->get('mautic.security')->hasEntityAccess('lead:leads:viewown', 'lead:leads:viewother', $entity->getPermissionUser())) {
                return $this->accessDenied();
            }

            /** @var \Mautic\CampaignBundle\Model\CampaignModel $campaignModel */
            $campaignModel = $this->getModel('campaign');
            $campaigns     = $campaignModel->getLeadCampaigns($entity, true);

            foreach ($campaigns as &$c) {
                if (!empty($c['lists'])) {
                    $c['listMembership'] = array_keys($c['lists']);
                    unset($c['lists']);
                }

                unset($c['leads'][0]['campaign_id']);
                unset($c['leads'][0]['lead_id']);

                $c = array_merge($c, $c['leads'][0]);

                unset($c['leads']);
            }

            $view = $this->view(
                [
                    'total'     => count($campaigns),
                    'campaigns' => $campaigns,
                ],
                Codes::HTTP_OK
            );

            return $this->handleView($view);
        }

        return $this->notFound();
    }

    /**
     * Obtains a list of contact events.
     *
     * @param $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getEventsAction($id)
    {
        $entity = $this->model->getEntity($id);

        if ($entity === null) {
            return $this->notFound();
        }

        if (!$this->checkEntityAccess($entity, 'view')) {
            return $this->accessDenied();
        }

        $filters = InputHelper::clean($this->request->get('filters', []));

        if (!isset($filters['search'])) {
            $filters['search'] = '';
        }

        if (!isset($filters['includeEvents'])) {
            $filters['includeEvents'] = [];
        }

        if (!isset($filters['excludeEvents'])) {
            $filters['excludeEvents'] = [];
        }

        $order = InputHelper::clean($this->request->get('order', [
            'timestamp',
            'DESC',
        ]));
        $page        = (int) $this->request->get('page', 1);
        $engagements = $this->model->getEngagements($entity, $filters, $order, $page);
        $view        = $this->view($engagements);

        return $this->handleView($view);
    }

    /**
     * Adds a DNC to the contact.
     *
     * @param int    $id
     * @param string $channel
     */
    public function addDncAction($id, $channel)
    {
        $entity = $this->model->getEntity((int) $id);

        if ($entity === null) {
            return $this->notFound();
        }

        if (!$this->checkEntityAccess($entity, 'edit')) {
            return $this->accessDenied();
        }

        $channelId = (int) $this->request->request->get('channelId');
        $reason    = (int) $this->request->request->get('reason');
        $comments  = InputHelper::clean($this->request->request->get('comments'));
        $result    = $this->model->addDncForLead($entity, $channel, $comments, $reason);
        $view      = $this->view([$this->entityNameOne => $entity]);

        if ($result === false) {
            return $this->badRequest();
        }

        return $this->handleView($view);
    }

    /**
     * Removes a DNC from the contact.
     *
     * @param int $id
     * @param int $channel
     */
    public function removeDncAction($id, $channel)
    {
        $entity = $this->model->getEntity((int) $id);

        if ($entity === null) {
            return $this->notFound();
        }

        if (!$this->checkEntityAccess($entity, 'edit')) {
            return $this->accessDenied();
        }

        $result = $this->model->removeDncForLead($entity, $channel);
        $view   = $this->view([$this->entityNameOne => $entity]);

        if ($result === false) {
            return $this->badRequest();
        }

        return $this->handleView($view);
    }

    /**
     * {@inheritdoc}
     *
     * @param \Mautic\LeadBundle\Entity\Lead &$entity
     * @param                                $parameters
     * @param                                $form
     * @param string                         $action
     */
    protected function preSaveEntity(&$entity, $form, $parameters, $action = 'edit')
    {
        //Since the request can be from 3rd party, check for an IP address if included
        if (isset($parameters['ipAddress'])) {
            $ip = $parameters['ipAddress'];
            unset($parameters['ipAddress']);

            $ipAddress = $this->factory->getIpAddress($ip);

            if (!$entity->getIpAddresses()->contains($ipAddress)) {
                $entity->addIpAddress($ipAddress);
            }
        }

        // Check for tag string
        if (isset($parameters['tags'])) {
            $this->model->modifyTags($entity, $parameters['tags']);
            unset($parameters['tags']);
        }

        if (isset($parameters['companies'])) {
            $this->model->modifyCompanies($entity, $parameters['companies']);
            unset($parameters['companies']);
        }

        // Contact parameters which can be updated apart form contact fields
        $contactParams = ['points', 'color', 'owner'];

        foreach ($contactParams as $contactParam) {
            if (isset($parameters[$contactParam])) {
                $entity->setPoints($parameters[$contactParam]);
                unset($parameters[$contactParam]);
            }
        }

        // Check for lastActive date
        if (isset($parameters['lastActive'])) {
            $lastActive = new DateTimeHelper($parameters['lastActive']);
            $entity->setLastActive($lastActive->getDateTime());
        }

        if (!empty($parameters['doNotContact']) && is_array($parameters['doNotContact'])) {
            foreach ($parameters['doNotContact'] as $dnc) {
                $channel  = !empty($dnc['channel']) ? $dnc['channel'] : 'email';
                $comments = !empty($dnc['comments']) ? $dnc['comments'] : '';
                $reason   = !empty($dnc['reason']) ? $dnc['reason'] : DoNotContact::MANUAL;
                $this->model->addDncForLead($entity, $channel, $comments, $reason, false);
            }
        }

        //set the custom field values

        //pull the data from the form in order to apply the form's formatting
        foreach ($form as $f) {
            $parameters[$f->getName()] = $f->getData();
        }

        $this->model->setFieldValues($entity, $parameters, true);
    }

    /**
     * Remove IpAddress and lastActive as it'll be handled outside the form.
     *
     * @param $parameters
     * @param Lead $entity
     * @param $action
     *
     * @return mixed|void
     */
    protected function prepareParametersForBinding($parameters, $entity, $action)
    {
        unset($parameters['ipAddress'], $parameters['lastActive'], $parameters['tags']);

        if (in_array($this->request->getMethod(), ['POST', 'PUT'])) {
            // If a new contact or PUT update (complete representation of the objectd), set empty fields to field defaults if the parameter
            // is not defined in the request

            /** @var FieldModel $fieldModel */
            $fieldModel = $this->getModel('lead.field');
            $fields     = $fieldModel->getFieldListWithProperties();

            foreach ($fields as $alias => $field) {
                // Set the default value if the parameter is not included in the request, there is no value for the given entity, and a default is defined
                $currentValue = $entity->getFieldValue($alias);
                if (!isset($parameters[$alias]) && ('' === $currentValue || null == $currentValue) && '' !== $field['defaultValue'] && null !== $field['defaultValue']) {
                    $parameters[$alias] = $field['defaultValue'];
                }
            }
        }

        return $parameters;
    }

    /**
     * Flatten fields into an 'all' key for dev convenience.
     *
     * @param        $entity
     * @param string $action
     */
    protected function preSerializeEntity(&$entity, $action = 'view')
    {
        if ($entity instanceof Lead) {
            $fields['all'] = $entity->getProfileFields();
            $entity->setFields($fields);
        }
    }
}
