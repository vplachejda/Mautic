<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SmsBundle\Form\Type;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class SmsSendType
 *
 * @package Mautic\FormBundle\Form\Type
 */
class SmsSendType extends AbstractType
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
        $builder->add('sms', 'sms_list', array(
            'label'       => 'mautic.sms.send.selectsmss',
            'label_attr'  => array('class' => 'control-label'),
            'attr'        => array(
                'class'   => 'form-control',
                'tooltip' => 'mautic.sms.choose.smss',
                'onchange'=> 'Mautic.disabledSmsAction()'
            ),
            'multiple'    => false,
            'constraints' => array(
                new NotBlank(
                    array('message' => 'mautic.sms.choosesms.notblank')
                )
            )
        ));

        if (! empty($options['update_select'])) {
            $windowUrl = $this->router->generate('mautic_sms_action', array(
                'objectAction' => 'new',
                'contentOnly'  => 1,
                'updateSelect' => $options['update_select']
            ));

            $builder->add('newSmsButton', 'button', array(
                'attr'  => array(
                    'class'   => 'btn btn-primary btn-nospin',
                    'onclick' => 'Mautic.loadNewSmsWindow({
                        "windowUrl": "' . $windowUrl . '"
                    })',
                    'icon'    => 'fa fa-plus'
                ),
                'label' => 'mautic.sms.send.new.sms'
            ));

            $sms = $options['data']['sms'];

            // create button edit sms
            $windowUrlEdit = $this->router->generate('mautic_sms_action', array(
                'objectAction' => 'edit',
                'objectId'     => 'smsId',
                'contentOnly'  => 1,
                'updateSelect' => $options['update_select']
            ));

            $builder->add('editSmsButton', 'button', array(
                'attr'  => array(
                    'class'     => 'btn btn-primary btn-nospin',
                    'onclick'   => 'Mautic.loadNewSmsWindow(Mautic.standardSmsUrl({"windowUrl": "' . $windowUrlEdit . '"}))',
                    'disabled'  => !isset($sms),
                    'icon'      => 'fa fa-edit'
                ),
                'label' => 'mautic.sms.send.edit.sms'
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
        return "smssend_list";
    }
}