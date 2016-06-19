<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\NotificationBundle\Security\Permissions;

use Symfony\Component\Form\FormBuilderInterface;
use Mautic\CoreBundle\Security\Permissions\AbstractPermissions;

/**
 * Class NotificationPermissions
 *
 * @package Mautic\NotificationBundle\Security\Permissions
 */
class NotificationPermissions extends AbstractPermissions
{

    /**
     * {@inheritdoc}
     */
    public function __construct($params)
    {
        parent::__construct($params);
        $this->addStandardPermissions('categories');
        $this->addExtendedPermissions('notifications');
    }

    /**
     * {@inheritdoc}
     *
     * @return string|void
     */
    public function getName()
    {
        return 'notification';
    }

    /**
     * {@inheritdoc}
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface &$builder, array $options, array $data)
    {
        $this->addStandardFormFields('notification', 'categories', $builder, $data);
        $this->addExtendedFormFields('notification', 'notifications', $builder, $data);
    }
}