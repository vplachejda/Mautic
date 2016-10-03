<?php
/**
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\NotificationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class ConfigType.
 */
class ConfigType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'notification_enabled',
            'yesno_button_group',
            [
                'label' => 'mautic.notification.config.form.notification.enabled',
                'data'  => (bool) $options['data']['notification_enabled'],
                'attr'  => [
                    'tooltip' => 'mautic.notification.config.form.notification.enabled.tooltip',
                ],
            ]
        );

        $builder->add(
            'notification_app_id',
            'text',
            [
                'label' => 'mautic.notification.config.form.notification.app_id',
                'data'  => $options['data']['notification_app_id'],
                'attr'  => [
                    'tooltip'      => 'mautic.notification.config.form.notification.app_id.tooltip',
                    'class'        => 'form-control',
                    'data-show-on' => '{"config_notificationconfig_notification_enabled_1":"checked"}',
                ],
            ]
        );

        $builder->add(
            'notification_safari_web_id',
            'text',
            [
                'label' => 'mautic.notification.config.form.notification.safari_web_id',
                'data'  => $options['data']['notification_safari_web_id'],
                'attr'  => [
                    'tooltip'      => 'mautic.notification.config.form.notification.safari_web_id.tooltip',
                    'class'        => 'form-control',
                    'data-show-on' => '{"config_notificationconfig_notification_enabled_1":"checked"}',
                ],
            ]
        );

        $builder->add(
            'notification_rest_api_key',
            'text',
            [
                'label' => 'mautic.notification.config.form.notification.rest_api_key',
                'data'  => $options['data']['notification_rest_api_key'],
                'attr'  => [
                    'tooltip'      => 'mautic.notification.config.form.notification.rest_api_key.tooltip',
                    'class'        => 'form-control',
                    'data-show-on' => '{"config_notificationconfig_notification_enabled_1":"checked"}',
                ],
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'notificationconfig';
    }
}
