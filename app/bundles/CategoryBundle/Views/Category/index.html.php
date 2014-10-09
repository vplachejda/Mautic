<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'category');
$view['slots']->set("headerTitle", $view['translator']->trans('mautic.category.header.index'));
$view['slots']->set('searchUri', $view['router']->generate('mautic_category_index', array(
    'page'   => $page,
    'bundle' => $bundle
)));
$view['slots']->set('searchString', $app->getSession()->get('mautic.category.filter'));
$view['slots']->set('searchHelp', $view['translator']->trans('mautic.category.help.searchcommands'));
?>

<?php if ($permissions[$bundle.':categories:create']): ?>
<?php $view['slots']->start("actions"); ?>
    <a class="btn btn-default" href="<?php echo $this->container->get('router')->generate(
        'mautic_category_action', array(
            "objectAction" => "new",
            "bundle"       => $bundle
        )); ?>"
       data-toggle="ajax"
       data-menu-link="#mautic_category_index">
        <i class="fa fa-plus"></i> 
        <?php echo $view["translator"]->trans("mautic.category.menu.new"); ?>
    </a>
    <?php $view['slots']->stop(); ?>
<?php endif; ?>

<?php $view['slots']->output('_content'); ?>