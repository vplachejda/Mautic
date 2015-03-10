<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Helper;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\PointsChangeLog;

/**
 * Class FormEventHelper
 *
 * @package Mautic\LeadBundle\Helper
 */
class FormEventHelper
{

    /**
     * @param       $action
     * @param       $form
     * @param array $post
     * @param       $factory
     * @param array $fields
     *
     * @return array
     */
    public static function createLead($action, $form, array $post, MauticFactory $factory, array $fields)
    {
        /** @var \Mautic\LeadBundle\Model\LeadModel $model */
        $model      = $factory->getModel('lead');
        $em         = $factory->getEntityManager();
        $properties = $action->getProperties();

        //set the mapped data
        $leadFields = $factory->getModel('lead.field')->getEntities(
            array('filter' => array('isPublished' => true))
        );
        $data = array();

        $lead = $model->getCurrentLead();
        $currentFields = $lead->getFields();

        foreach ($leadFields as $f) {
            $id    = $f->getId();
            $alias = $f->getAlias();
            $type  = $f->getType();

            $data[$alias] = '';

            if (!empty($properties['mappedFields'][$id])) {
                $mappedTo = $properties['mappedFields'][$id];
                if (isset($fields[$mappedTo])) {
                    $fieldName = $fields[$mappedTo]['alias'];
                    if (isset($post[$fieldName])) {
                        $value        = is_array($post[$fieldName]) ? implode(', ', $post[$fieldName]) : $post[$fieldName];
                        $data[$alias] = $value;

                        //update the lead rather than creating a new one if there is for sure identifier match
                        if ($type == 'email') {
                            $leads = $em->getRepository('MauticLeadBundle:Lead')->getLeadsByFieldValue($alias, $value, $lead->getId());
                            if (count($leads)) {
                                //merge with current lead
                                $lead = $model->mergeLeads($lead, $leads[0]);
                            } else {
                                //create a new lead if details differ
                                $currentEmail = $currentFields['core']['email']['value'];
                                if (!empty($currentEmail) && $currentEmail != $value) {
                                    //for sure a different lead so create a new one
                                    $lead = new Lead();
                                    $lead->setNewlyCreated(true);
                                }
                            }
                        }
                    }
                }
            }
        }

        //check for existing IP address
        $ipAddress = $factory->getIpAddress();

        //no lead was found by a mapped email field so create a new one
        if ($lead->isNewlyCreated()) {
            $lead->setPoints($properties['points']);
            $lead->addIpAddress($ipAddress);

            //create a new points change event
            $lead->addPointsChangeLogEntry(
                'form',
                $form->getId() . ":" . $form->getName(),
                $action->getName(),
                $properties['points'],
                $ipAddress
            );
        } else {
            $leadIpAddresses = $lead->getIpAddresses();
            if (!$leadIpAddresses->contains($ipAddress)) {
                $lead->addIpAddress($ipAddress);
            }
        }

        //set the mapped fields
        $model->setFieldValues($lead, $data, false);

        if (!empty($event)) {
            $event->setIpAddress($ipAddress);
            $lead->addPointsChangeLog($event);
        }

        //create a new lead
        $model->saveEntity($lead, false);

        $model->setCurrentLead($lead);

        //return the lead so it can be used elsewhere
        return array('lead' => $lead);
    }

    /**
     * @param array $post
     * @param array $server
     * @param       $fields
     * @param MauticFactory $factory
     * @param       $action
     * @param       $form
     */
    public static function changePoints (array $post, array $server, $fields, MauticFactory $factory, $action, $form)
    {
        $properties = $action->getProperties();

        if (isset($fields[$properties['formField']])) {
            $fieldName = $fields[$properties['formField']]['alias'];
            if (isset($post[$fieldName])) {
                $model = $factory->getModel('lead.lead');
                $em    = $factory->getEntityManager();
                $leads = $em->getRepository('MauticLeadBundle:Lead')->getLeadsByFieldValue(
                    $fieldName,
                    $post[$fieldName]
                );

                //check for existing IP address or add one if not exist
                $ipAddress = $factory->getIpAddress();

                //create a new points change event
                $event = new PointsChangeLog();
                $event->setType('form');
                $event->setEventName($form->getId() . ":" . $form->getName());
                $event->setActionName($action->getName());
                $event->setIpAddress($ipAddress);
                $event->setDateAdded(new \DateTime());

                if (count($leads) === 1) {
                    //good to go so update the points
                    $lead = $leads[0];
                } else {
                    switch ($properties['matchMode']) {
                        case 'strict':
                            //no points change since more than one lead matched
                            $lead = false;
                            break;
                        case 'newest':
                            //the newest lead is listed first so use it
                            $lead = $leads[0];
                            break;
                        case 'oldest':
                            //the last lead is the oldest so use it
                            $lead = end($leads);
                            break;
                        case 'all':
                            $lead = false;

                            foreach ($leads as &$l) {
                                $event->setDelta($properties['points']);
                                $event->setLead($l);
                                $l->addPointsChangeLog($event);

                                $ipAddresses = $l->getIpAddresses();
                                //add the IP if the lead is not already associated with it
                                if (!$ipAddresses->contains($ipAddress)) {
                                    $l->addIpAddress($ipAddress);
                                }
                            }

                            $model->saveEntities($leads, false);
                            break;
                    }
                }

                if ($lead) {
                    $event->setDelta($properties['points']);
                    $event->setLead($lead);
                    $lead->addPointsChangeLog($event);
                    $lead->addToPoints($properties['points']);
                    $ipAddresses = $lead->getIpAddresses();
                    //add the IP if the lead is not already associated with it
                    if (!$ipAddresses->contains($ipAddress)) {
                        $lead->addIpAddress($ipAddress);
                    }

                    $model->saveEntity($lead, false);
                }
            }
        }
    }

    /**
     * @param $action
     * @param $factory
     */
    public static function changeLists ($action, $factory)
    {
        $properties = $action->getProperties();

        /** @var \Mautic\LeadBundle\Model\LeadModel $leadModel */
        $leadModel  = $factory->getModel('lead');
        $lead       = $leadModel->getCurrentLead();
        $addTo      = $properties['addToLists'];
        $removeFrom = $properties['removeFromLists'];

        if (!empty($addTo)) {
            $leadModel->addToLists($lead, $addTo);
        }

        if (!empty($removeFrom)) {
            $leadModel->removeFromLists($lead, $removeFrom);
        }
    }
}
