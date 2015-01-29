<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$buttonGroupTypes = array('group', 'button-dropdown');
foreach ($buttonGroupTypes as $groupType) {
    $buttonCount = 0;
    if ($groupType == 'group') {
        echo '<div class="btn-group hidden-xs hidden-sm">';
        $dropdownOpenHtml = '';
    } else {
        echo '<div class="btn-group hidden-md hidden-lg">';
        $dropdownOpenHtml  = '<button type="button" class="btn btn-default btn-nospin  dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-caret-down"></i></button>' . "\n";
        $dropdownOpenHtml .= '<ul class="dropdown-menu dropdown-menu-right" role="menu">' . "\n";
    }

    include 'action_button_helper.php';

    echo $view['buttons']->renderPreCustomButtons($buttonCount, $dropdownOpenHtml);

    foreach ($templateButtons as $action => $enabled) {
        if (empty($enabled)) {
            continue;
        }

        if ($buttonCount === 1) {
            echo $dropdownOpenHtml;
        }

        if ($groupType == 'button-dropdown' && $buttonCount > 0) {
            $wrapOpeningTag = "<li>\n";
            $wrapClosingTag = "</li>\n";
        }

        echo $wrapOpeningTag;

        $btnClass = ($groupType == 'group' || $buttonCount === 0) ? 'btn btn-default' : '';

        switch ($action) {
            case 'clone':
            case 'abtest':
                $icon = ($action == 'clone') ? 'copy' : 'sitemap';
                echo '<a class="'.$btnClass.'" href="' . $view['router']->generate('mautic_' . $routeBase . '_action', array_merge(array("objectAction" => $action), $query)) . '" data-toggle="ajax"' . $menuLink . ">\n";
                echo '  <i class="fa fa-'.$icon.'"></i> ' . $view['translator']->trans('mautic.core.form.' . $action) . "\n";
                echo "</a>\n";
                break;
            case 'new':
            case'edit':
                if ($action == 'new') {
                    $icon = 'plus';
                } else {
                    $icon = 'pencil-square-o';
                    $query['objectId'] = $item->getId();
                }

                echo '<a class="'.$btnClass.'" href="' . $view['router']->generate('mautic_' . $routeBase . '_action', array_merge(array("objectAction" => $action), $query)) . '" data-toggle="' . $editMode . '"' . $editAttr . $menuLink . ">\n";
                echo '  <i class="fa fa-'.$icon.'"></i> ' . $view['translator']->trans('mautic.core.form.' . $action) . "\n";
                echo "</a>\n";
                break;
            case 'delete':
                echo $view->render('MauticCoreBundle:Helper:confirm.html.php', array(
                    'message'       => $view["translator"]->trans("mautic." . $langVar . ".form.confirmdelete", array("%name%" => $item->$nameGetter() . " (" . $item->getId() . ")")),
                    'confirmAction' => $view['router']->generate('mautic_' . $routeBase . '_action', array_merge(array("objectAction" => "delete", "objectId" => $item->getId()), $query)),
                    'template'      => 'delete',
                    'btnClass'      => ($groupType == 'button-dropdown') ? '' : $btnClass
                ));
                break;
        }
        $buttonCount++;

        echo $wrapClosingTag;
    }

    echo $view['buttons']->renderPostCustomButtons($buttonCount, $dropdownOpenHtml);

    echo ($groupType == 'group') ? '</div>' : '</ul></div>';
}

echo $extraHtml;