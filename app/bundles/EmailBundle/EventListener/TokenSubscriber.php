<?php
/**
 * @package     Mautic
 * @copyright   2015 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\EventListener;

use Doctrine\ORM\Internal\Hydration\ObjectHydrator;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\LeadBundle\Entity\Lead;

/**
 * Class TokenSubscriber
 */
class TokenSubscriber extends CommonSubscriber
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            EmailEvents::EMAIL_ON_SEND     => array('decodeTokens', 254),
            EmailEvents::EMAIL_ON_DISPLAY  => array('decodeTokens', 254),
            EmailEvents::TOKEN_REPLACEMENT => ['onTokenReplacement', 254]
        );
    }

    /**
     * @param EmailSendEvent $event
     *
     * @return void
     */
    public function decodeTokens(EmailSendEvent $event)
    {
        // Find and replace encoded tokens for trackable URL conversion
        $content = $event->getContent();
        $content = preg_replace('/(%7B)(.*?)(%7D)/i', '{$2}', $content, -1, $count);
        $event->setContent($content);

        if ($plainText = $event->getPlainText()) {
            $plainText = preg_replace('/(%7B)(.*?)(%7D)/i', '{$2}', $plainText);
            $event->setPlainText($plainText);
        }

        $lead = $event->getLead();
        $dynamicContentAsArray = $event->getEmail()->getDynamicContentAsArray();

        if (! empty($dynamicContentAsArray)) {
            $tokenEvent = new TokenReplacementEvent(null, $lead, ['lead' => null, 'dynamicContent' => $dynamicContentAsArray]);
            $this->dispatcher->dispatch(EmailEvents::TOKEN_REPLACEMENT, $tokenEvent);
            $event->addTokens($tokenEvent->getTokens());
        }
    }

    /**
     * @param TokenReplacementEvent $event
     */
    public function onTokenReplacement(TokenReplacementEvent $event)
    {
        $clickthrough = $event->getClickthrough();

        if (! array_key_exists('dynamicContent', $clickthrough)) {
            return;
        }

        $lead      = $event->getLead();
        $tokenData = $clickthrough['dynamicContent'];

        if ($lead instanceof Lead) {
            $lead = $lead->getProfileFields();
        }

        foreach ($tokenData as $data) {
            $defaultContent = $data['content'];
            $filterContent  = null;

            foreach ($data['filters'] as $filter) {
                if ($this->matchFilterForLead($filter['filters'], $lead)) {
                    $filterContent = $filter['content'];
                }
            }

            $event->addToken('{dynamiccontent="'.$data['tokenName'].'"}', $filterContent ?: $defaultContent);
        }
    }

    /**
     * @param array $filter
     * @param array $lead
     *
     * @return bool
     */
    private function matchFilterForLead(array $filter, array $lead)
    {
        $groups   = [];
        $groupNum = 0;

        foreach ($filter as $key => $data) {
            if (!array_key_exists($data['field'], $lead)) {
                continue;
            }

            /**
             * Split the filters into groups based on the glue.
             * The first filter and any filters whose glue is
             * "or" will start a new group.
             */
            if ($groupNum === 0 || $data['glue'] === 'or') {
                $groupNum++;
                $groups[$groupNum] = null;
            }

            /**
             * If the group has been marked as false, there
             * is no need to continue checking the others
             * in the group.
             */
            if ($groups[$groupNum] === false) {
                continue;
            }

            $leadVal   = $lead[$data['field']];
            $filterVal = $data['filter'];

            if (!is_array($filterVal) && in_array($data['type'], ['number', 'boolean'])) {
                $filterVal = $data['type'] === 'number' ? (float) $filterVal : (bool) $filterVal;
            }

            if (in_array($data['operator'], ['like', '!like'])) {
                $leadVal = (string) $leadVal;
                $filterVal = (string) $filterVal;
            }

            switch ($data['operator']) {
                case '=':
                    $groups[$groupNum] = $leadVal === $filterVal;
                    break;
                case '!=':
                    $groups[$groupNum] = $leadVal !== $filterVal;
                    break;
                case 'gt':
                    $groups[$groupNum] = $leadVal > $filterVal;
                    break;
                case 'gte':
                    $groups[$groupNum] = $leadVal >= $filterVal;
                    break;
                case 'lt':
                    $groups[$groupNum] = $leadVal < $filterVal;
                    break;
                case 'lte':
                    $groups[$groupNum] = $leadVal <= $filterVal;
                    break;
                case 'empty':
                    $groups[$groupNum] = empty($leadVal);
                    break;
                case '!empty':
                    $groups[$groupNum] = !empty($leadVal);
                    break;
                case 'like':
                    $groups[$groupNum] = strpos($leadVal, $filterVal) !== false;
                    break;
                case '!like':
                    $groups[$groupNum] = strpos($leadVal, $filterVal) === false;
                    break;
            }
        }

        return in_array(true, $groups);
    }
}
