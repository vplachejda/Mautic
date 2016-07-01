<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ApiBundle\Model;

use Mautic\ApiBundle\ApiEvents;
use Mautic\ApiBundle\Entity\oAuth1\Consumer;
use Mautic\ApiBundle\Event\ClientEvent;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\ApiBundle\Entity\oAuth2\Client;
use Mautic\UserBundle\Entity\User;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Class ClientModel
 */
class ClientModel extends FormModel
{

    /**
     * @var string
     */
    private $apiMode;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var null|\Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * ClientModel constructor.
     *
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->apiMode = $this->request->get('api_mode', $this->request->getSession()->get('mautic.client.filter.api_mode', 'oauth1a'));
    }

    /**
     * @param $apiMode
     */
    public function setApiMode($apiMode)
    {
        $this->apiMode = $apiMode;
    }

    /**
     * @param Session $session
     */
    public function setSession(Session $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Mautic\ApiBundle\Entity\oAuth1\ConsumerRepository|\Mautic\ApiBundle\Entity\oAuth2\ClientRepository
     */
    public function getRepository()
    {
        if ($this->apiMode == 'oauth2') {
            return $this->em->getRepository('MauticApiBundle:oAuth2\Client');
        } else {
            return $this->em->getRepository('MauticApiBundle:oAuth1\Consumer');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionBase()
    {
        return 'api:clients';
    }

    /**
     * {@inheritdoc}
     *
     * @throws MethodNotAllowedHttpException
     */
    public function createForm($entity, $formFactory, $action = null, $options = array())
    {
        if (!$entity instanceof Client && !$entity instanceof Consumer) {
            throw new MethodNotAllowedHttpException(array('Client', 'Consumer'));
        }

        $params = (!empty($action)) ? array('action' => $action) : array();
        return $formFactory->create('client', $entity, $params);
    }

    /**
     * {@inheritdoc}
     *
     * @return null|Client|Consumer
     */
    public function getEntity($id = null)
    {
        if ($id === null) {
            return $this->apiMode == 'oauth2' ? new Client() : new Consumer();
        }

        return parent::getEntity($id);
    }


    /**
     * {@inheritdoc}
     *
     * @throws MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, Event $event = null)
    {
        if (!$entity instanceof Client && !$entity instanceof Consumer) {
            throw new MethodNotAllowedHttpException(array('Client', 'Consumer'));
        }

        switch ($action) {
            case "post_save":
                $name = ApiEvents::CLIENT_POST_SAVE;
                break;
            case "post_delete":
                $name = ApiEvents::CLIENT_POST_DELETE;
                break;
            default:
                return null;
        }

        if ($this->dispatcher->hasListeners($name)) {
            if (empty($event)) {
                $event = new ClientEvent($entity, $isNew);
                $event->setEntityManager($this->em);
            }
            $this->dispatcher->dispatch($name, $event);
            return $event;
        }

        return null;
    }

    /**
     * @param User $user
     *
     * @return array
     */
    public function getUserClients(User $user)
    {
        return $this->getRepository()->getUserClients($user);
    }

    /**
     * @param $entity
     *
     * @return void
     */
    public function revokeAccess($entity)
    {
        if (!$entity instanceof Client && !$entity instanceof Consumer) {
            throw new MethodNotAllowedHttpException(array('Client', 'Consumer'));
        }

        //remove the user from the client
        if ($this->apiMode == 'oauth2') {
            $entity->removeUser($this->user);
            $this->saveEntity($entity);
        } else {
            $this->getRepository()->deleteAccessTokens($entity, $this->user);
        }
    }
}
