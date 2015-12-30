<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace Mautic\DashboardBundle\EventListener;

use Mautic\DashboardBundle\DashboardEvents;
use Mautic\DashboardBundle\Event\WidgetTypeListEvent;
use Mautic\DashboardBundle\Event\WidgetFormEvent;
use Mautic\DashboardBundle\Event\WidgetDetailEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Helper\DateTimeHelper;

/**
 * Class DashboardSubscriber
 *
 * @package Mautic\DashboardBundle\EventListener
 */
class DashboardSubscriber extends CommonSubscriber
{
    protected $bundle = 'others';
    protected $types = array();

    /**
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(
            DashboardEvents::DASHBOARD_ON_MODULE_LIST_GENERATE => array('onWidgetListGenerate', 0),
            DashboardEvents::DASHBOARD_ON_MODULE_FORM_GENERATE => array('onWidgetFormGenerate', 0),
            DashboardEvents::DASHBOARD_ON_MODULE_DETAIL_GENERATE => array('onWidgetDetailGenerate', 0),
        );
    }

    /**
     * Adds widget new widget types to the list of available widget types 
     *
     * @param WidgetTypeListEvent $event
     *
     * @return void
     */
    public function onWidgetListGenerate(WidgetTypeListEvent $event)
    {
        $widgetTypes = array_keys($this->types);

        foreach ($widgetTypes as $type) {
            $event->addType($type, $this->bundle);
        }
    }

    /**
     * Set a widget edit form when needed 
     *
     * @param WidgetFormEvent $event
     *
     * @return void
     */
    public function onWidgetFormGenerate(WidgetFormEvent $event)
    {
        if (isset($this->types[$event->getType()])) {
            $event->setForm($this->types[$event->getType()]);
            $event->stopPropagation();
        }
    }

    /**
     * Set a widget detail when needed 
     *
     * @param WidgetDetailEvent $event
     *
     * @return void
     */
    public function onWidgetDetailGenerate(WidgetDetailEvent $event)
    {
    }
}
