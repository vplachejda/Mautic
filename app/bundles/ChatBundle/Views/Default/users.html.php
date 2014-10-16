<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>

<ul class="media-list media-list-contact" id="ChatUsers">
    <li class="media-heading">
        <h5 class="fw-sb"><?php echo $view['translator']->trans('mautic.chat.chat.users'); ?></h5>
    </li>

    <?php foreach ($users as $u): ?>
        <?php
        switch ($u['onlineStatus']):
            case 'online':
                $status = 'success';
                break;
            case 'away':
                $status = 'warning';
                break;
            case 'dnd':
                $status = 'danger';
                break;
            default:
                $status = 'default';
                break;
        endswitch;
        $hasUnread = (!empty($u['unread'])) ? ' text-warning' : '';

        $name      = $u['username'];
        $shortName = (strlen($name) > 15) ? substr($name, 0, 12) . '...' : $name;
        ?>
        <li class="media chat-list pb-0 pt-0">
            <a href="javascript:void(0);" onclick="Mautic.startUserChat('<?php echo $u['id']; ?>');" class="media offcanvas-opener offcanvas-open-rtl">
                <span class="pull-left img-wrapper img-circle mr-sm">
                    <img class="media-object" src="<?php echo $view['gravatar']->getImage($u['email'], '40'); ?>" class="img-circle" alt="" />
                </span>
                <span class="media-body">
                    <span class="media-heading mb-0 text-nowrap text-white dark-sm<?php echo $hasUnread; ?>"><span class="bullet bullet-<?php echo $status; ?> chat-bullet mr-sm"></span><?php echo $shortName; ?></span>
                    <span class="meta text-white dark-lg small"><?php echo $view['translator']->trans('mautic.chat.chat.status.'.$u['onlineStatus']); ?></span>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>