<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
/*
$items = array(
    'name' => array(
        'label'    => '',
        'route'    => '',
        'uri'      => '',
        'attributes' => array(),
        'labelAttributes' => array(),
        'linkAttributes' => array(),
        'childrenAttributes' => array(),
        'extras' => array(),
        'display' => true,
        'displayChildren' => true,
        'children' => array()
    )
);
 */

$items = array(
    'mautic.dashboard.menu.index' => array(
        'name'    => 'mautic.dashboard.menu.index',
        'route'    => 'mautic_dashboard_index',
        'linkAttributes' => array(
            'data-toggle'    => 'ajax',
            'data-menu-link' => '#mautic_dashboard_index',
            'id'             => 'mautic_dashboard_index'
        ),
        'labelAttributes' => array(
            'class'   => 'nav-item-name'
        ),
        'extras'=> array(
            'iconClass' => 'fa-th-large',
            'routeName' => 'mautic_dashboard_index'
        )
    )
);

return array(
    'priority' => 0,
    'items'    => $items
);