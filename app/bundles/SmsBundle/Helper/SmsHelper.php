<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SmsBundle\Helper;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumber;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\SmsBundle\Event\SmsSendEvent;
use Mautic\SmsBundle\SmsEvents;

class SmsHelper
{
    /**
     * @var MauticFactory
     */
    protected $factory;

    /**
     * @param MauticFactory $factory
     */
    public function __construct(MauticFactory $factory)
    {
        $this->factory = $factory;
    }

    public function unsubscribe($number)
    {
        $phoneUtil = PhoneNumberUtil::getInstance();
        $phoneNumber = $phoneUtil->parse($number, 'US');
        $number = $phoneUtil->format($phoneNumber, PhoneNumberFormat::E164);

        /** @var \Mautic\LeadBundle\Entity\LeadRepository $repo */
        $repo = $this->factory->getEntityManager()->getRepository('MauticLeadBundle:Lead');

        $args = array(
            'filter' => array(
                'force' => array(
                    array(
                        'column' => 'mobile',
                        'expr' => 'eq',
                        'value' => $number
                    )
                )
            )
        );

        $leads = $repo->getEntities($args);

        if (! empty($leads)) {
            $lead = array_shift($leads);
        } else {
            // Try to find the lead based on the given phone number
            $args['filter']['force'][0]['column'] = 'phone';

            $leads = $repo->getEntities($args);

            if (! empty($leads)) {
                $lead = array_shift($leads);
            } else {
                return false;
            }
        }

        /** @var \Mautic\LeadBundle\Model\LeadModel $leadModel */
        $leadModel = $this->factory->getModel('lead.lead');

        return $leadModel->addDncForLead($lead, 'sms', null, DoNotContact::UNSUBSCRIBED);
    }

    /**
     * @param array $config
     * @param Lead $lead
     * @param MauticFactory $factory
     *
     * @return boolean
     */
    public static function send(array $config, Lead $lead, MauticFactory $factory)
    {
        /** @var \Mautic\LeadBundle\Model\LeadModel $leadModel */
        $leadModel = $factory->getModel('lead.lead');

        if ($leadModel->isContactable($lead, 'sms') !== DoNotContact::IS_CONTACTABLE) {
            return false;
        }

        $smsUsername = $factory->getParameter('sms_username');
        $smsPassword = $factory->getParameter('sms_password');
        $sendingPhoneNumber = $factory->getParameter('sms_sending_phone_number');
        $leadPhoneNumber = $lead->getFieldValue('mobile');

        if (empty($leadPhoneNumber)) {
            $leadPhoneNumber = $lead->getFieldValue('phone');
        }

        if (empty($leadPhoneNumber)) {
            return false;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        $phoneNumber = $phoneUtil->parse($leadPhoneNumber, 'US');
        $leadPhoneNumber = $phoneUtil->format($phoneNumber, PhoneNumberFormat::E164);

        $client = new \Services_Twilio($smsUsername, $smsPassword);

        $dispatcher = $factory->getDispatcher();

        $event = new SmsSendEvent($config['sms_message_template'], $lead);

        $dispatcher->dispatch(SmsEvents::SMS_ON_SEND, $event);

        return $client->account->messages->sendMessage($sendingPhoneNumber, $leadPhoneNumber, $event->getContent());
    }
}