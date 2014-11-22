<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

//Mautic event listener
$container->setDefinition(
    'mautic.lead.subscriber',
    new Definition(
        'Mautic\LeadBundle\EventListener\LeadSubscriber',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('kernel.event_subscriber');

$container->setDefinition(
    'mautic.lead.emailbundle.subscriber',
    new Definition(
        'Mautic\LeadBundle\EventListener\EmailSubscriber',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('kernel.event_subscriber');

$container->setDefinition(
    'mautic.lead.formbundle.subscriber',
    new Definition(
        'Mautic\LeadBundle\EventListener\FormSubscriber',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('kernel.event_subscriber');

$container->setDefinition(
    'mautic.lead.campaignbundle.subscriber',
    new Definition(
        'Mautic\LeadBundle\EventListener\CampaignSubscriber',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('kernel.event_subscriber');

$container->setDefinition(
    'mautic.lead.reportbundle.subscriber',
    new Definition(
        'Mautic\LeadBundle\EventListener\ReportSubscriber',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('kernel.event_subscriber');


$container->setDefinition(
    'mautic.lead.doctrine.subscriber',
    new Definition(
        'Mautic\LeadBundle\EventListener\DoctrineSubscriber',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('doctrine.event_subscriber');

$container->setDefinition(
    'mautic.lead.calendarbundle.subscriber',
    new Definition(
        'Mautic\LeadBundle\EventListener\CalendarSubscriber',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('kernel.event_subscriber');
