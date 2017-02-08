<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ChannelBundle\Controller;

use Mautic\ChannelBundle\Model\MessageModel;
use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\LeadBundle\Controller\EntityContactsTrait;
use Symfony\Component\Form\Form;

/**
 * Class MessageController.
 */
class MessageController extends AbstractStandardFormController
{
    use EntityContactsTrait;
    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        return $this->batchDeleteStandard();
    }

    /**
     * @param $objectId
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function cloneAction($objectId)
    {
        return $this->cloneStandard($objectId);
    }

    /**
     * @param      $objectId
     * @param bool $ignorePost
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function editAction($objectId, $ignorePost = false)
    {
        return $this->editStandard($objectId, $ignorePost);
    }

    /**
     * @param int $page
     *
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction($page = 1)
    {
        return $this->indexStandard($page);
    }

    /**
     * @return \Mautic\CoreBundle\Controller\Response|\Symfony\Component\HttpFoundation\JsonResponse
     */
    public function newAction()
    {
        return $this->newStandard();
    }

    /**
     * @param $objectId
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        return $this->viewStandard($objectId);
    }

    /**
     * @param $args
     * @param $action
     *
     * @return mixed
     */
    protected function getViewArguments(array $args, $action)
    {
        /** @var MessageModel $model */
        $model          = $this->getModel($this->getModelName());
        $viewParameters = [];
        switch ($action) {
            case 'index':
                $viewParameters = [
                    'headerTitle' => $this->get('translator')->trans('mautic.channel.messages'),
                    'listHeaders' => [
                        [
                            'text'  => 'mautic.core.channels',
                            'class' => 'visible-md visible-lg',
                        ],
                    ],
                    'listItemTemplate'  => 'MauticChannelBundle:Message:list_item.html.php',
                    'enableCloneButton' => true,
                ];

                break;
            case 'view':
                // Init the date range filter form
                $returnUrl = $this->generateUrl(
                    'mautic_message_action',
                    [
                        'objectAction' => 'view',
                        'objectId'     => $args['viewParameters']['item']->getId(),
                    ]
                );

                $dateRangeValues = $this->request->get('daterange', []);
                $dateRangeForm   = $this->get('form.factory')->create('daterange', $dateRangeValues, ['action' => $returnUrl]);
                $dateFrom        = new \DateTime($dateRangeForm['date_from']->getData());
                $dateTo          = new \DateTime($dateRangeForm['date_to']->getData());

                $logs = $this->getModel('core.auditLog')->getLogForObject('message', $args['viewParameters']['item']->getId(), $args['viewParameters']['item']->getDateAdded());

                $chart       = new LineChart(null, $dateFrom, $dateTo);
                $eventCounts = $model->getLeadStatsPost($args['viewParameters']['item']->getId(), $dateFrom, $dateTo);
                $chart->setDataset($this->get('translator')->trans('mautic.messages.marketing.messages.sent.stats', ['msg' => $args['viewParameters']['item']->getName()]), $eventCounts);

                $viewParameters = [
                    'logs'            => $logs,
                    'channels'        => $model->getChannels(),
                    'channelContents' => $model->getMessageChannels($args['viewParameters']['item']->getId()),
                    'dateRangeForm'   => $dateRangeForm->createView(),
                    'eventCounts'     => $chart->render(),
                    'messagedLeads'   => $this->forward(
                        'MauticChannelBundle:Message:contacts',
                        [
                            'objectId'   => $args['viewParameters']['item']->getId(),
                            'page'       => $this->get('session')->get('mautic.'.$this->getSessionBase().'.page', 1),
                            'ignoreAjax' => true,
                        ]
                    )->getContent(),
                ];
                break;
            case 'new':
            case 'edit':
            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $this->template = false;
            } else {
                $this->template = true;
            }
                $viewParameters = [
                    'channels' => $model->getChannels(),
                ];

                break;
        }

        $args['viewParameters'] = array_merge($args['viewParameters'], $viewParameters);

        return $args;
    }

    /**
     * @param array $args
     * @param       $action
     *
     * @return array
     */
    protected function getPostActionRedirectArguments(array $args, $action)
    {
        switch ($action) {
            default:
                $args['contentTemplate'] = $this->getControllerBase().':index';
                $args['returnUrl']       = $this->generateUrl($this->getIndexRoute());
                break;
        }

        return $args;
    }

    /**
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function deleteAction($objectId)
    {
        return $this->deleteStandard($objectId);
    }

    /**
     * {@inheritdoc}
     */
    protected function getControllerBase()
    {
        return 'MauticChannelBundle:Message';
    }

    /**
     * @param Form $form
     * @param      $view
     *
     * @return \Symfony\Component\Form\FormView
     */
    protected function getFormView(Form $form, $view)
    {
        $themes = ['MauticChannelBundle:FormTheme'];
        /** @var MessageModel $model */
        $model    = $this->getModel($this->getModelName());
        $channels = $model->getChannels();
        foreach ($channels as $channel) {
            if (isset($channel['formTheme'])) {
                $themes[] = $channel['formTheme'];
            }
        }

        return $this->setFormTheme($form, 'MauticChannelBundle:Message:form.html.php', $themes);
    }

    /**
     * {@inheritdoc}
     */
    protected function getJsLoadMethodPrefix()
    {
        return 'messages';
    }

    /**
     * {@inheritdoc}
     */
    protected function getModelName()
    {
        return 'channel.message';
    }

    /**
     * {@inheritdoc}
     */
    protected function getRouteBase()
    {
        return 'message';
    }

    /***
     * @param null $objectId
     *
     * @return string
     */
    protected function getSessionBase($objectId = null)
    {
        return 'message';
    }

    /**
     * {@inheritdoc}
     */
    protected function getTranslationBase()
    {
        return 'mautic.channel.message';
    }

    /**
     * @param     $objectId
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function contactsAction($objectId, $page = 1)
    {
        return $this->generateContactsGrid(
            $objectId,
            $page,
            'channel:messages:view',
            'message',
            'campaign_lead_event_log',
            'message',
            'channel_id'
        );
    }
}
