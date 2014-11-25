<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\MapperBundle\Helper;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\MapperBundle\Event\MapperSyncEvent;
use Mautic\MapperBundle\MapperEvents;
use Mautic\PointBundle\Entity\TriggerEvent;

/**
 * Class PointEventHelper
 */
class PointEventHelper
{
    /**
     * @param       $action
     *
     * @return array
     */
    public static function syncData(TriggerEvent $event, Lead $lead, MauticFactory $factory)
    {
        $event = new MapperSyncEvent($factory);
        $event->setMapper('Lead');
        $event->setData($lead);
        $factory->getDispatcher()->dispatch(MapperEvents::SYNC_DATA, $event);
    }
}