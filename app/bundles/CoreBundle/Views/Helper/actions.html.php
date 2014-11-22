<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$nameGetter = (!empty($nameGetter)) ? $nameGetter : 'getName';
if (empty($pull)) {
    $pull = 'left';
}
if (!isset($extra)) {
    $extra = array();
}

?>
<div class="input-group input-group-sm">
    <span class="input-group-addon">
        <input type="checkbox" data-toggle="selectrow" class="list-checkbox" name="cb<?php echo $item->getId(); ?>" value="<?php echo $item->getId(); ?>" />
      </span>
    <div class="btn-group">
        <button type="button" class="btn btn-default btn-sm dropdown-toggle btn-nospin" data-toggle="dropdown">
            <i class="fa fa-angle-down "></i>
        </button>
        <ul class="pull-<?php echo $pull; ?> page-list-actions dropdown-menu" role="menu">
            <?php
            if (!empty($customTop)):
            foreach ($customTop as $c): ?>
            <li>
                <?php
                $attr = '';
                if (isset($menuLink)):
                    $attr .= ' data-menu-link="' . $menuLink . '"';
                endif;
                if (isset($c['attr'])):
                    foreach ($c['attr'] as $k => $v):
                        $attr .= " $k=" . '"' . $v . '"';
                    endforeach;
                endif;
                ?>
                <a<?php echo $attr; ?>>
                            <span>
                                <?php if (isset($c['icon'])): ?>
                                    <i class="fa fa-fw <?php echo $c['icon']; ?>"></i>
                                <?php endif; ?>
                                <?php echo $view['translator']->trans($c['label']); ?>
                            </span>
                </a>
            </li>
            <?php
            endforeach;
            endif;
            if (!empty($edit)): ?>
                <li>
                    <a href="<?php echo $view['router']->generate('mautic_' . $routeBase . '_action',
                        array_merge(array("objectAction" => "edit", "objectId" => $item->getId()), $extra)); ?>"
                       data-toggle="ajax"
                        <?php if (isset($menuLink)):?>
                        data-menu-link="<?php echo $menuLink; ?>"
                        <?php endif; ?>>
                        <span><i class="fa fa-fw fa-pencil-square-o"></i><?php echo $view['translator']->trans('mautic.core.form.edit'); ?></span>
                    </a>
                </li>
            <?php
            endif;
            if (!empty($clone)): ?>
            <li>
                <a href="<?php echo $view['router']->generate('mautic_' . $routeBase . '_action',
                       array_merge(array("objectAction" => "clone", "objectId" => $item->getId()), $extra)); ?>"
                   data-toggle="ajax"
                    <?php if (isset($menuLink)):?>
                    data-menu-link="<?php echo $menuLink; ?>"
                    <?php endif; ?>>
                    <span><i class="fa fa-fw fa-copy"></i><?php echo $view['translator']->trans('mautic.core.form.clone'); ?></span>
                </a>
            </li>
            <?php
            endif;
            if (!empty($delete)): ?>
            <li>
                <a href="javascript:void(0);"
                   onclick="Mautic.showConfirmation(
                       '<?php echo $view->escape($view["translator"]->trans("mautic." . $langVar . ".form.confirmdelete",
                            array("%name%" => $item->$nameGetter() . " (" . $item->getId() . ")")), 'js'); ?>',
                       '<?php echo $view->escape($view["translator"]->trans("mautic.core.form.delete"), 'js'); ?>',
                       'executeAction',
                       ['<?php echo $view['router']->generate('mautic_' . $routeBase . '_action',
                            array_merge(array("objectAction" => "delete", "objectId" => $item->getId()), $extra)); ?>',
                       '#<?php echo $menuLink; ?>'],
                       '<?php echo $view->escape($view["translator"]->trans("mautic.core.form.cancel"), 'js'); ?>','',[]);">
                    <span><i class="fa fa-fw fa-trash-o"></i><?php echo $view['translator']->trans('mautic.core.form.delete'); ?></span>
                </a>
            </li>
            <?php endif; ?>
            <?php
            if (!empty($custom)):
                foreach ($custom as $c): ?>
                    <li>
                        <?php
                        $attr = '';
                        if (isset($menuLink)):
                            $attr .= ' data-menu-link="' . $menuLink . '"';
                        endif;
                        if (isset($c['attr'])):
                            foreach ($c['attr'] as $k => $v):
                                $attr .= " $k=" . '"' . $v . '"';
                            endforeach;
                        endif;
                        ?>
                        <a<?php echo $attr; ?>>
                            <span>
                                <?php if (isset($c['icon'])): ?>
                                <i class="fa fa-fw <?php echo $c['icon']; ?>"></i>
                                <?php endif; ?>
                                <?php echo $view['translator']->trans($c['label']); ?>
                            </span>
                        </a>
                    </li>
                <?php
                endforeach;
            endif;
            ?>
        </ul>
    </div>
</div>
