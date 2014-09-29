<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 *
 * //TODO create support for hashed api requests for non-ssl sites?
 */

namespace Mautic\ApiBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Util\Codes;
use JMS\Serializer\SerializationContext;
use Mautic\CoreBundle\Controller\MauticController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class CommonApiController extends FOSRestController implements MauticController
{

    /**
     * @var
     */
    protected $request;

    /**
     * @var
     */

    protected $factory;

    /**
     * @var
     */
    protected $security;

    /**
     * @var
     */
    protected $model;

    /**
     * @var
     */
    protected $entityNameOne;

    /**
     * @var
     */
    protected $entityNameMulti;

    /**
     * @var
     */
    protected $entityClass;

    /**
     * @var array
     */
    protected $serializerGroups = array();

    /**
     * Initialize some variables
     *
     * @param FilterControllerEvent $event
     */
    public function initialize(FilterControllerEvent $event)
    {
        $this->security = $this->factory->getSecurity();
    }

    /**
     * @param Request $request
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param MauticFactory $factory
     */
    public function setFactory(MauticFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Obtains a list of entities as defined by the API URL
     *
     * @ApiDoc(
     *   description = "Obtains a list of entities as defined by the API URL",
     *   statusCodes = {
     *     200 = "Returned when successful"
     *   },
     *   filters={
     *      {"name"="start", "dataType"="integer", "required"=false, "description"="Set the record to start with."},
     *      {"name"="limit", "dataType"="integer", "required"=false, "description"="Limit the number of records to retrieve."},
     *      {"name"="filter", "dataType"="string", "required"=false, "description"="A string in which to filter the results by."},
     *      {"name"="orderBy", "dataType"="string", "required"=false, "description"="Table column in which to sort the results by."},
     *      {"name"="orderByDir", "dataType"="string", "required"=false, "description"="Direction in which to sort results by. (ASC|DESC)"}
     *   }
     * )
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getEntitiesAction()
    {
        $args = array(
            'start'      => $this->request->query->get('start', 0),
            'limit'      => $this->request->query->get('limit', $this->factory->getParameter('default_pagelimit')),
            'filter'     => $this->request->query->get('search', ''),
            'orderBy'    => $this->request->query->get('orderBy', ''),
            'orderByDir' => $this->request->query->get('orderByDir', 'ASC')
        );

        $results = $this->model->getEntities($args);
        //we have to convert them from paginated proxy functions to entities in order for them to be
        //returned by the serializer/rest bundle
        $entities = array();
        foreach ($results as $r) {
            $entities[] = $r;
        }
        $view = $this->view(array($this->entityNameMulti => $entities), Codes::HTTP_OK);
        if (!empty($this->serializerGroups)) {
            $context = SerializationContext::create()->setGroups($this->serializerGroups);
            $view->setSerializationContext($context);
        }
        return $this->handleView($view);
    }

    /**
     * Obtains a specific entity as defined by the API URL
     *
     * @ApiDoc(
     *   description = "Obtains a specific entity as defined by the API URL",
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     404 = "Returned if the entity was not found"
     *   }
     * )
     *
     * @param int $id Entity ID
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function getEntityAction($id)
    {
        $entity = $this->model->getEntity($id);
        if (!$entity instanceof $this->entityClass) {
            throw new NotFoundHttpException($this->get('translator')->trans('mautic.api.call.notfound'));
        }

        $view = $this->view(array($this->entityNameOne => $entity), Codes::HTTP_OK);
        if (!empty($this->serializerGroups)) {
            $context = SerializationContext::create()->setGroups($this->serializerGroups);
            $view->setSerializationContext($context);
        }
        return $this->handleView($view);
    }

    /**
     * Creates a new entity
     *
     * @ApiDoc(
     *   description = "Creates a new entity",
     *   statusCodes = {
     *     200 = "Returned if successful",
     *     400 = "Returned if validation failed"
     *   }
     * )
     *
     * @return Response
     */
    public function newEntityAction()
    {
        $entity     = $this->model->getEntity();
        $parameters = $this->request->request->all();
        return $this->processForm($entity, $parameters, 'POST');
    }


    /**
     * Edits an existing entity or creates one on PUT if it doesn't exist
     *
     * @ApiDoc(
     *   description = "Edits an existing entity or creates one on PUT if it doesn't exist",
     *   statusCodes = {
     *     200 = "Returned if successful edit",
     *     201 = "Returned if a new entity was created"
     *     400 = "Returned if validation failed"
     *   }
     * )
     *
     * @param int $id Entity ID
     * @return Response
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function editEntityAction($id)
    {
        $entity     = $this->model->getEntity($id);
        $parameters = $this->request->request->all();
        $method     = $this->request-getMethod();

        if (!$entity) {
            if ($method === "PATCH") {
                //PATCH requires that an entity exists
                throw new NotFoundHttpException($this->get('translator')->trans('mautic.api.call.notfound'));
            } else {
                //PUT can create a new entity if it doesn't exist
                $entity = $this->model->getEntity();
            }
        }

        return $this->processForm($entity, $parameters, $method);
    }

    /**
     * Deletes an entity
     *
     * @ApiDoc(
     *   description = "Deletes an entity",
     *   statusCodes = {
     *     200 = "Returned if successful",
     *     404 = "Returned if ID is not found"
     *   }
     * )
     *
     * @param int $id Entity ID
     * @return Response
     */
    public function deleteEntityAction($id)
    {
        $entity = $this->model->getEntity($id);
        if (null !== $entity) {
            $this->model->deleteEntity($entity);

            $view = $this->view(array($this->entityNameOne => $entity), Codes::HTTP_OK);
            if (!empty($this->serializerGroups)) {
                $context = SerializationContext::create()->setGroups($this->serializerGroups);
                $view->setSerializationContext($context);
            }

            return $this->handleView($view);
        } else {
            throw new NotFoundHttpException($this->get('translator')->trans('mautic.api.call.notfound'));
        }
    }

    /**
     * Processes API Form
     *
     * @param        $entity
     * @param null   $parameters
     * @param string $method
     * @return Response
     */
    protected function processForm($entity, $parameters = null, $method = 'PUT')
    {
        if ($parameters === null) {
            //get from request
            $parameters = $this->request->request->all();
        }

        //unset the ID in the parameters if set as this will cause the form to fail
        if (isset($parameters['id'])) {
            unset($parameters['id']);
        }

        //is an entity being updated or created?
        $statusCode = $entity->getId() ? Codes::HTTP_OK : Codes::HTTP_CREATED;

        $form = $this->model->createForm($entity, $this->get('form.factory'));

        $form->submit($parameters, 'PATCH' !== $method);

        if ($form->isValid()) {
            $this->model->saveEntity($entity);
            $this->postProcessForm($entity);
            $headers = array();
            //return the newly created entities location if applicable
            if (Codes::HTTP_CREATED === $statusCode) {
                $headers['Location'] = $this->generateUrl(
                    'mautic_api_get' . $this->entityNameOne, array('id' => $entity->getId()),
                    true
                );
            }

            $view = $this->view(array($this->entityNameOne => $entity), $statusCode, $headers);
            if (!empty($this->serializerGroups)) {
                $context = SerializationContext::create()->setGroups($this->serializerGroups);
                $view->setSerializationContext($context);
            }
        } else {
            $view = $this->view($form, Codes::HTTP_BAD_REQUEST);
        }

        return $this->handleView($view);
    }

    /*
     * Give the controller an opportunity to process the entity before sending it as a response
     */
    protected function postProcessForm(&$entity)
    {
        //...
    }

    /**
     * Returns a 403 Access Denied
     *
     * @return Response
     */
    public function accessDenied()
    {
        $view = $this->view(
            array(
                "error" => $this->get('translator')->trans('mautic.core.error.accessdenied')
            ), Codes::HTTP_FORBIDDEN
        );
        return $this->handleView($view);
    }
}
