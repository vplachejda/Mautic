<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\PageBundle\Entity\Trackable;

/**
 * Class BuilderSubscriber
 *
 * @package Mautic\EmailBundle\EventListener
 */
class BuilderSubscriber extends CommonSubscriber
{
    /**
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(
            EmailEvents::EMAIL_ON_BUILD   => array('onEmailBuild', 0),
            EmailEvents::EMAIL_ON_SEND    => array(
                array('onEmailGenerate', 0),
                // Ensure this is done last in order to catch all tokenized URLs
                array('convertUrlsToTokens', -9999)
            ),
            EmailEvents::EMAIL_ON_DISPLAY => array(
                array('onEmailGenerate', 0),
                // Ensure this is done last in order to catch all tokenized URLs
                array('convertUrlsToTokens', -9999)
            )
        );
    }

    /**
     * @param EmailBuilderEvent $event
     */
    public function onEmailBuild(EmailBuilderEvent $event)
    {
        if ($event->tokenSectionsRequested()) {
            //add email tokens
            $content = $this->templating->render('MauticEmailBundle:SubscribedEvents\EmailToken:token.html.php');
            $event->addTokenSection('email.emailtokens', 'mautic.email.builder.index', $content);
        }

        if ($event->abTestWinnerCriteriaRequested()) {
            //add AB Test Winner Criteria
            $openRate = array(
                'group'    => 'mautic.email.stats',
                'label'    => 'mautic.email.abtest.criteria.open',
                'callback' => '\Mautic\EmailBundle\Helper\AbTestHelper::determineOpenRateWinner'
            );
            $event->addAbTestWinnerCriteria('email.openrate', $openRate);

            $clickThrough = array(
                'group'    => 'mautic.email.stats',
                'label'    => 'mautic.email.abtest.criteria.clickthrough',
                'callback' => '\Mautic\EmailBundle\Helper\AbTestHelper::determineClickthroughRateWinner'
            );
            $event->addAbTestWinnerCriteria('email.clickthrough', $clickThrough);
        }

        $tokens = array(
            '{unsubscribe_text}' => $this->translator->trans('mautic.email.token.unsubscribe_text'),
            '{webview_text}'     => $this->translator->trans('mautic.email.token.webview_text'),
            '{signature}'        => $this->translator->trans('mautic.email.token.signature')
        );

        if ($event->tokensRequested(array_keys($tokens))) {
            $event->addTokens(
                $event->filterTokens($tokens),
                true
            );
        }

        // these should not allow visual tokens
        $tokens = array(
            '{unsubscribe_url}' => $this->translator->trans('mautic.email.token.unsubscribe_url'),
            '{webview_url}'     => $this->translator->trans('mautic.email.token.webview_url')
        );
        if ($event->tokensRequested(array_keys($tokens))) {
            $event->addTokens(
                $event->filterTokens($tokens)
            );
        }
    }

    /**
     * @param EmailSendEvent $event
     */
    public function onEmailGenerate(EmailSendEvent $event)
    {
        $idHash = $event->getIdHash();
        $lead   = $event->getLead();

        if ($idHash == null) {
            // Generate a bogus idHash to prevent errors for routes that may include it
            $idHash = uniqid();
        }
        $model = $this->factory->getModel('email');

        $unsubscribeText = $this->factory->getParameter('unsubscribe_text');
        if (!$unsubscribeText) {
            $unsubscribeText = $this->translator->trans('mautic.email.unsubscribe.text', array('%link%' => '|URL|'));
        }
        $unsubscribeText = str_replace('|URL|', $model->buildUrl('mautic_email_unsubscribe', array('idHash' => $idHash)), $unsubscribeText);
        $event->addToken('{unsubscribe_text}', $unsubscribeText);

        $event->addToken('{unsubscribe_url}', $model->buildUrl('mautic_email_unsubscribe', array('idHash' => $idHash)));

        $webviewText = $this->factory->getParameter('webview_text');
        if (!$webviewText) {
            $webviewText = $this->translator->trans('mautic.email.webview.text', array('%link%' => '|URL|'));
        }
        $webviewText = str_replace('|URL|', $model->buildUrl('mautic_email_webview', array('idHash' => $idHash)), $webviewText);
        $event->addToken('{webview_text}', $webviewText);

        // Show public email preview if the lead is not known to prevent 404
        if (empty($lead['id']) && $event->getEmail()) {
            $event->addToken('{webview_url}', $model->buildUrl('mautic_email_preview', array('objectId' => $event->getEmail()->getId())));
        } else {
            $event->addToken('{webview_url}', $model->buildUrl('mautic_email_webview', array('idHash' => $idHash)));
        }

        $signatureText = $this->factory->getParameter('default_signature_text');
        $fromName      = $this->factory->getParameter('mailer_from_name');

        if (!empty($lead['owner_id'])) {
            $owner = $this->factory->getModel('lead')->getRepository()->getLeadOwner($lead['owner_id']);
            if ($owner && !empty($owner['signature'])) {
                $fromName      = $owner['first_name'].' '.$owner['last_name'];
                $signatureText = $owner['signature'];
            }
        }

        $signatureText = str_replace('|FROM_NAME|', $fromName, nl2br($signatureText));
        $event->addToken('{signature}', $signatureText);
    }

    /**
     * @param EmailSendEvent $event
     *
     * @return array
     */
    public function convertUrlsToTokens(EmailSendEvent $event)
    {
        if ($event->isInternalSend()) {
            // Don't convert for previews, example emails, etc

            return;
        }

        /** @var \Mautic\PageBundle\Model\TrackableModel $trackableModel */
        $trackableModel = $this->factory->getModel('page.trackable');
        /** @var \Mautic\PageBundle\Model\RedirectModel $redirectModel */
        $redirectModel = $this->factory->getModel('page.redirect');

        $email   = $event->getEmail();
        $emailId = ($email) ? $email->getId() : null;

        $clickthrough = $event->generateClickthrough();
        $trackables   = $this->parseContentForUrls($event, $emailId);

        /**
         * @var string    $token
         * @var Trackable $trackable
         */
        foreach ($trackables as $token => $trackable) {
            $url = ($trackable instanceof Trackable)
                ?
                $trackableModel->generateTrackableUrl($trackable, $clickthrough)
                :
                $redirectModel->generateRedirectUrl($trackable, $clickthrough);

            $event->addToken($token, $url);
        }
    }

    /**
     * Parses content for URLs and tokens
     *
     * @param EmailSendEvent $event
     * @param                $emailId
     *
     * @return mixed
     */
    protected function parseContentForUrls(EmailSendEvent $event, $emailId)
    {
        static $convertedContent = array();

        // Prevent parsing the exact same content over and over
        if (!isset($convertedContent[$event->getContentHash()])) {
            $html = $event->getContent();
            $text = $event->getPlainText();

            /** @var \Mautic\PageBundle\Model\TrackableModel $trackableModel */
            $trackableModel = $this->factory->getModel('page.trackable');
            $contentTokens  = $event->getTokens();

            list($content, $trackables) = $trackableModel->parseContentForTrackables(
                array($html, $text),
                $contentTokens,
                ($emailId) ? 'email' : null,
                $emailId
            );

            list($html, $text) = $content;
            unset($content);

            if ($html) {
                $event->setContent($html);
            }
            if ($text) {
                $event->setPlainText($text);
            }

            $convertedContent[$event->getContentHash()] = $trackables;

            // Don't need to preserve Trackable or Redirect entities in memory
            $this->factory->getEntityManager()->clear('Mautic\PageBundle\Entity\Redirect');
            $this->factory->getEntityManager()->clear('Mautic\PageBundle\Entity\Trackable');

            unset($html, $text, $trackables);
        }

        return $convertedContent[$event->getContentHash()];
    }
}
