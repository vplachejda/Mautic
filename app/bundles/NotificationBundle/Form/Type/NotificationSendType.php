<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\NotificationBundle\Form\Type;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class NotificationSendType
 *
 * @package Mautic\FormBundle\Form\Type
 */
class NotificationSendType extends AbstractType
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('notification', 'notification_list', array(
            'label'       => 'mautic.notification.send.selectnotifications',
            'label_attr'  => array('class' => 'control-label'),
            'attr'        => array(
                'class'   => 'form-control',
                'tooltip' => 'mautic.notification.choose.notifications',
                'onchange'=> 'Mautic.disabledNotificationAction()'
            ),
            'multiple'    => false,
            'constraints' => array(
                new NotBlank(
                    array('message' => 'mautic.notification.choosenotification.notblank')
                )
            )
        ));

        if (! empty($options['update_select'])) {
            $windowUrl = $this->router->generate('mautic_notification_action', array(
                'objectAction' => 'new',
                'contentOnly'  => 1,
                'updateSelect' => $options['update_select']
            ));

            $builder->add('newNotificationButton', 'button', array(
                'attr'  => array(
                    'class'   => 'btn btn-primary btn-nospin',
                    'onclick' => 'Mautic.loadNewNotificationWindow({
                        "windowUrl": "' . $windowUrl . '"
                    })',
                    'icon'    => 'fa fa-plus'
                ),
                'label' => 'mautic.notification.send.new.notification'
            ));

            $notification = $options['data']['notification'];

            // create button edit notification
            $windowUrlEdit = $this->router->generate('mautic_notification_action', array(
                'objectAction' => 'edit',
                'objectId'     => 'notificationId',
                'contentOnly'  => 1,
                'updateSelect' => $options['update_select']
            ));

            $builder->add('editNotificationButton', 'button', array(
                'attr'  => array(
                    'class'     => 'btn btn-primary btn-nospin',
                    'onclick'   => 'Mautic.loadNewNotificationWindow(Mautic.standardNotificationUrl({"windowUrl": "' . $windowUrlEdit . '"}))',
                    'disabled'  => !isset($notification),
                    'icon'      => 'fa fa-edit'
                ),
                'label' => 'mautic.notification.send.edit.notification'
            ));
        }
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setOptional(array('update_select'));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return "notificationsend_list";
    }
}