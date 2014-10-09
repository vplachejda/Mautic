<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<div id="form-page-tokens">
    <ul class="draggable scrollable list-group">
        <li class="list-group-item has-click-event">
            <div class="padding-sm">
                <span class="list-item-primary">
                    <?php echo $view['translator']->trans('mautic.page.page.token.lang'); ?>
                </span>
                <span class="list-item-secondary" data-toggle="tooltip" data-placement="bottom"
                      title="<?php echo $view['translator']->trans('mautic.page.page.token.lang.descr'); ?>">
                    <?php echo substr($view['translator']->trans('mautic.page.page.token.lang.descr'), 0, 30); ?>...
                </span>
                <input type="hidden" class="page-token" value="{langbar}" />
            </div>
        </li>
        <li class="list-group-item has-click-event">
            <div class="padding-sm">
                <span class="list-item-primary">
                    <?php echo $view['translator']->trans('mautic.page.page.token.share'); ?>
                </span>
                <span class="list-item-secondary" data-toggle="tooltip" data-placement="bottom"
                      title="<?php echo $view['translator']->trans('mautic.page.page.token.share.descr'); ?>">
                    <?php echo substr($view['translator']->trans('mautic.page.page.token.share.descr'), 0, 30); ?>...
                </span>
                <input type="hidden" class="page-token" value="{sharebuttons}" />
            </div>
        </li>
    </ul>
</div>