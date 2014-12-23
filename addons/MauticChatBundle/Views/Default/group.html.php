<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
$firstMsg = reset($messages);
$showDate = (!empty($showDate)) ? true : false;

$dividerInserted = false;
if (!empty($insertUnreadDivider) && ((isset($lastReadId) && $firstMsg['id'] > $lastReadId && $user['id'] != $me->getId()) || (!isset($lastReadId) && !$firstMsg['isRead']))) {
    echo $view->render('MauticChatBundle:Default:newdivider.html.php');
    $dividerInserted = true;
}
?>
<li class="media<?php echo $direction; ?> chat-group nm pb-0" id="ChatGroup<?php echo $firstMsg['id']; ?>">
    <div class="media-object">
        <img src="<?php echo $view['gravatar']->getImage($user['email'], 40); ?>" class="img-circle" alt="">
        <div class="small"><?php echo $user['firstName'] . ' ' . substr($user['lastName'], 0, 1) . '.'; ?></div>
    </div>

    <div class="media-body">
        <?php
        foreach ($messages as $message):
            if (!empty($insertUnreadDivider) && !$dividerInserted && ((isset($lastReadId) && $user['id'] != $me->getId() && $message['id'] > $lastReadId) || (!isset($lastReadId) && !$message['isRead']))):
                echo $view->render('MauticChatBundle:Default:newdivider.html.php', array('tag' => 'div'));
                $dividerInserted = true;
            endif;
            echo $view->render('MauticChatBundle:Default:message.html.php', array('message' => $message));
        endforeach;
        ?>
        <?php if ($showDate): ?>
        <p class="media-meta text-white dark-lg"><?php echo $view['date']->toShort($message['dateSent']); ?></p>
        <?php endif; ?>
    </div>
    <input type="hidden" class="chat-group-firstid" value="<?php echo $firstMsg['id']; ?>" />
</li>