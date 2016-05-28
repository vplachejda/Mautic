<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\NotificationBundle;

/**
 * Class NotificationEvents
 * Events available for NotificationBundle
 *
 * @package Mautic\NotificationBundle
 */
final class NotificationEvents
{

    /**
     * The mautic.notification_on_click event is thrown when an email is opened
     *
     * The event listener receives a
     * Mautic\NotificationBundle\Event\NotificationClickEvent instance.
     *
     * @var string
     */
    const NOTIFICATION_ON_CLICK = 'mautic.notification_on_click';

    /**
     * The mautic.notification_on_send event is thrown when a notification is sent
     *
     * The event listener receives a
     * Mautic\NotificationBundle\Event\NotificationSendEvent instance.
     *
     * @var string
     */
    const NOTIFICATION_ON_SEND = 'mautic.notification_on_send';

    /**
     * The mautic.notification_pre_save event is thrown right before a notification is persisted.
     *
     * The event listener receives a
     * Mautic\NotificationBundle\Event\NotificationEvent instance.
     *
     * @var string
     */
    const NOTIFICATION_PRE_SAVE = 'mautic.notification_pre_save';

    /**
     * The mautic.notification_post_save event is thrown right after a notification is persisted.
     *
     * The event listener receives a
     * Mautic\NotificationBundle\Event\NotificationEvent instance.
     *
     * @var string
     */
    const NOTIFICATION_POST_SAVE = 'mautic.notification_post_save';

    /**
     * The mautic.notification_pre_delete event is thrown prior to when a notification is deleted.
     *
     * The event listener receives a
     * Mautic\NotificationBundle\Event\NotificationEvent instance.
     *
     * @var string
     */
    const NOTIFICATION_PRE_DELETE = 'mautic.notification_pre_delete';

    /**
     * The mautic.notification_post_delete event is thrown after a notification is deleted.
     *
     * The event listener receives a
     * Mautic\NotificationBundle\Event\NotificationEvent instance.
     *
     * @var string
     */
    const NOTIFICATION_POST_DELETE = 'mautic.notification_post_delete';
}