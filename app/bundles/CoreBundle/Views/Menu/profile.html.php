<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<li class="dropdown">
    <a class="dropdown-toggle" data-toggle="dropdown" href="#">
        <span class="img-wrapper img-rounded" style="width:32px;"><img src="https://s3.amazonaws.com/uifaces/faces/twitter/adhamdannaway/128.jpg"></span>
        <span class="text fw-sb ml-xs hidden-xs"><?php echo $app->getUser()->getName();?></span>
        <span class="caret ml-xs"></span>
    </a>
    <ul class="dropdown-menu dropdown-menu-right">
        <li>
            <a href="<?php echo $view['router']->generate("mautic_user_account"); ?>" data-toggle="ajax">
                <i class="fa fa-cog fs-14"></i><span><?php echo $view["translator"]->trans("mautic.user.account.settings"); ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo $view['router']->generate("mautic_user_logout"); ?>">
                <i class="fa fa-sign-out fs-14"></i><span><?php echo $view["translator"]->trans("mautic.user.auth.logout"); ?></span>
            </a>
        </li>
    </ul>
</li>

<!--<li class="panel-toggle right-panel-toggle">
    <a href="javascript: void(0);" data-toggle="sidebar" data-direction="rtl"><i class="fa fa-bars fa-2x"></i></a>
</li>-->