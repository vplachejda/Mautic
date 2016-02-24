<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set("headerTitle", $view['translator']->trans('mautic.dashboard.header.index'));
$view['slots']->set('mauticContent', 'dashboard');

$buttons[] = array(
    'attr'      => array(
        'class'       => 'btn btn-default btn-nospin',
        'data-toggle' => 'ajaxmodal',
        'data-target' => '#MauticSharedModal',
        'href'        => $view['router']->generate('mautic_dashboard_action', array('objectAction' => 'new')),
        'data-header' => $view['translator']->trans('mautic.dashboard.widget.add'),
    ),
    'iconClass' => 'fa fa-plus',
    'btnText'   => 'mautic.dashboard.widget.add'
);

$buttons[] = array(
    'attr'      => array(
        'class'       => 'btn btn-default btn-nospin',
        'href'        => $view['router']->generate('mautic_dashboard_action', array('objectAction' => 'export')),
        'data-toggle' => ''
    ),
    'iconClass' => 'fa fa-cloud-download',
    'btnText'   => 'mautic.dashboard.export.widgets'
);

$buttons[] = array(
    'attr'      => array(
        'class'       => 'btn btn-default',
        'href'        => $view['router']->generate('mautic_dashboard_action', array('objectAction' => 'import')),
        'data-header' => $view['translator']->trans('mautic.dashboard.widget.import'),
    ),
    'iconClass' => 'fa fa-cloud-upload',
    'btnText'   => 'mautic.dashboard.widget.import'
);

$view['slots']->set('actions', $view->render('MauticCoreBundle:Helper:page_actions.html.php', array(
    'routeBase' => 'dashboard',
    'langVar'   => 'dashboard',
    'customButtons' => $buttons
)));
?>
<div class="row pt-md pl-md">
<?php echo $view['form']->start($filterForm, array('attr' => array('class' => 'form-filter'))); ?>
    <div class="col-sm-6">
        <div class="input-group">
            <span class="input-group-addon">
                <?php echo $view['form']->label($filterForm['date_from']); ?>
            </span>
            <?php echo $view['form']->widget($filterForm['date_from']); ?>
            <span class="input-group-addon" style="border-left: 0;border-right: 0;">
                <?php echo $view['form']->label($filterForm['date_to']); ?>
            </span>
            <?php echo $view['form']->widget($filterForm['date_to']); ?>
            <span class="input-group-btn">
                <?php echo $view['form']->row($filterForm['apply']); ?>
            </span>
        </div>
    </div>
<?php echo $view['form']->end($filterForm); ?>
</div>

<?php if (count($widgets)): ?>
    <div id="dashboard-widgets" class="cards">
        <?php foreach ($widgets as $widget): ?>
            <div class="card-flex widget" data-widget-id="<?php echo $widget->getId(); ?>" style="width: <?php echo !empty($widget->getWidth()) ? $widget->getWidth() . '' : '100' ?>%; height: <?php echo !empty($widget->getHeight()) ? $widget->getHeight() . 'px' : '300px' ?>">
                <?php echo $view->render('MauticDashboardBundle:Widget:detail.html.php', array('widget' => $widget)); ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="well well col-md-6 col-md-offset-3 mt-md">
        <div class="row">
            <div class="col-xs-3 text-center">
                <img class="img-responsive" style="max-height: 125px; margin-left: auto; margin-right: auto;" src="<?php echo $view['mautibot']->getImage('wave'); ?>" />
            </div>
            <div class="col-xs-9">
                <h4><i class="fa fa-quote-left"></i> <?php echo $view['translator']->trans('mautic.dashboard.nowidgets.tip.header'); ?> <i class="fa fa-quote-right"></i></h4>
                <p class="mt-md"><?php echo $view['translator']->trans('mautic.dashboard.nowidgets.tip'); ?></p>
                <a href="<?php echo $view['router']->generate('mautic_dashboard_action', array('objectAction' => 'applyDashboardFile', 'file' => 'default.json')); ?>" class="btn btn-success">
                    Apply the default dashboard
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>
