<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>

<div class="global-search-result">
    <div class="global-search-primary">
    <?php if (!empty($showMore)): ?>
        <a class="pull-right margin-md-sides" href="<?php echo $this->container->get('router')->generate(
            'mautic_role_index', array('filter-role' => $searchString)); ?>"
           data-toggle="ajax">
            <span><?php echo $view['translator']->trans('mautic.core.search.more', array("%count%" => $remaining)); ?></span>
        </a>
    <?php else: ?>
        <?php if ($canEdit): ?>
        <a href="<?php echo $this->container->get('router')->generate(
            'mautic_role_action', array('objectAction' => 'edit', 'objectId' => $role->getId())); ?>"
        data-toggle="ajax">
        <?php endif; ?>
        <span><?php echo $role->getName(); ?></span>
        <?php if ($canEdit): ?>
        </a>
        <?php endif; ?>
        <span class="badge alert-success gs-count-badge" data-toggle="tooltip"
              title="<?php echo $view['translator']->trans('mautic.user.role.usercount'); ?>" data-placement="left">
            <?php echo count($role->getUsers()); ?>
        </span>
    <?php endif; ?>
    </div>
</div>