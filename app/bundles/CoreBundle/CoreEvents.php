<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle;

/**
 * Class CoreEvents
 */
final class CoreEvents
{
    /**
     * The mautic.build_menu event is thrown to render menu items.
     *
     * The event listener receives a Mautic\CoreBundle\Event\MenuEvent instance.
     *
     * @var string
     */
    const BUILD_MENU = 'mautic.build_menu';

    /**
     * The mautic.build_route event is thrown to build Mautic bundle routes
     *
     * The event listener receives a Mautic\CoreBundle\Event\RouteEvent instance.
     *
     * @var string
     */
    const BUILD_ROUTE = 'mautic.build_route';

    /**
     * The mautic.global_search event is thrown to build global search results from applicable bundles
     *
     * The event listener receives a Mautic\CoreBundle\Event\GlobalSearchEvent instance.
     *
     * @var string
     */
    const GLOBAL_SEARCH = 'mautic.global_search';

    /**
     * The mautic.build_command_list event is thrown to build global search's autocomplete list
     *
     * The event listener receives a Mautic\CoreBundle\Event\CommandListEvent instance.
     *
     * @var string
     */
    const BUILD_COMMAND_LIST = 'mautic.build_command_list';

    /**
     * The mautic.on_fetch_icons event is thrown to fetch icons of menu items.
     *
     * The event listener receives a Mautic\CoreBundle\Event\IconEvent instance.
     *
     * @var string
     */
    const FETCH_ICONS = 'mautic.on_fetch_icons';

    /**
     * The mautic.build_canvas_content event is dispatched to populate the content for the right panel
     *
     * The event listener receives a Mautic\CoreBundle\Event\SidebarCanvasEvent instance.
     *
     * @var string
     */
    const BUILD_CANVAS_CONTENT = 'mautic.build_canvas_content';

    /**
     * The mautic.pre_upgrade is dispatched before an upgrade.
     *
     * The event listener receives a Mautic\CoreBundle\Event\UpgradeEvent instance.
     *
     * @var string
     */
    const PRE_UPGRADE = 'mautic.pre_upgrade';

    /**
     * The mautic.post_upgrade is dispatched after an upgrade.
     *
     * The event listener receives a Mautic\CoreBundle\Event\UpgradeEvent instance.
     *
     * @var string
     */
    const POST_UPGRADE = 'mautic.post_upgrade';

    /**
     * The mautic.build_embeddable_js event is dispatched to allow plugins to extend the mautic tracking js
     *
     * The event listener receives a Mautic\CoreBundle\Event\BuildJsEvent instance.
     *
     * @var string
     */
    const BUILD_MAUTIC_JS = 'mautic.build_embeddable_js';
}
