<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Model;

use Mautic\UserBundle\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class FormModel
 */
class FormModel extends CommonModel
{

    /**
     * Get a specific entity
     *
     * @param $id
     *
     * @return null|object
     */
    public function getEntity($id = null)
    {
        if (null !== $id) {
            $repo = $this->getRepository();
            if (method_exists($repo, 'getEntity')) {
                return $repo->getEntity($id);
            }

            return $repo->find($id);
        }

        return null;
    }

    /**
     * Lock an entity to prevent multiple people from editing
     *
     * @param object $entity
     *
     * @return void
     */
    public function lockEntity($entity)
    {
        //lock the row if applicable
        if (method_exists($entity, 'setCheckedOut')) {
            $user = $this->factory->getUser();
            if ($user->getId()) {
                $entity->setCheckedOut(new \DateTime());
                $entity->setCheckedOutBy($user);
                $this->em->persist($entity);
                $this->em->flush();
            }
        }
    }

    /**
     * Check to see if the entity is locked
     *
     * @param object $entity
     *
     * @return bool
     */
    public function isLocked($entity)
    {
        if (method_exists($entity, 'getCheckedOut')) {
            $checkedOut = $entity->getCheckedOut();
            if (!empty($checkedOut)) {
                //is it checked out by the current user?
                $checkedOutBy = $entity->getCheckedOutBy();
                if (!empty($checkedOutBy) && $checkedOutBy !== $this->factory->getUser()->getId()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Unlock an entity that prevents multiple people from editing
     *
     * @param object $entity
     * @param        $extra Can be used by model to determine what to unlock
     *
     * @return void
     */
    public function unlockEntity($entity, $extra = null)
    {
        //unlock the row if applicable
        if (method_exists($entity, 'setCheckedOut')) {
            //flush any potential changes
            $this->em->refresh($entity);

            $entity->setCheckedOut(null);
            $entity->setCheckedOutBy(null);

            $this->em->persist($entity);
            $this->em->flush();
        }
    }

    /**
     * Create/edit entity
     *
     * @param object $entity
     * @param bool   $unlock
     */
    public function saveEntity($entity, $unlock = true)
    {
        $isNew = $this->isNewEntity($entity);

        //set some defaults
        $this->setTimestamps($entity, $isNew, $unlock);

        $event = $this->dispatchEvent("pre_save", $entity, $isNew);
        $this->getRepository()->saveEntity($entity);
        $this->dispatchEvent("post_save", $entity, $isNew, $event);
    }

    /**
     * Save an array of entities
     *
     * @param array $entities
     * @param bool  $unlock
     *
     * @return array
     */
    public function saveEntities($entities, $unlock = true)
    {
        //iterate over the results so the events are dispatched on each delete
        $batchSize = 20;
        foreach ($entities as $k => $entity) {
            $isNew = $this->isNewEntity($entity);

            //set some defaults
            $this->setTimestamps($entity, $isNew, $unlock);

            $event = $this->dispatchEvent("pre_save", $entity, $isNew);
            $this->getRepository()->saveEntity($entity, false);
            $this->dispatchEvent("post_save", $entity, $isNew, $event);

            if ((($k + 1) % $batchSize) === 0) {
                $this->em->flush();
            }
        }
        $this->em->flush();
    }

    /**
     * Determines if an entity is new or not
     *
     * @param mixed $entity
     *
     * @return bool
     */
    public function isNewEntity($entity)
    {
        if (method_exists($entity, 'getId')) {
            $isNew = ($entity->getId()) ? false : true;
        } else {
            $isNew = \Doctrine\ORM\UnitOfWork::STATE_NEW === $this->factory->getEntityManager()->getUnitOfWork()->getEntityState($entity);
        }

        return $isNew;
    }

    /**
     * Toggles entity publish status
     *
     * @param object $entity
     *
     * @return bool  Force browser refresh
     */
    public function togglePublishStatus($entity)
    {
        if (method_exists($entity, 'setIsPublished')) {
            $status = $entity->getPublishStatus();

            switch ($status) {
                case 'unpublished':
                    $entity->setIsPublished(true);
                    break;
                case 'published':
                case 'expired':
                case 'pending':
                    $entity->setIsPublished(false);
                    break;
            }

            //set timestamp changes
            $this->setTimestamps($entity, false, false);
        } elseif (method_exists($entity, 'setIsEnabled')) {
            $enabled    = $entity->getIsEnabled();
            $newSetting = ($enabled) ? false : true;
            $entity->setIsEnabled($newSetting);
        }

        //hit up event listeners
        $event = $this->dispatchEvent("pre_save", $entity, false);
        $this->getRepository()->saveEntity($entity);
        $this->dispatchEvent("post_save", $entity, false, $event);

        return false;
    }


    /**
     * Set timestamps and user ids
     *
     * @param object $entity
     * @param bool   $isNew
     * @param bool   $unlock
     */
    public function setTimestamps(&$entity, $isNew, $unlock = true)
    {
        $user = $this->factory->getUser(true);
        if ($isNew) {
            if (method_exists($entity, 'setDateAdded')) {
                $entity->setDateAdded(new \DateTime());
            }

            if ($user instanceof User) {
                if (method_exists($entity, 'setCreatedBy')) {
                    $entity->setCreatedBy($user);
                } elseif (method_exists($entity, 'setCreatedByUser')) {
                    $entity->setCreatedByUser($user->getName());
                }
            }
        } else {
            if (method_exists($entity, 'setDateModified')) {
                $entity->setDateModified(new \DateTime());
            }

            if ($user instanceof User) {
                if (method_exists($entity, 'setModifiedBy')) {
                    $entity->setModifiedBy($user);
                } elseif (method_exists($entity, 'setModifiedByUser')) {
                    $entity->setModifiedByUser($user->getName());
                }
            }
        }

        //unlock the row if applicable
        if ($unlock && method_exists($entity, 'setCheckedOut')) {
            $entity->setCheckedOut(null);
            $entity->setCheckedOutBy(null);
        }
    }

    /**
     * Delete an entity
     *
     * @param object $entity
     *
     * @return void
     */
    public function deleteEntity($entity)
    {
        //take note of ID before doctrine wipes it out
        $id = $entity->getId();
        $event = $this->dispatchEvent("pre_delete", $entity);
        $this->getRepository()->deleteEntity($entity);
        //set the id for use in events
        $entity->deletedId = $id;
        $this->dispatchEvent("post_delete", $entity, false, $event);
    }

    /**
     * Delete an array of entities
     *
     * @param array $ids
     *
     * @return array
     */
    public function deleteEntities($ids)
    {
        $entities = array();
        //iterate over the results so the events are dispatched on each delete
        $batchSize = 20;
        foreach ($ids as $k => $id) {
            $entity = $this->getEntity($id);
            $entities[$id] = $entity;
            if ($entity !== null) {
                $event = $this->dispatchEvent("pre_delete", $entity);
                $this->getRepository()->deleteEntity($entity, false);
                //set the id for use in events
                $entity->deletedId = $id;
                $this->dispatchEvent("post_delete", $entity, false, $event);
            }
            if ((($k + 1) % $batchSize) === 0) {
                $this->em->flush();
            }
        }
        $this->em->flush();
        //retrieving the entities while here so may as well return them so they can be used if needed
        return $entities;
    }

    /**
     * Creates the appropriate form per the model
     *
     * @param object                              $entity
     * @param \Symfony\Component\Form\FormFactory $formFactory
     * @param string|null                         $action
     * @param array                               $options
     *
     * @return \Symfony\Component\Form\Form
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function createForm($entity, $formFactory, $action = null, $options = array())
    {
        throw new NotFoundHttpException('Form object not found.');
    }

    /**
     * Dispatches events for child classes
     *
     * @param string $action
     * @param object $entity
     * @param bool   $isNew
     * @param bool   $event
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, $event = false)
    {
        //...
    }

    /**
     * Set default subject for user contact form
     *
     * @param string $subject
     * @param object $entity
     *
     * @return mixed
     */
    public function getUserContactSubject($subject, $entity)
    {
        switch ($subject) {
            case 'locked':
                $msg = 'mautic.user.user.contact.locked';
                break;
            default:
                $msg = 'mautic.user.user.contact.regarding';
                break;
        }

        $nameGetter = $this->getNameGetter();
        $subject    = $this->translator->trans($msg, array(
            '%entityName%' => $entity->$nameGetter(),
            '%entityId%'   => $entity->getId()
        ));

        return $subject;
    }

    /**
     * Returns the function used to name the entity
     *
     * @return string
     */
    public function getNameGetter()
    {
        return "getName";
    }
}
