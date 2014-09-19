<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!empty($deleted)):
    $action    = 'undelete';
    $iconClass = 'fa-undo';
    $btnClass  = 'btn-warning';
else:
    $action    = 'delete';
    $iconClass = 'fa-times';
    $btnClass  = 'btn-danger';
endif;

if (empty($route))
    $route = 'mautic_campaignevent_action';
?>

<div class="pull-right hide form-buttons">
    <a data-toggle="ajaxmodal" data-target="#campaignEventModal" data-ignore-removemodal="true" href="<?php echo $view['router']->generate($route, array('objectAction' => 'edit', 'objectId' => $id, 'level' => $level)); ?>" class="btn btn-primary btn-xs btn-edit">
        <i class="fa fa-pencil-square-o"></i>
    </a>
    <a data-menu-link="mautic_campaign_index" data-toggle="ajax" data-target="#CampaignEvent_<?php echo $id; ?>" data-ignore-formexit="true" data-method="POST" data-hide-loadingbar="true" href="<?php echo $view['router']->generate($route, array('objectAction' => $action, 'objectId' => $id, 'level' => $level)); ?>"  class="btn <?php echo $btnClass; ?> btn-xs btn-delete">
        <i class="fa <?php echo $iconClass; ?>"></i>
    </a>
</div>