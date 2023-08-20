<?php

namespace Mautic\EmailBundle\Controller;

use Mautic\CacheBundle\Cache\CacheProvider;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Controller\VariantAjaxControllerTrait;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\EmailBundle\Helper\PlainTextHelper;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\PageBundle\Form\Type\AbTestPropertiesType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;

class AjaxController extends CommonAjaxController
{
    use VariantAjaxControllerTrait;
    use AjaxLookupControllerTrait;

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getAbTestFormAction(Request $request, FormFactoryInterface $formFactory)
    {
        return $this->getAbTestForm(
            $request,
            $formFactory,
            'email',
            AbTestPropertiesType::class,
            'email_abtest_settings',
            'emailform',
            '@MauticEmail/AbTest/form.html.twig',
            ['@MauticEmail/AbTest/form.html.twig', '@MauticEmail/FormTheme/Email/layout.html.twig']
        );
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function sendBatchAction(Request $request)
    {
        $dataArray = ['success' => 0];

        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model    = $this->getModel('email');
        $objectId = $request->request->get('id', 0);
        $pending  = $request->request->get('pending', 0);
        $limit    = $request->request->get('batchlimit', 100);

        if ($objectId && $entity = $model->getEntity($objectId)) {
            $dataArray['success'] = 1;
            $session              = $request->getSession();
            $progress             = $session->get('mautic.email.send.progress', [0, (int) $pending]);
            $stats                = $session->get('mautic.email.send.stats', ['sent' => 0, 'failed' => 0, 'failedRecipients' => []]);
            $inProgress           = $session->get('mautic.email.send.active', false);

            if ($pending && !$inProgress && $entity->isPublished()) {
                $session->set('mautic.email.send.active', true);
                [$batchSentCount, $batchFailedCount, $batchFailedRecipients] = $model->sendEmailToLists($entity, null, $limit);

                $progress[0] += ($batchSentCount + $batchFailedCount);
                $stats['sent'] += $batchSentCount;
                $stats['failed'] += $batchFailedCount;

                foreach ($batchFailedRecipients as $emails) {
                    $stats['failedRecipients'] = $stats['failedRecipients'] + $emails;
                }

                $session->set('mautic.email.send.progress', $progress);
                $session->set('mautic.email.send.stats', $stats);
                $session->set('mautic.email.send.active', false);
            }

            $dataArray['percent']  = ($progress[1]) ? ceil(($progress[0] / $progress[1]) * 100) : 100;
            $dataArray['progress'] = $progress;
            $dataArray['stats']    = $stats;
        }

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * Called by parent::getBuilderTokensAction().
     *
     * @return array
     */
    protected function getBuilderTokens($query)
    {
        /** @var \Mautic\EmailBundle\Model\EmailModel $model */
        $model = $this->getModel('email');

        return $model->getBuilderComponents(null, ['tokens'], (string) $query);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function generatePlaintTextAction(Request $request)
    {
        $custom = $request->request->get('custom');
        $id     = $request->request->get('id');

        $parser = new PlainTextHelper(
            [
                'base_url' => $request->getSchemeAndHttpHost().$request->getBasePath(),
            ]
        );

        $dataArray = [
            'text' => $parser->setHtml($custom)->getText(),
        ];

        return $this->sendJsonResponse($dataArray);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function getAttachmentsSizeAction(Request $request)
    {
        $assets = $request->query->get('assets') ?? [];
        $size   = 0;
        if ($assets) {
            /** @var \Mautic\AssetBundle\Model\AssetModel $assetModel */
            $assetModel = $this->getModel('asset');
            $size       = $assetModel->getTotalFilesize($assets);
        }

        return $this->sendJsonResponse(['size' => $size]);
    }

    /**
     * Tests monitored email connection settings.
     *
     * @return JsonResponse
     */
    public function testMonitoredEmailServerConnectionAction(Request $request)
    {
        $dataArray = ['success' => 0, 'message' => ''];

        if ($this->user->isAdmin()) {
            $settings = $request->request->all();

            if (empty($settings['password'])) {
                $existingMonitoredSettings = $this->coreParametersHelper->get('monitored_email');
                if (is_array($existingMonitoredSettings) && (!empty($existingMonitoredSettings[$settings['mailbox']]['password']))) {
                    $settings['password'] = $existingMonitoredSettings[$settings['mailbox']]['password'];
                }
            }

            /** @var \Mautic\EmailBundle\MonitoredEmail\Mailbox $helper */
            $helper = $this->factory->getHelper('mailbox');

            try {
                $helper->setMailboxSettings($settings, false);
                $folders = $helper->getListingFolders('');
                if (!empty($folders)) {
                    $dataArray['folders'] = '';
                    foreach ($folders as $folder) {
                        $dataArray['folders'] .= "<option value=\"$folder\">$folder</option>\n";
                    }
                }
                $dataArray['success'] = 1;
                $dataArray['message'] = $this->translator->trans('mautic.core.success');
            } catch (\Exception $e) {
                $dataArray['message'] = $this->translator->trans($e->getMessage());
            }
        }

        return $this->sendJsonResponse($dataArray);
    }

    public function sendTestEmailAction(TransportInterface $transport, UserHelper $userHelper, CoreParametersHelper $parametersHelper): Response
    {
        $user  = $userHelper->getUser();
        $email = (new MauticMessage())
            ->subject($this->translator->trans('mautic.email.config.mailer.transport.test_send.subject'))
            ->text($this->translator->trans('mautic.email.config.mailer.transport.test_send.body'))
            ->from(new Address($parametersHelper->get('mailer_from_email'), $parametersHelper->get('mailer_from_name') ?: ''))
            ->to(new Address($user->getEmail(), trim($user->getFirstName().' '.$user->getLastName()) ?: ''));

        $success = 1;
        $message = $this->translator->trans('mautic.core.success');

        try {
            $transport->send($email);
        } catch (TransportExceptionInterface $e) {
            $success = 0;
            $message = $e->getMessage();
        }

        return $this->sendJsonResponse(['success' => $success, 'message' => $message]);
    }

    public function getEmailCountStatsAction(Request $request)
    {
        /** @var EmailModel $model */
        $model = $this->getModel('email');

        $id  = $request->query->get('id');
        $ids = $request->query->get('ids');

        // Support for legacy calls
        if (!$ids && $id) {
            $ids = [$id];
        }

        $data = [];
        foreach ($ids as $id) {
            if ($email = $model->getEntity($id)) {
                $pending = $model->getPendingLeads($email, null, true);
                $queued  = $model->getQueuedCounts($email);

                $data[] = [
                    'id'          => $email->getId(),
                    'pending'     => 'list' === $email->getEmailType() && $pending ? $this->translator->trans(
                        'mautic.email.stat.leadcount',
                        ['%count%' => $pending]
                    ) : 0,
                    'queued'      => ($queued) ? $this->translator->trans('mautic.email.stat.queued', ['%count%' => $queued]) : 0,
                    'sentCount'   => $this->translator->trans('mautic.email.stat.sentcount', ['%count%' => $email->getSentCount(true)]),
                    'readCount'   => $this->translator->trans('mautic.email.stat.readcount', ['%count%' => $email->getReadCount(true)]),
                    'readPercent' => $this->translator->trans('mautic.email.stat.readpercent', ['%count%' => $email->getReadPercentage(true)]),
                ];
            }
        }

        // Support for legacy calls
        if ($request->get('id') && !empty($data[0])) {
            $data = $data[0];
        } else {
            $data = [
                'success' => 1,
                'stats'   => $data,
            ];
        }

        return new JsonResponse($data);
    }

    public function getEmailDeliveredCountAction(Request $request, CacheProvider $cacheProvider): JsonResponse
    {
        $emailId = (int) InputHelper::clean($request->query->get('id'));

        if (0 === $emailId) {
            return $this->sendJsonResponse([
                'success' => 0,
                'message' => $this->translator->trans('mautic.core.error.badrequest'),
            ], 400);
        }

        $cacheTimeout = (int) $this->coreParametersHelper->get('cached_data_timeout');
        $cacheItem    = $cacheProvider->getItem('email.stats.delivered.'.$emailId);

        if ($cacheItem->isHit()) {
            $deliveredCount = $cacheItem->get();
        } else {
            /** @var EmailModel $model */
            $model = $this->getModel('email');

            $email = $model->getEntity($emailId);
            if (null === $email) {
                return $this->sendJsonResponse([
                    'success' => 0,
                    'message' => $this->translator->trans('mautic.api.call.notfound'),
                ], 404);
            }
            $deliveredCount = $model->getDeliveredCount($email);
            $cacheItem->set($deliveredCount);
            $cacheItem->expiresAfter($cacheTimeout * 60);
            $cacheProvider->save($cacheItem);
        }

        return $this->sendJsonResponse([
            'success'     => 1,
            'delivered'   => $deliveredCount,
        ]);
    }
}
