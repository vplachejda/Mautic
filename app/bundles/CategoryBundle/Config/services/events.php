<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

//Mautic event listener
$container->setDefinition(
    'mautic.category.subscriber',
    new Definition(
        'Mautic\CategoryBundle\EventListener\CategorySubscriber',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('kernel.event_subscriber');