<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace Mautic\AssetBundle\EventListener;

use Mautic\DashboardBundle\DashboardEvents;
use Mautic\DashboardBundle\Event\WidgetDetailEvent;
use Mautic\DashboardBundle\EventListener\DashboardSubscriber as MainDashboardSubscriber;
use Mautic\CoreBundle\Helper\DateTimeHelper;

/**
 * Class DashboardSubscriber
 *
 * @package Mautic\AssetBundle\EventListener
 */
class DashboardSubscriber extends MainDashboardSubscriber
{
    /**
     * Define the name of the bundle/category of the widget(s)
     *
     * @var string
     */
    protected $bundle = 'asset';

    /**
     * Define the widget(s)
     *
     * @var array
     */
    protected $types = array(
        'asset.downloads.in.time' => array(),
        'unique.vs.repetitive.downloads' => array(),
        'popular.assets' => array(),
        'created.assets' => array()
    );

    /**
     * Define permissions to see those widgets
     *
     * @var array
     */
    protected $permissions = array(
        'asset:assets:viewown',
        'asset:assets:viewother'
    );


    /**
     * Set a widget detail when needed 
     *
     * @param WidgetDetailEvent $event
     *
     * @return void
     */
    public function onWidgetDetailGenerate(WidgetDetailEvent $event)
    {
        $this->checkPermissions($event);
        $canViewOthers = $event->hasPermission('asset:assets:viewother');
        
        if ($event->getType() == 'asset.downloads.in.time') {
            $widget = $event->getWidget();
            $params = $widget->getParams();

            if (!$event->isCached()) {
                $model = $this->factory->getModel('asset');
                $event->setTemplateData(array(
                    'chartType'   => 'line',
                    'chartHeight' => $widget->getHeight() - 80,
                    'chartData'   => $model->getDownloadsLineChartData(
                        $params['timeUnit'],
                        $params['dateFrom'],
                        $params['dateTo'],
                        $params['dateFormat'],
                        $canViewOthers
                    )
                ));
            }

            $event->setTemplate('MauticCoreBundle:Helper:chart.html.php');
            $event->stopPropagation();
        }

        if ($event->getType() == 'unique.vs.repetitive.downloads') {
            if (!$event->isCached()) {
                $params = $event->getWidget()->getParams();
                $model = $this->factory->getModel('asset');
                $event->setTemplateData(array(
                    'chartType'   => 'pie',
                    'chartHeight' => $event->getWidget()->getHeight() - 80,
                    'chartData'   => $model->getUniqueVsRepetitivePieChartData($params['dateFrom'], $params['dateTo'], $canViewOthers)
                ));
            }

            $event->setTemplate('MauticCoreBundle:Helper:chart.html.php');
            $event->stopPropagation();
        }

        if ($event->getType() == 'popular.assets') {
            if (!$event->isCached()) {
                $model = $this->factory->getModel('asset');
                $params = $event->getWidget()->getParams();

                if (empty($params['limit'])) {
                    // Count the pages limit from the widget height
                    $limit = round((($event->getWidget()->getHeight() - 80) / 35) - 1);
                } else {
                    $limit = $params['limit'];
                }
                
                $assets = $model->getPopularAssets($limit, $params['dateFrom'], $params['dateTo'], $canViewOthers);
                $items  = array();

                // Build table rows with links
                if ($assets) {
                    foreach ($assets as &$asset) {
                        $assetUrl = $this->factory->getRouter()->generate('mautic_asset_action', array('objectAction' => 'view', 'objectId' => $asset['id']));
                        $row = array(
                            array(
                                'value' => $asset['title'],
                                'type' => 'link',
                                'link' => $assetUrl
                            ),
                            array(
                                'value' => $asset['download_count']
                            )
                        );
                        $items[] = $row;
                    }
                }
                
                $event->setTemplateData(array(
                    'headItems'   => array(
                        $event->getTranslator()->trans('mautic.dashboard.label.title'),
                        $event->getTranslator()->trans('mautic.dashboard.label.downloads')
                    ),
                    'bodyItems'   => $items,
                    'raw'         => $assets
                ));
            }

            $event->setTemplate('MauticCoreBundle:Helper:table.html.php');
            $event->stopPropagation();
        }

        if ($event->getType() == 'created.assets') {
            if (!$event->isCached()) {
                $model  = $this->factory->getModel('asset');
                $params = $event->getWidget()->getParams();

                if (empty($params['limit'])) {
                    // Count the assets limit from the widget height
                    $limit = round((($event->getWidget()->getHeight() - 80) / 35) - 1);
                } else {
                    $limit = $params['limit'];
                }

                $assets = $model->getAssetList($limit, $params['dateFrom'], $params['dateTo'], array(), array('canViewOthers' => $canViewOthers));
                $items = array();

                // Build table rows with links
                if ($assets) {
                    foreach ($assets as &$asset) {
                        $assetUrl = $this->factory->getRouter()->generate('mautic_asset_action', array('objectAction' => 'view', 'objectId' => $asset['id']));
                        $row = array(
                            array(
                                'value' => $asset['name'],
                                'type' => 'link',
                                'link' => $assetUrl
                            )
                        );
                        $items[] = $row;
                    }
                }

                $event->setTemplateData(array(
                    'headItems'   => array(
                        $event->getTranslator()->trans('mautic.dashboard.label.title')
                    ),
                    'bodyItems'   => $items,
                    'raw'         => $assets
                ));
            }
            
            $event->setTemplate('MauticCoreBundle:Helper:table.html.php');
            $event->stopPropagation();
        }
    }
}
