<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Model;

use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\PointsChangeLog;
use Mautic\EmailBundle\Entity\DoNotEmail;
use Mautic\LeadBundle\Entity\Tag;
use Mautic\LeadBundle\Event\LeadChangeEvent;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\Event\LeadMergeEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Helper\Chart\PieChart;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Intl\Intl;

/**
 * Class LeadModel
 * {@inheritdoc}
 * @package Mautic\CoreBundle\Model\FormModel
 */
class LeadModel extends FormModel
{
    private $currentLead       = null;
    private $systemCurrentLead = null;

    /**
     * {@inheritdoc}
     *
     * @return \Mautic\LeadBundle\Entity\LeadRepository
     */
    public function getRepository()
    {
        static $repoSetup;

        $repo = $this->em->getRepository('MauticLeadBundle:Lead');

        if (!$repoSetup) {
            $repoSetup = true;

            //set the point trigger model in order to get the color code for the lead
            $repo->setTriggerModel($this->factory->getModel('point.trigger'));

            /** @var FieldModel $fieldModel */
            $fieldModel = $this->factory->getModel('lead.field');
            $fields     = $fieldModel->getFieldList(true, false);

            $socialFields = (!empty($fields['social'])) ? array_keys($fields['social']) : array();
            $repo->setAvailableSocialFields($socialFields);

            $searchFields = array();
            foreach ($fields as $group => $groupFields) {
                $searchFields = array_merge($searchFields, array_keys($groupFields));
            }
            $repo->setAvailableSearchFields($searchFields);
        }

        return $repo;
    }

    /**
     * Get the tags repository
     *
     * @return \Mautic\LeadBundle\Entity\TagRepository
     */
    public function getTagRepository()
    {
        return $this->em->getRepository('MauticLeadBundle:Tag');
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getPermissionBase()
    {
        return 'lead:leads';
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getNameGetter()
    {
        return "getPrimaryIdentifier";
    }

    /**
     * {@inheritdoc}
     *
     * @param Lead                                $entity
     * @param \Symfony\Component\Form\FormFactory $formFactory
     * @param string|null                         $action
     * @param array                               $options
     *
     * @return \Symfony\Component\Form\Form
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    public function createForm($entity, $formFactory, $action = null, $options = array())
    {
        if (!$entity instanceof Lead) {
            throw new MethodNotAllowedHttpException(array('Lead'), 'Entity must be of class Lead()');
        }
        if (!empty($action))  {
            $options['action'] = $action;
        }
        return $formFactory->create('lead', $entity, $options);
    }

    /**
     * Get a specific entity or generate a new one if id is empty
     *
     * @param $id
     * @return null|Lead
     */
    public function getEntity($id = null)
    {
        if ($id === null) {
            return new Lead();
        }

        //set the point trigger model in order to get the color code for the lead
        $repo = $this->getRepository();
        $repo->setTriggerModel($this->factory->getModel('point.trigger'));

        $entity = parent::getEntity($id);

        return $entity;
    }

    /**
     * {@inheritdoc}
     *
     * @param $action
     * @param $event
     * @param $entity
     * @param $isNew
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, Event $event = null)
    {
        if (!$entity instanceof Lead) {
            throw new MethodNotAllowedHttpException(array('Lead'), 'Entity must be of class Lead()');
        }

        switch ($action) {
            case "pre_save":
                $name = LeadEvents::LEAD_PRE_SAVE;
                break;
            case "post_save":
                $name = LeadEvents::LEAD_POST_SAVE;
                break;
            case "pre_delete":
                $name = LeadEvents::LEAD_PRE_DELETE;
                break;
            case "post_delete":
                $name = LeadEvents::LEAD_POST_DELETE;
                break;
            default:
                return null;
        }

        if ($this->dispatcher->hasListeners($name)) {
            if (empty($event)) {
                $event = new LeadEvent($entity, $isNew);
                $event->setEntityManager($this->em);
            }
            $this->dispatcher->dispatch($name, $event);

            return $event;
        } else {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param Lead $entity
     * @param bool   $unlock
     */
    public function saveEntity($entity, $unlock = true)
    {
        //check to see if we can glean information from ip address
        if (!$entity->imported && count($ips = $entity->getIpAddresses())) {
            $fields = $entity->getFields();

            $details = $ips->first()->getIpDetails();
            if (!empty($details['city']) && empty($fields['core']['city']['value'])) {
                $entity->addUpdatedField('city', $details['city']);
            }

            if (!empty($details['region']) && empty($fields['core']['state']['value'])) {
                $entity->addUpdatedField('state', $details['region']);
            }

            if (!empty($details['country']) && empty($fields['core']['country']['value'])) {
                $entity->addUpdatedField('country', $details['country']);
            }

            if (!empty($details['zipcode']) && empty($fields['core']['zipcode']['value'])) {
                $entity->addUpdatedField('zipcode', $details['zipcode']);
            }
        }

        parent::saveEntity($entity, $unlock);
    }

    /**
     * @param object $entity
     */
    public function deleteEntity($entity)
    {
        // Delete custom avatar if one exists
        $imageDir = $this->factory->getSystemPath('images', true);
        $avatar   = $imageDir . '/lead_avatars/avatar' . $entity->getId();

        if (file_exists($avatar)) {
            unlink($avatar);
        }

        parent::deleteEntity($entity);
    }

    /**
     * Populates custom field values for updating the lead. Also retrieves social media data
     *
     * @param Lead       $lead
     * @param array      $data
     * @param bool|false $overwriteWithBlank
     * @param bool|true  $fetchSocialProfiles
     *
     * @return array
     */
    public function setFieldValues(Lead &$lead, array $data, $overwriteWithBlank = false, $fetchSocialProfiles = true)
    {
        if ($fetchSocialProfiles) {
            //@todo - add a catch to NOT do social gleaning if a lead is created via a form, etc as we do not want the user to experience the wait
            //generate the social cache
            list($socialCache, $socialFeatureSettings) = $this->factory->getHelper('integration')->getUserProfiles(
                $lead,
                $data,
                true,
                null,
                false,
                true
            );

            //set the social cache while we have it
            if (!empty($socialCache)) {
                $lead->setSocialCache($socialCache);
            }
        }

        //save the field values
        $fieldValues = $lead->getFields();

        if (empty($fieldValues)) {
            // Lead is new or they haven't been populated so let's build the fields now
            static $fields;
            if (empty($fields)) {
                $fields = $this->factory->getModel('lead.field')->getEntities(array(
                    'filter'         => array('isPublished' => true),
                    'hydration_mode' => 'HYDRATE_ARRAY'
                ));
                $fields = $this->organizeFieldsByGroup($fields);
            }
            $fieldValues = $fields;
        }

        //update existing values
        foreach ($fieldValues as $group => &$groupFields) {
            foreach ($groupFields as $alias => &$field) {
                if (!isset($field['value'])) {
                    $field['value'] = null;
                }

                // Only update fields that are part of the passed $data array
                if (array_key_exists($alias, $data)) {
                    $curValue = $field['value'];
                    $newValue = $data[$alias];

                    if ($curValue !== $newValue && (strlen($newValue) > 0 || (strlen($newValue) === 0 && $overwriteWithBlank))) {
                        $field['value'] = $newValue;
                        $lead->addUpdatedField($alias, $newValue, $curValue);
                    }

                    //if empty, check for social media data to plug the hole
                    if (empty($newValue) && !empty($socialCache)) {
                        foreach ($socialCache as $service => $details) {
                            //check to see if a field has been assigned

                            if (!empty($socialFeatureSettings[$service]['leadFields'])
                                && in_array($field['alias'], $socialFeatureSettings[$service]['leadFields'])
                            ) {

                                //check to see if the data is available
                                $key = array_search($field['alias'], $socialFeatureSettings[$service]['leadFields']);
                                if (isset($details['profile'][$key])) {
                                    //Found!!
                                    $field['value'] = $details['profile'][$key];
                                    $lead->addUpdatedField($alias, $details['profile'][$key]);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        $lead->setFields($fieldValues);
    }

    /**
     * Disassociates a user from leads
     *
     * @param $userId
     */
    public function disassociateOwner($userId)
    {
        $leads = $this->getRepository()->findByOwner($userId);
        foreach ($leads as $lead) {
            $lead->setOwner(null);
            $this->saveEntity($lead);
        }
    }

    /**
     * Get list of entities for autopopulate fields
     *
     * @param $type
     * @param $filter
     * @param $limit
     * @param $start
     * @return array
     */
    public function getLookupResults($type, $filter = '', $limit = 10, $start = 0)
    {
        $results = array();
        switch ($type) {
            case 'user':
                $results = $this->em->getRepository('MauticUserBundle:User')->getUserList($filter, $limit, $start, array('lead' => 'leads'));
                break;
        }

        return $results;
    }

    /**
     * Obtain an array of users for api lead edits
     *
     * @return mixed
     */
    public function getOwnerList()
    {
        $results = $this->em->getRepository('MauticUserBundle:User')->getUserList('', 0);
        return $results;
    }

    /**
     * Obtains a list of leads based off IP
     *
     * @param $ip
     *
     * @return mixed
     */
    public function getLeadsByIp($ip)
    {
        return $this->getRepository()->getLeadsByIp($ip);
    }

    /**
     * Gets the details of a lead if not already set
     *
     * @param $lead
     *
     * @return mixed
     */
    public function getLeadDetails($lead)
    {
        static $details = array();

        if ($lead instanceof Lead) {
            $fields = $lead->getFields();
            if (!empty($fields)) {

                return $fields;
            }
        }

        $leadId = ($lead instanceof Lead) ? $lead->getId() : (int) $lead;

        return $this->getRepository()->getFieldValues($leadId);
    }

    /**
     * Reorganizes a field list to be keyed by field's group then alias
     *
     * @param $fields
     * @return array
     */
    public function organizeFieldsByGroup($fields)
    {
        $array = array();

        foreach ($fields as $field) {
            if ($field instanceof LeadField) {
                $alias = $field->getAlias();
                if ($field->isPublished()) {
                    $group                          = $field->getGroup();
                    $array[$group][$alias]['id']    = $field->getId();
                    $array[$group][$alias]['group'] = $group;
                    $array[$group][$alias]['label'] = $field->getLabel();
                    $array[$group][$alias]['alias'] = $alias;
                    $array[$group][$alias]['type']  = $field->getType();
                }
            } else {
                $alias = $field['alias'];
                if ($field['isPublished']) {
                    $group = $field['group'];
                    $array[$group][$alias]['id']    = $field['id'];
                    $array[$group][$alias]['group'] = $group;
                    $array[$group][$alias]['label'] = $field['label'];
                    $array[$group][$alias]['alias'] = $alias;
                    $array[$group][$alias]['type']  = $field['type'];
                }
            }
        }

        //make sure each group key is present
        $groups = array('core', 'social', 'personal', 'professional');
        foreach ($groups as $g) {
            if (!isset($array[$g])) {
                $array[$g] = array();
            }
        }

        return $array;
    }

    /**
     * Takes leads organized by group and flattens them into just alias => value
     *
     * @param $fields
     *
     * @return array
     */
    public function flattenFields($fields)
    {
        $flat = array();
        foreach ($fields as $group => $fields) {
            foreach ($fields as $field) {
                $flat[$field['alias']] = $field['value'];
            }
        }

        return $flat;
    }

    /**
     * Returns flat array for single lead
     *
     * @param $leadId
     *
     * @return array
     */
    public function getLead($leadId)
    {
        return $this->getRepository()->getLead($leadId);
    }

    /**
     * Get the current lead; if $returnTracking = true then array with lead, trackingId, and boolean of if trackingId
     * was just generated or not
     *
     * @param bool|false $returnTracking
     *
     * @return Lead|array
     */
    public function getCurrentLead($returnTracking = false)
    {
        if ((!$returnTracking && $this->systemCurrentLead) || defined('IN_MAUTIC_CONSOLE')) {
            // Just return the system set lead
            if (null === $this->systemCurrentLead) {
                $this->systemCurrentLead = new Lead();
            }

            return $this->systemCurrentLead;
        }

        $request = $this->factory->getRequest();
        $cookies = $request->cookies;

        list($trackingId, $generated) = $this->getTrackingCookie();

        if (empty($this->currentLead)) {
            $leadId = $cookies->get($trackingId);
            $ip     = $this->factory->getIpAddress();

            if (empty($leadId)) {
                //this lead is not tracked yet so get leads by IP and track that lead or create a new one
                $leads = $this->getLeadsByIp($ip->getIpAddress());

                if (count($leads)) {
                    //just create a tracking cookie for the newest lead
                    $lead   = $leads[0];
                    $leadId = $lead->getId();
                } else {
                    //let's create a lead
                    $lead = new Lead();
                    $lead->addIpAddress($ip);
                    $lead->setNewlyCreated(true);

                    // Set to prevent loops
                    $this->currentLead = $lead;

                    $this->saveEntity($lead, false);
                    $leadId = $lead->getId();
                }

                $fields = $this->getLeadDetails($lead);
                $lead->setFields($fields);
            } else {
                $lead = $this->getEntity($leadId);

                if ($lead === null) {
                    //let's create a lead
                    $lead = new Lead();
                    $lead->addIpAddress($ip);
                    $lead->setNewlyCreated(true);

                    // Set to prevent loops
                    $this->currentLead = $lead;

                    $this->saveEntity($lead, false);
                    $leadId = $lead->getId();

                    $fields = $this->getLeadDetails($lead);
                    $lead->setFields($fields);
                }
            }

            $this->currentLead = $lead;
            $this->setLeadCookie($leadId);
        }

        // Log last active
        if (!defined('MAUTIC_LEAD_LASTACTIVE_LOGGED')) {
            $this->getRepository()->updateLastActive($this->currentLead->getId());
            define('MAUTIC_LEAD_LASTACTIVE_LOGGED', 1);
        }

        return ($returnTracking) ? array($this->currentLead, $trackingId, $generated) : $this->currentLead;
    }

    /**
     * Sets current lead
     *
     * @param Lead $lead
     */
    public function setCurrentLead(Lead $lead)
    {
        if ($this->systemCurrentLead || defined('IN_MAUTIC_CONSOLE')) {
            // Overwrite system current lead
            $this->systemCurrentLead = $lead;

            return;
        }

        $oldLead = (is_null($this->currentLead)) ? $this->getCurrentLead() : $this->currentLead;

        $fields = $lead->getFields();
        if (empty($fields)) {
            $lead->setFields($this->getLeadDetails($lead));
        }

        $this->currentLead = $lead;

        // Set last active
        $this->currentLead->setLastActive(new \DateTime());

        // Update tracking cookies if the lead is different
        if ($oldLead->getId() != $lead->getId()) {

            list($newTrackingId, $oldTrackingId) = $this->getTrackingCookie(true);

            //set the tracking cookies
            $this->setLeadCookie($lead->getId());

            if ($this->dispatcher->hasListeners(LeadEvents::CURRENT_LEAD_CHANGED)) {
                $event = new LeadChangeEvent($oldLead, $oldTrackingId, $lead, $newTrackingId);
                $this->dispatcher->dispatch(LeadEvents::CURRENT_LEAD_CHANGED, $event);
            }
        }
    }

    /**
     * Used by system processes that hook into events that use getCurrentLead()
     *
     * @param Lead $lead
     */
    function setSystemCurrentLead(Lead $lead = null)
    {
        $fields = $lead->getFields();
        if (empty($fields)) {
            $lead->setFields($this->getLeadDetails($lead));
        }

        $this->systemCurrentLead = $lead;
    }

    /**
     * Get a list of lists this lead belongs to
     *
     * @param Lead       $lead
     * @param bool|false $forLists
     * @param bool|false $arrayHydration
     *
     * @return mixed
     */
    public function getLists(Lead $lead, $forLists = false, $arrayHydration = false)
    {
        $repo = $this->em->getRepository('MauticLeadBundle:LeadList');
        return $repo->getLeadLists($lead->getId(), $forLists, $arrayHydration);
    }

    /**
     * Get or generate the tracking ID for the current session
     *
     * @param bool|false $forceRegeneration
     *
     * @return array
     */
    public function getTrackingCookie($forceRegeneration = false)
    {
        static $trackingId = false, $generated = false;

        $request = $this->factory->getRequest();
        $cookies = $request->cookies;

        if ($forceRegeneration) {
            $generated = true;

            $oldTrackingId = $cookies->get('mautic_session_id');
            $trackingId    = hash('sha1', uniqid(mt_rand()));

            //create a tracking cookie
            $this->factory->getHelper('cookie')->setCookie('mautic_session_id', $trackingId);

            return array($trackingId, $oldTrackingId);
        }

        if (empty($trackingId)) {
            //check for the tracking cookie
            $trackingId = $cookies->get('mautic_session_id');
            $generated  = false;
            if (empty($trackingId)) {
                $trackingId = hash('sha1', uniqid(mt_rand()));
                $generated  = true;
            }

            //create a tracking cookie
            $this->factory->getHelper('cookie')->setCookie('mautic_session_id', $trackingId);
        }

        return array($trackingId, $generated);
    }

    /**
     * Sets the leadId for the current session
     *
     * @param $leadId
     */
    public function setLeadCookie($leadId)
    {
        // Remove the old if set
        $request       = $this->factory->getRequest();
        $cookies       = $request->cookies;
        $oldTrackingId = $cookies->get('mautic_session_id');
        if (!empty($oldTrackingId)) {
            $this->factory->getHelper('cookie')->setCookie($oldTrackingId, null, -3600);
        }

        list($trackingId, $generated) = $this->getTrackingCookie();
        $this->factory->getHelper('cookie')->setCookie($trackingId, $leadId);
    }

    /**
     * Add lead to lists
     *
     * @param array|Lead        $lead
     * @param array|LeadList    $lists
     * @param bool              $manuallyAdded
     */
    public function addToLists($lead, $lists, $manuallyAdded = true)
    {
        /** @var \Mautic\LeadBundle\Model\ListModel $listModel */
        $listModel = $this->factory->getModel('lead.list');
        $listModel->addLead($lead, $lists, $manuallyAdded);
    }

    /**
     * Remove lead from lists
     *
     * @param      $lead
     * @param      $lists
     * @param bool $manuallyRemoved
     */
    public function removeFromLists($lead, $lists, $manuallyRemoved = true)
    {
        $this->factory->getModel('lead.list')->removeLead($lead, $lists, $manuallyRemoved);
    }

    /**
     * Merge two leads; if a conflict of data occurs, the newest lead will get precedence
     *
     * @param Lead $lead
     * @param Lead $lead2
     * @param bool $autoMode If true, the newest lead will be merged into the oldes then deleted; otherwise, $lead will be merged into $lead2 then deleted
     *
     * @return Lead
     */
    public function mergeLeads(Lead $lead, Lead $lead2, $autoMode = true)
    {
        $logger  = $this->factory->getLogger();
        $logger->debug('LEAD: Merging leads');

        $leadId  = $lead->getId();
        $lead2Id = $lead2->getId();

        //if they are the same lead, then just return one
        if ($leadId === $lead2Id) {
            $logger->debug('LEAD: Leads are the same');

            return $lead;
        }

        if ($autoMode) {
            //which lead is the oldest?
            $mergeWith = ($lead->getDateAdded() < $lead2->getDateAdded()) ? $lead : $lead2;
            $mergeFrom = ($mergeWith->getId() === $leadId) ? $lead2 : $lead;
        } else {
            $mergeWith = $lead2;
            $mergeFrom = $lead;
        }
        $logger->debug('LEAD: Lead ID# ' . $mergeFrom->getId() . ' will be merged into ID# ' . $mergeWith->getId());

        //dispatch pre merge event
        $event = new LeadMergeEvent($mergeWith, $mergeFrom);
        if ($this->dispatcher->hasListeners(LeadEvents::LEAD_PRE_MERGE)) {
            $this->dispatcher->dispatch(LeadEvents::LEAD_PRE_MERGE, $event);
        }

        //merge IP addresses
        $ipAddresses = $mergeFrom->getIpAddresses();
        foreach ($ipAddresses as $ip) {
            $mergeWith->addIpAddress($ip);

            $logger->debug('LEAD: Associating with IP ' . $ip->getIpAddress());
        }

        //merge fields
        $mergeFromFields = $mergeFrom->getFields();
        foreach ($mergeFromFields as $group => $groupFields) {
            foreach ($groupFields as $alias => $details) {
                //overwrite old lead's data with new lead's if new lead's is not empty
                if (!empty($details['value'])) {
                    $mergeWith->addUpdatedField($alias, $details['value']);

                    $logger->debug('LEAD: Updated ' . $alias . ' = ' . $details['value']);
                }
            }
        }

        //merge owner
        $oldOwner = $mergeWith->getOwner();
        $newOwner = $mergeFrom->getOwner();

        if ($oldOwner === null && $newOwner !== null) {
            $mergeWith->setOwner($newOwner);

            $logger->debug('LEAD: New owner is ' . $newOwner->getId());
        }

        //sum points
        $mergeWithPoints = $mergeWith->getPoints();
        $mergeFromPoints = $mergeFrom->getPoints();
        $mergeWith->setPoints($mergeWithPoints + $mergeFromPoints);
        $logger->debug('LEAD: Adding ' . $mergeFromPoints . ' points to lead');

        //merge tags
        $mergeFromTags = $mergeFrom->getTags();
        $addTags       = $mergeFromTags->getKeys();
        $this->modifyTags($mergeWith, $addTags, null, false);

        //save the updated lead
        $this->saveEntity($mergeWith, false);

        //post merge events
        if ($this->dispatcher->hasListeners(LeadEvents::LEAD_POST_MERGE)) {
            $this->dispatcher->dispatch(LeadEvents::LEAD_POST_MERGE, $event);
        }

        //delete the old
        $this->deleteEntity($mergeFrom);

        //return the merged lead
        return $mergeWith;
    }

    /**
     * @param Lead $lead
     * @param string $channel
     *
     * @return int
     *
     * @see \Mautic\LeadBundle\Entity\DoNotContact This method can return boolean false, so be
     *                                             sure to always compare the return value against
     *                                             the class constants of DoNotContact
     */
    public function isContactable(Lead $lead, $channel)
    {
        /** @var \Mautic\LeadBundle\Entity\DoNotContactRepository $dncRepo */
        $dncRepo = $this->em->getRepository('MauticLeadBundle:DoNotContact');

        /** @var \Mautic\LeadBundle\Entity\DoNotContact[] $entries */
        $dncEntries = $dncRepo->getEntriesByLeadAndChannel($lead, $channel);

        // If the lead has no entries in the DNC table, we're good to go
        if (empty($dncEntries)) {
            return DoNotContact::IS_CONTACTABLE;
        }

        foreach ($dncEntries as $dnc) {
            if ($dnc->getReason() !== DoNotContact::IS_CONTACTABLE) {
                return $dnc->getReason();
            }
        }

        return DoNotContact::IS_CONTACTABLE;
    }

    /**
     * Remove a Lead's DNC entry based on channel.
     *
     * @param Lead $lead
     * @param string $channel
     *
     * @return boolean
     */
    public function removeDncForLead(Lead $lead, $channel)
    {
        /** @var DoNotContact $dnc */
        foreach ($lead->getDoNotContact() as $dnc) {
            if ($dnc->getChannel() === $channel) {
                $lead->removeDoNotContactEntry($dnc);

                $this->getRepository()->saveEntity($lead);

                return true;
            }
        }

        return false;
    }

    /**
     * Create a DNC entry for a lead
     *
     * @param Lead         $lead
     * @param string|array $channel  If an array with an ID, use the structure ['email' => 123]
     * @param string       $comments
     * @param int          $reason
     * @param bool         $flush
     *
     * @return boolean If a DNC entry is added or updated, returns true. If a DNC is already present
     *                 and has the specified reason, nothing is done and this returns false.
     */
    public function addDncForLead(Lead $lead, $channel, $comments = '', $reason = DoNotContact::BOUNCED, $flush = true)
    {
        $isContactable = $this->isContactable($lead, $channel);
        $reason = $this->determineReasonFromTag($reason);

        // If they don't have a DNC entry yet
        if ($isContactable === DoNotContact::IS_CONTACTABLE) {
            $dnc = new DoNotContact();

            if (is_array($channel)) {
                $channelId = reset($channel);
                $channel   = key($channel);

                $dnc->setChannelId((int) $channelId);
            }

            $dnc->setChannel($channel);
            $dnc->setReason($reason);
            $dnc->setLead($lead);
            $dnc->setDateAdded(new \DateTime);
            $dnc->setComments($comments);

            $lead->addDoNotContactEntry($dnc);

            $this->getRepository()->saveEntity($lead);

            if ($flush) {
                $this->em->flush();
            }

            return true;
        }
        // Or if the given reason is different than the stated reason
        elseif ($isContactable !== $reason) {
            /** @var DoNotContact $dnc */
            foreach ($lead->getDoNotContact() as $dnc) {
                // Only update if the contact did not unsubscribe themselves
                if ($dnc->getChannel() === $channel && $dnc->getReason() !== DoNotContact::UNSUBSCRIBED) {
                    // Remove the outdated entry
                    $lead->removeDoNotContactEntry($dnc);

                    // Update the DNC entry
                    $dnc->setChannel($channel);
                    $dnc->setReason($reason);
                    $dnc->setLead($lead);
                    $dnc->setDateAdded(new \DateTime);
                    $dnc->setComments($comments);

                    // Re-add the entry to the lead
                    $lead->addDoNotContactEntry($dnc);

                    // Persist
                    $this->getRepository()->saveEntity($lead);

                    if ($flush) {
                        $this->em->flush();
                    }

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * This method will translate text reason tags into DNC reason codes.
     *
     * @param string|int $tag
     *
     * @return int
     *
     * @see \Mautic\LeadBundle\Entity\DoNotContact This method can return boolean false, so be
     * sure to always compare the return value against the class constants of DoNotContact
     *
     * @deprecated - No replacement. Remove in 2.0
     */
    private function determineReasonFromTag($tag)
    {
        switch ($tag) {
            case DoNotContact::UNSUBSCRIBED:
            case 'unsubscribed':
                return DoNotContact::UNSUBSCRIBED;

            case DoNotContact::BOUNCED:
            case 'bounced':
                return DoNotContact::BOUNCED;

            case DoNotContact::MANUAL:
            case 'manual':
                return DoNotContact::MANUAL;
        }

        return DoNotContact::IS_CONTACTABLE;
    }

    /**
     * Add a do not contact entry for the lead
     *
     * @param Lead       $lead
     * @param string     $emailAddress
     * @param string     $reason
     * @param bool|true  $persist
     * @param bool|false $manual
     *
     * @return DoNotContact|bool
     * @throws \Doctrine\DBAL\DBALException
     *
     * @deprecated Use addDncForLead() instead. To be removed in 2.0.
     */
    public function setDoNotContact(Lead $lead, $emailAddress = '', $reason = '', $persist = true, $manual = false)
    {
        return $this->unsubscribeLead($lead, $reason, $persist, $manual);
    }

    /**
     * @param Lead       $lead
     * @param string     $comments
     * @param bool|true  $persist
     * @param bool|false $manual
     *
     * @return bool|DoNotContact
     *
     * @deprecated Use addDncForLead() instead. To be removed in 2.0.
     */
    public function unsubscribeLead(Lead $lead, $comments = null, $persist = true, $manual = false)
    {
        $comments = $comments ?: $this->factory->getTranslator()->trans('mautic.email.dnc.unsubscribed');

        $reason = $manual ? DoNotContact::MANUAL : DoNotContact::UNSUBSCRIBED;

        $this->addDncForLead($lead, 'email', $comments, $reason);

        // This is here to duplicate previous behavior for BC
        if ($persist !== true) {
            /** @var DoNotContact $dnc */
            foreach ($lead->getDoNotContact() as $dnc) {
                if ($dnc->getChannel() === 'email') {
                    return $dnc;
                }
            }
        }

        return false;
    }

    /**
     * @param      $fields
     * @param      $data
     * @param null $owner
     * @param null $list
     * @param null $tags
     * @param bool $persist Persist to the database; otherwise return entity
     *
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Swift_RfcComplianceException
     */
    public function importLead($fields, $data, $owner = null, $list = null, $tags = null, $persist = true)
    {
        // Let's check for an existing lead by email
        $hasEmail = (!empty($fields['email']) && !empty($data[$fields['email']]));
        if ($hasEmail) {
            // Validate the email
            MailHelper::validateEmail($data[$fields['email']]);

            $leadFound = $this->getRepository()->getLeadByEmail($data[$fields['email']]);
            $lead      = ($leadFound) ? $this->em->getReference('MauticLeadBundle:Lead', $leadFound['id']) : new Lead();
            $merged    = $leadFound;
        } else {
            $lead   = new Lead();
            $merged = false;
        }

        if (!empty($fields['dateAdded']) && !empty($data[$fields['dateAdded']])) {
            $dateAdded = new DateTimeHelper($data[$fields['dateAdded']]);
            $lead->setDateAdded($dateAdded->getUtcDateTime());
        }
        unset($fields['dateAdded']);

        if (!empty($fields['dateModified']) && !empty($data[$fields['dateModified']])) {
            $dateModified = new DateTimeHelper($data[$fields['dateModified']]);
            $lead->setDateModified($dateModified->getUtcDateTime());
        }
        unset($fields['dateModified']);

        if (!empty($fields['lastActive']) && !empty($data[$fields['lastActive']])) {
            $lastActive = new DateTimeHelper($data[$fields['lastActive']]);
            $lead->setLastActive($lastActive->getUtcDateTime());
        }
        unset($fields['lastActive']);

        if (!empty($fields['dateIdentified']) && !empty($data[$fields['dateIdentified']])) {
            $dateIdentified = new DateTimeHelper($data[$fields['dateIdentified']]);
            $lead->setDateIdentified($dateIdentified->getUtcDateTime());
        }
        unset($fields['dateIdentified']);

        if (!empty($fields['createdByUser']) && !empty($data[$fields['createdByUser']])) {
            $userRepo = $this->em->getRepository('MauticUserBundle:User');
            $createdByUser = $userRepo->findByIdentifier($data[$fields['createdByUser']]);
            if ($createdByUser !== null) {
                $lead->setCreatedBy($createdByUser);
            }
        }
        unset($fields['createdByUser']);

        if (!empty($fields['modifiedByUser']) && !empty($data[$fields['modifiedByUser']])) {
            $userRepo = $this->em->getRepository('MauticUserBundle:User');
            $modifiedByUser = $userRepo->findByIdentifier($data[$fields['modifiedByUser']]);
            if ($modifiedByUser !== null) {
                $lead->setModifiedBy($modifiedByUser);
            }
        }
        unset($fields['modifiedByUser']);

        if (!empty($fields['ip']) && !empty($data[$fields['ip']])) {
            $addresses = explode(',', $data[$fields['ip']]);
            foreach ($addresses as $address) {
                $ipAddress = new IpAddress;
                $ipAddress->setIpAddress(trim($address));
                $lead->addIpAddress($ipAddress);
            }
        }
        unset($fields['ip']);

        if (!empty($fields['points']) && !empty($data[$fields['points']]) && $lead->getId() === null) {
            // Add points only for new leads
            $lead->setPoints($data[$fields['points']]);

            //add a lead point change log
            $log = new PointsChangeLog();
            $log->setDelta($data[$fields['points']]);
            $log->setLead($lead);
            $log->setType('lead');
            $log->setEventName($this->factory->getTranslator()->trans('mautic.lead.import.event.name'));
            $log->setActionName($this->factory->getTranslator()->trans('mautic.lead.import.action.name', array(
                '%name%' => $this->factory->getUser()->getUsername()
            )));
            $log->setIpAddress($this->factory->getIpAddress());
            $log->setDateAdded(new \DateTime());
            $lead->addPointsChangeLog($log);
        }
        unset($fields['points']);

        // Set unsubscribe status
        if (!empty($fields['doNotEmail']) && !empty($data[$fields['doNotEmail']]) && $hasEmail) {
            $doNotEmail = filter_var($data[$fields['doNotEmail']], FILTER_VALIDATE_BOOLEAN);
            if ($doNotEmail) {
                $reason = $this->factory->getTranslator()->trans('mautic.lead.import.by.user', array(
                    "%user%" => $this->factory->getUser()->getUsername()
                ));

                // The email must be set for successful unsubscribtion
                $lead->addUpdatedField('email', $data[$fields['email']]);
                $this->unsubscribeLead($lead, $reason, false);
            }
        }
        unset($fields['doNotEmail']);

        if ($owner !== null) {
            $lead->setOwner($this->em->getReference('MauticUserBundle:User', $owner));
        }

        if ($tags !== null) {
            $this->modifyTags($lead, $tags, null, false);
        }

        // Set profile data
        foreach ($fields as $leadField => $importField) {
            // Prevent overwriting existing data with empty data
            if (array_key_exists($importField, $data) && !is_null($data[$importField]) && $data[$importField] != '') {
                $lead->addUpdatedField($leadField, $data[$importField]);
            }
        }

        $lead->imported = true;

        if ($persist) {
            $this->saveEntity($lead);

            if ($list !== null) {
                $this->addToLists($lead, array($list));
            }
        }

        return $merged;
    }

    /**
     * Update a leads tags
     *
     * @param Lead  $lead
     * @param array $tags
     * @param bool|false $removeOrphans
     */
    public function setTags(Lead $lead, array $tags, $removeOrphans = false)
    {
        $currentTags  = $lead->getTags();
        $leadModified = $tagsDeleted = false;

        foreach ($currentTags as $tagName => $tag) {
            if (!in_array($tag->getId(), $tags)) {
                // Tag has been removed
                $lead->removeTag($tag);
                $leadModified = $tagsDeleted = true;
            } else {
                // Remove tag so that what's left are new tags
                $key = array_search($tag->getId(), $tags);
                unset($tags[$key]);
            }
        }

        if (!empty($tags)) {
            foreach($tags as $tag) {
                if (is_numeric($tag)) {
                    // Existing tag being added to this lead
                    $lead->addTag(
                        $this->factory->getEntityManager()->getReference('MauticLeadBundle:Tag', $tag)
                    );
                } else {
                    // New tag
                    $newTag = new Tag();
                    $newTag->setTag(InputHelper::clean($tag));
                    $lead->addTag($newTag);
                }
            }
            $leadModified = true;
        }

        if ($leadModified) {
            $this->saveEntity($lead);

            // Delete orphaned tags
            if ($tagsDeleted && $removeOrphans) {
                $this->getTagRepository()->deleteOrphans();
            }
        }
    }

    /**
     * Modify tags with support to remove via a prefixed minus sign
     *
     * @param Lead $lead
     * @param      $tags
     * @param      $removeTags
     * @param      $persist
     */
    public function modifyTags(Lead $lead, $tags, array $removeTags = null, $persist = true)
    {
        $logger   = $this->factory->getLogger();
        $leadTags = $lead->getTags();

        if ($leadTags) {
            $logger->debug('LEAD: Lead currently has tags '.implode(', ', $leadTags->getKeys()));
        }

        if (!is_array($tags)) {
            $tags = explode(',', $tags);
        }

        $logger->debug('CONTACT: Adding ' . implode(', ', $tags) . ' to contact ID# ' . $lead->getId());

        array_walk($tags, create_function('&$val', '$val = trim($val); \Mautic\CoreBundle\Helper\InputHelper::clean($val);'));

        // See which tags already exist
        $foundTags = $this->getTagRepository()->getTagsByName($tags);
        foreach ($tags as $tag) {
            if (strpos($tag, '-') === 0) {
                // Tag to be removed
                $tag = substr($tag, 1);

                if (array_key_exists($tag, $foundTags) && $leadTags->contains($foundTags[$tag])) {
                    $lead->removeTag($foundTags[$tag]);
                    $logger->debug('LEAD: Removed ' . $tag);
                }
            } else {
                // Tag to be added
                if (!array_key_exists($tag, $foundTags)) {
                    // New tag
                    $newTag = new Tag();
                    $newTag->setTag($tag);
                    $lead->addTag($newTag);
                    $logger->debug('LEAD: Added ' . $tag);
                } elseif (!$leadTags->contains($foundTags[$tag])) {
                    $lead->addTag($foundTags[$tag]);

                    $logger->debug('LEAD: Added ' . $tag);
                }
            }
        }

        if (!empty($removeTags)) {

            $logger->debug('CONTACT: Removing '.implode(', ', $removeTags).' for contact ID# '.$lead->getId());

            array_walk($removeTags, create_function('&$val', '$val = trim($val); \Mautic\CoreBundle\Helper\InputHelper::clean($val);'));

            // See which tags really exist
            $foundRemoveTags = $this->getTagRepository()->getTagsByName($removeTags);

            foreach ($removeTags as $tag) {
                // Tag to be removed
                if (array_key_exists($tag, $foundRemoveTags) && $leadTags->contains($foundRemoveTags[$tag])) {
                    $lead->removeTag($foundRemoveTags[$tag]);
                    $logger->debug('LEAD: Removed '.$tag);
                }
            }
        }

        if ($persist) {
            $this->saveEntity($lead);
        }
    }

    /**
     * Get array of available lead tags
     */
    public function getTagList()
    {
        return $this->getTagRepository()->getSimpleList(null, array(), 'tag', 'id');
    }

    /**
     * @param null $operator
     *
     * @return array
     */
    public function getFilterExpressionFunctions($operator = null)
    {
        $operatorOptions = array(
            '='          =>
                array(
                    'label'       => 'mautic.lead.list.form.operator.equals',
                    'expr'        => 'eq',
                    'negate_expr' => 'neq'
                ),
            '!='         =>
                array(
                    'label'       => 'mautic.lead.list.form.operator.notequals',
                    'expr'        => 'neq',
                    'negate_expr' => 'eq'
                ),
            'gt'         =>
                array(
                    'label'       => 'mautic.lead.list.form.operator.greaterthan',
                    'expr'        => 'gt',
                    'negate_expr' => 'lt'
                ),
            'gte'        =>
                array(
                    'label'       => 'mautic.lead.list.form.operator.greaterthanequals',
                    'expr'        => 'gte',
                    'negate_expr' => 'lt'
                ),
            'lt'         =>
                array(
                    'label'       => 'mautic.lead.list.form.operator.lessthan',
                    'expr'        => 'lt',
                    'negate_expr' => 'gt'
                ),
            'lte'        =>
                array(
                    'label'       => 'mautic.lead.list.form.operator.lessthanequals',
                    'expr'        => 'lte',
                    'negate_expr' => 'gt'
                ),
            'like'       =>
                array(
                    'label'       => 'mautic.lead.list.form.operator.islike',
                    'expr'        => 'like',
                    'negate_expr' => 'notLike'
                ),
            '!like'      =>
                array(
                    'label'       => 'mautic.lead.list.form.operator.isnotlike',
                    'expr'        => 'notLike',
                    'negate_expr' => 'like'
                ),
        );

        return ($operator === null) ? $operatorOptions : $operatorOptions[$operator];
    }

    /**
     * Get bar chart data of hits
     *
     * @param char     $unit   {@link php.net/manual/en/function.date.php#refsect1-function.date-parameters}
     * @param DateTime $dateFrom
     * @param DateTime $dateTo
     * @param string   $dateFormat
     * @param array    $filter
     * @param boolean  $canViewOthers
     *
     * @return array
     */
    public function getLeadsLineChartData($unit, $dateFrom, $dateTo, $dateFormat = null, $filter = array(), $canViewOthers = true)
    {
        $flag = null;
        $topLists  = null;
        $allLeadsT = $this->factory->getTranslator()->trans('mautic.lead.all.leads');
        $identifiedT = $this->factory->getTranslator()->trans('mautic.lead.identified');
        $anonymousT = $this->factory->getTranslator()->trans('mautic.lead.lead.anonymous');

        if (isset($filter['flag'])) {
            $flag = $filter['flag'];
            unset($filter['flag']);
        }

        if (!$canViewOthers) {
            $filter['owner_id'] = $this->factory->getUser()->getId();
        }

        $chart     = new LineChart($unit, $dateFrom, $dateTo, $dateFormat);
        $query     = $chart->getChartQuery($this->em->getConnection());
        $anonymousFilter = $filter;
        $anonymousFilter['date_identified'] = array(
            'expression' => 'isNull'
        );
        $identifiedFilter = $filter;
        $identifiedFilter['date_identified'] = array(
            'expression' => 'isNotNull'
        );

        if ($flag == 'top') {
            $topLists = $this->factory->getModel('lead.list')->getTopLists(6, $dateFrom, $dateTo);
            if ($topLists) {
                foreach ($topLists as $list) {
                    $filter['leadlist_id'] = array(
                        'value' => $list['id'],
                        'list_column_name' => 't.id'
                    );
                    $all = $query->fetchTimeData('leads', 'date_added', $filter);
                    $chart->setDataset($list['name'] . ': ' . $allLeadsT, $all);
                }
            }
        } elseif ($flag == 'topIdentifiedVsAnonymous') {
            $topLists = $this->factory->getModel('lead.list')->getTopLists(3, $dateFrom, $dateTo);
            if ($topLists) {
                foreach ($topLists as $list) {
                    $anonymousFilter['leadlist_id'] = array(
                        'value' => $list['id'],
                        'list_column_name' => 't.id'
                    );
                    $identifiedFilter['leadlist_id'] = array(
                        'value' => $list['id'],
                        'list_column_name' => 't.id'
                    );
                    $identified = $query->fetchTimeData('leads', 'date_added', $identifiedFilter);
                    $anonymous = $query->fetchTimeData('leads', 'date_added', $anonymousFilter);
                    $chart->setDataset($list['name'] . ': ' . $identifiedT, $identified);
                    $chart->setDataset($list['name'] . ': ' . $anonymousT, $anonymous);
                }
            }
        } elseif ($flag == 'identified') {
            $identified = $query->fetchTimeData('leads', 'date_added', $identifiedFilter);
            $chart->setDataset($identifiedT, $identified);
        } elseif ($flag == 'anonymous') {
            $anonymous = $query->fetchTimeData('leads', 'date_added', $anonymousFilter);
            $chart->setDataset($anonymousT, $anonymous);
        } elseif ($flag == 'identifiedVsAnonymous') {
            $identified = $query->fetchTimeData('leads', 'date_added', $identifiedFilter);
            $anonymous = $query->fetchTimeData('leads', 'date_added', $anonymousFilter);
            $chart->setDataset($identifiedT, $identified);
            $chart->setDataset($anonymousT, $anonymous);
        } else {
            $all = $query->fetchTimeData('leads', 'date_added', $filter);
            $chart->setDataset($allLeadsT, $all);
        }

        return $chart->render();
    }

    /**
     * Get pie chart data of dwell times
     *
     * @param string  $dateFrom
     * @param string  $dateTo
     * @param array   $filters
     * @param boolean $canViewOthers
     *
     * @return array
     */
    public function getAnonymousVsIdentifiedPieChartData($dateFrom, $dateTo, $filters = array(), $canViewOthers = true)
    {
        $chart = new PieChart();
        $query = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo);

        if (!$canViewOthers) {
            $filter['owner_id'] = $this->factory->getUser()->getId();
        }

        $identified = $query->count('leads', 'date_identified', 'date_added', $filters);
        $all = $query->count('leads', 'id', 'date_added', $filters);
        $chart->setDataset($this->factory->getTranslator()->trans('mautic.lead.identified'), $identified);
        $chart->setDataset($this->factory->getTranslator()->trans('mautic.lead.lead.anonymous'), ($all - $identified));

        return $chart->render();
    }

    /**
     * Get leads count per country name.
     * Can't use entity, because country is a custom field.
     *
     * @param string  $dateFrom
     * @param string  $dateTo
     * @param array   $filters
     * @param boolean $canViewOthers
     *
     * @return array
     */
    public function getLeadMapData($dateFrom, $dateTo, $filters = array(), $canViewOthers = true)
    {
        if (!$canViewOthers) {
            $filter['owner_id'] = $this->factory->getUser()->getId();
        }

        $q = $this->em->getConnection()->createQueryBuilder();
        $q->select('COUNT(t.id) as quantity, t.country')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 't')
            ->groupBy('t.country')
            ->where($q->expr()->isNotNull('t.country'));

        $chartQuery = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo);
        $chartQuery->applyFilters($q, $filters);
        $chartQuery->applyDateFilters($q, 'date_added');

        $results = $q->execute()->fetchAll();

        $countries = array_flip(Intl::getRegionBundle()->getCountryNames('en'));
        $mapData = array();

        // Convert country names to 2-char code
        if ($results) {
            foreach ($results as $leadCountry) {
                if (isset($countries[$leadCountry['country']])) {
                    $mapData[$countries[$leadCountry['country']]] = $leadCountry['quantity'];
                }
            }
        }

        return $mapData;
    }

    /**
     * Get a list of top (by leads owned) users
     *
     * @param integer $limit
     * @param string  $dateFrom
     * @param string  $dateTo
     * @param array   $filters
     *
     * @return array
     */
    public function getTopOwners($limit = 10, $dateFrom = null, $dateTo = null, $filters = array())
    {
        $q = $this->em->getConnection()->createQueryBuilder();
        $q->select('COUNT(t.id) AS leads, t.owner_id, u.first_name, u.last_name')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 't')
            ->join('t', MAUTIC_TABLE_PREFIX.'users', 'u', 'u.id = t.owner_id')
            ->where($q->expr()->isNotNull('t.owner_id'))
            ->orderBy('leads', 'DESC')
            ->groupBy('t.owner_id, u.first_name, u.last_name')
            ->setMaxResults($limit);

        $chartQuery = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo);
        $chartQuery->applyFilters($q, $filters);
        $chartQuery->applyDateFilters($q, 'date_added');

        $results = $q->execute()->fetchAll();
        return $results;
    }

    /**
     * Get a list of top (by leads owned) users
     *
     * @param integer $limit
     * @param string  $dateFrom
     * @param string  $dateTo
     * @param array   $filters
     *
     * @return array
     */
    public function getTopCreators($limit = 10, $dateFrom = null, $dateTo = null, $filters = array())
    {
        $q = $this->em->getConnection()->createQueryBuilder();
        $q->select('COUNT(t.id) AS leads, t.created_by, t.created_by_user')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 't')
            ->where($q->expr()->isNotNull('t.created_by'))
            ->andWhere($q->expr()->isNotNull('t.created_by_user'))
            ->orderBy('leads', 'DESC')
            ->groupBy('t.created_by, t.created_by_user')
            ->setMaxResults($limit);

        $chartQuery = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo);
        $chartQuery->applyFilters($q, $filters);
        $chartQuery->applyDateFilters($q, 'date_added');

        $results = $q->execute()->fetchAll();
        return $results;
    }

    /**
     * Get a list of leads in a date range
     *
     * @param integer  $limit
     * @param DateTime $dateFrom
     * @param DateTime $dateTo
     * @param array    $filters
     * @param array    $options
     *
     * @return array
     */
    public function getLeadList($limit = 10, \DateTime $dateFrom = null, \DateTime $dateTo = null, $filters = array(), $options = array())
    {
        if (!empty($options['canViewOthers'])) {
            $filter['owner_id'] = $this->factory->getUser()->getId();
        }

        $q = $this->em->getConnection()->createQueryBuilder();
        $q->select('t.id, t.firstname, t.lastname, t.email, t.date_added, t.date_modified')
            ->from(MAUTIC_TABLE_PREFIX.'leads', 't')
            ->setMaxResults($limit);

        $chartQuery = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo);
        $chartQuery->applyFilters($q, $filters);
        $chartQuery->applyDateFilters($q, 'date_added');

        $results = $q->execute()->fetchAll();

        if ($results) {
            foreach ($results as &$result) {
                if ($result['firstname'] || $result['lastname']) {
                    $result['name'] = trim($result['firstname'] . ' ' . $result['lastname']);
                } elseif ($result['email']) {
                    $result['name'] = $result['email'];
                } else {
                    $result['name'] = 'anonymous';
                }
                unset($result['firstname']);
                unset($result['lastname']);
                unset($result['email']);
            }
        }

        return $results;
    }
}
