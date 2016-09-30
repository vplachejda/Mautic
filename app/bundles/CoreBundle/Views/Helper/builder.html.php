<?php
/**
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<div class="hide builder <?php echo $type; ?>-builder">
    <script type="text/html" data-builder-assets>
        <?php echo htmlspecialchars($builderAssets); ?>
    </script>
    <div class="builder-content">
        <input type="hidden" id="builder_url" value="<?php echo $view['router']->path('mautic_'.$type.'_action', ['objectAction' => 'builder', 'objectId' => $objectId]); ?>" />
    </div>
    <div class="builder-panel">
        <div class="builder-panel-top">
            <button type="button" class="btn btn-primary btn-close-builder" onclick="Mautic.closeBuilder('<?php echo 'type'; ?>');">
                <?php echo $view['translator']->trans('mautic.core.close.builder'); ?>
            </button>
        </div>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title"><?php echo $view['translator']->trans('mautic.core.slot.types'); ?></h4>
            </div>
            <div class="panel-body">
                <?php if ($slots): ?>
                <div id="slot-type-container" class="col-md-12">
                    <?php foreach ($slots as $slotKey => $slot): ?>
                        <div class="slot-type-handle btn btn-default btn-lg btn-nospin" data-slot-type="<?php echo $slotKey; ?>">
                            <i class="fa fa-<?php echo $slot['icon']; ?>" aria-hidden="true"></i>
                            <br>
                            <?php echo $slot['header']; ?>
                            <script type="text/html">
                                <?php echo $view->render($slot['content']); ?>
                            </script>
                        </div>
                    <?php endforeach; ?>
                    <div class="clearfix"></div>
                </div>
                <?php endif; ?>
                <p class="text-muted pt-md text-center"><i><?php echo $view['translator']->trans('mautic.core.drag.info'); ?></i></p>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title"><?php echo $view['translator']->trans('mautic.core.customize.slot'); ?></h4>
            </div>
            <div class="panel-body" id="customize-form-container">
                <div id="slot-form-container" class="col-md-12">
                    <p class="text-muted pt-md text-center">
                        <i><?php echo $view['translator']->trans('mautic.core.slot.customize.info'); ?></i>
                    </p>
                </div>
                <?php if ($slots): ?>
                    <?php foreach ($slots as $slotKey => $slot): ?>
                        <script type="text/html" data-slot-type-form="<?php echo $slotKey; ?>">
                            <?php echo $view['form']->start($slot['form']); ?>
                            <?php echo $view['form']->end($slot['form']); ?>
                        </script>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="panel panel-default" id="section">
            <div class="panel-heading">
                <h4 class="panel-title"><?php echo $view['translator']->trans('mautic.core.customize.section'); ?></h4>
            </div>
            <div class="panel-body" id="customize-form-container">
                <div id="section-form-container" class="col-md-12">
                    <p class="text-muted pt-md text-center">
                        <i><?php echo $view['translator']->trans('mautic.core.section.customize.info'); ?></i>
                    </p>
                </div>
                <script type="text/html" data-section-form>
                    <?php echo $view['form']->start($sectionForm); ?>
                    <?php echo $view['form']->end($sectionForm); ?>
                </script>
            </div>
        </div>
    </div>
</div>
