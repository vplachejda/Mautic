<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PluginBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

trait FieldsTypeTrait
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     * @param array                $integrationFields
     * @param array                $mauticFields
     * @param string               $fieldObject
     */
    protected function buildFormFields(
        FormBuilderInterface $builder,
        array $options,
        array $integrationFields,
        array $mauticFields,
        $fieldObject = ''
    ) {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options, $integrationFields, $mauticFields, $fieldObject) {
            $form = $event->getForm();
            $index = 0;
            $choices = [];
            $populatedFields = [];
            $requiredFields = [];
            $optionalFields = [];
            $fieldData = isset($options['data']) ? $options['data'] : [];
            $isPost = (isset($fieldData['i_1']));
            $matchedFields = [];

            // First loop to build options
            foreach ($integrationFields as $field => $details) {
                //if ($matched = ($isPost) ? !empty($fieldData['m_'.$fieldData[$field]]) : !empty($fieldData[$field])) {
                //    $matchedFields[$field] = !empty($fieldData['m_'.$fieldData[$field]]) ? $fieldData['m_'.$fieldData[$field]] : $fieldData[$field];
                //}

                if (is_array($details) && !empty($details['required'])) {
                    $requiredFields[$field] = $details;
                } elseif (isset($fieldData[$field])) {
                    $populatedFields[$field] = $details;
                } else {
                    $optionalFields[$field] = $details;
                }

                if (is_array($details)) {
                    if (isset($details['group'])) {
                        if (!isset($choices[$details['group']])) {
                            $choices[$details['group']] = [];
                        }

                        $label = (isset($details['optionLabel'])) ? $details['optionLabel'] : $details['label'];
                        $group[$field] = $details['group'];
                        $choices[$field] = $label;
                    } else {
                        $choices[$field] = $details['label'];
                    }
                } else {
                    $choices[$field] = $details;
                }
            }

            $fields = array_merge($requiredFields, $populatedFields, $optionalFields);

            foreach ($fields as $field => $details) {
                $matched = isset($fieldData[$field]);
                $required = (int) !empty($integrationFields[$field]['required']);
                $disabled = (!$required && $index > 1 && !$matched) ? 'disabled' : '';
                $mauticDisabled = ($required || $index == 1 || $matched) ? '' : 'disabled';
                ++$index;
                $form->add(
                    'label_'.$index,
                    'text',
                    [
                        'label' => false,
                        'data'  => $choices[$field],
                        'attr'  => [
                            'class'         => 'form-control',
                            'data-required' => $required,
                            'data-label'    => $choices[$field],
                            'placeholder'   => isset($group[$field]) ? $group[$field] : '',
                            'readonly'      => true,
                        ],
                        'by_reference' => true,
                        'mapped'       => false,
                    ]
                );
                if (isset($options['enable_data_priority']) and $options['enable_data_priority']) {
                    $updateName = 'update_mautic';
                    if ($fieldObject) {
                        $updateName .= '_'.$fieldObject;
                    }
                    $form->add(
                        $updateName.$index,
                        'button_group',
                        [
                            'choices' => [
                                '<btn class="btn-nospin fa fa-arrow-circle-left"></btn>',
                                '<btn class="btn-nospin fa fa-arrow-circle-right"></btn>',
                            ],
                            'label'       => false,
                            'data'        => isset($options[$updateName][$field]) ? (int) $options[$updateName][$field] : 1,
                            'empty_value' => false,
                            'attr'        => ['data-toggle' => 'tooltip', 'title' => 'mautic.plugin.direction.data.update', 'disabled' => $disabled],
                            'disabled'    => $disabled,
                        ]
                    );
                }
                $form->add(
                    'm_'.$index,
                    'choice',
                    [
                        'choices'    => $mauticFields,
                        'label'      => false,
                        'data'       => $matched ? $fieldData[$field] : '',
                        'label_attr' => ['class' => 'control-label'],
                        'attr'       => [
                            'class'            => 'field-selector',
                            'data-placeholder' => ' ',
                            'data-required'    => $required,
                            'data-value'       => $matched ? $fieldData[$field] : '',
                            'disabled'         => $mauticDisabled,
                            'data-choices'     => $mauticFields,
                        ],
                        'disabled' => $mauticDisabled,
                    ]
                );
                $form->add(
                    'i_'.$index,
                    HiddenType::class,
                    [
                        'data' => $field,
                        'attr' => [
                            'data-required' => $required,
                        ],
                    ]
                );
                $form->add(
                    $field,
                    HiddenType::class,
                    [
                        'data' => $index,
                        'attr' => [
                            'data-required' => $required,
                        ],
                    ]
                );
            }
        });
    }
}
