<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PluginBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class SocialMediaServiceType.
 */
class FieldsType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $index             = 0;
        $integrationFields = [0 => ''] + array_combine(array_keys($options['integration_fields']), array_keys($options['integration_fields']));
        foreach ($options['integration_fields'] as $field => $details) {
            ++$index;
            $builder->add('i_'.$index, 'choice', [
                'choices'  => $integrationFields,
                'label'    => false,
                'required' => true,
                'attr'     => ['class' => 'form-control', 'data-placeholder' => ' ',   'onChange' => 'Mautic.matchFieldsType('.$index.')'],
                'disabled' => ($index > 1 && !in_array($field, $options['data'])) ? true : false,
            ]);
            $builder->add('update_mautic'.$index,
                'yesno_button_group',
                [
                    'label'     => false,
                    'data'      => isset($options['data']['update_mautic']) ? (bool) $options['data']['update_mautic'] : true,
                    'no_label'  => '<-',
                    'yes_label' => '->',
                ]);

            $builder->add('m_'.$index, 'choice', [
                'choices'    => $options['lead_fields'],
                'label'      => false,
                'required'   => true,
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control', 'data-placeholder' => ' ',   'onChange' => 'Mautic.matchFieldsType('.$index.')'],
                'disabled'   => ($index > 1 && !in_array($field, $options['data'])) ? true : false,
            ]);
        }
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['integration_fields', 'lead_fields']);
        $resolver->setDefaults(
            [
                'special_instructions' => '',
                'alert_type'           => '',
                'allow_extra_fields'   => true,
            ]
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'integration_fields';
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['specialInstructions'] = $options['special_instructions'];
        $view->vars['alertType']           = $options['alert_type'];
    }
}
