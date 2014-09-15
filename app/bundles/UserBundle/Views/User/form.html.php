<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'user');
$userId = $form->vars['data']->getId();
if (!empty($userId)) {
    $user   = $form->vars['data']->getName();
    $header = $view['translator']->trans('mautic.user.user.header.edit', array("%name%" => $user));
} else {
    $header = $view['translator']->trans('mautic.user.user.header.new');
}
$view['slots']->set("headerTitle", $header);
?>

<div class="scrollable">
    <?php echo $view['form']->form($form); ?>
    '
</div>