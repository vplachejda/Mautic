<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Form\Type;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Form\DataTransformer\EmojiToShortTransformer;
use Mautic\CoreBundle\Form\DataTransformer\IdToEntityModelTransformer;
use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Mautic\CoreBundle\Form\Type\DynamicContentTrait;
use Mautic\LeadBundle\Helper\FormFieldHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class EmailUtmTagsType.
 */
class EmailUtmTagsType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder->add(
            'utmSource',
            'text',
            [
                'label'      => 'mautic.email.campaign_source',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    //  'preaddon' => 'fa fa-user',
                    //   'tooltip'  => 'mautic.email.from_name.tooltip',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'utmMedium',
            'text',
            [
                'label'      => 'mautic.email.campaign_medium',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    //  'preaddon' => 'fa fa-user',
                    // 'tooltip'  => 'mautic.email.from_name.tooltip',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'utmName',
            'text',
            [
                'label'      => 'mautic.email.campaign_name',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    //  'preaddon' => 'fa fa-user',
                    //      'tooltip'  => 'mautic.email.from_name.tooltip',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'utmContent',
            'text',
            [
                'label'      => 'mautic.email.campaign_content',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    //  'preaddon' => 'fa fa-user',
                    //      'tooltip'  => 'mautic.email.from_name.tooltip',
                ],
                'required' => false,
            ]
        );
    }


    /**
     * @return string
     */
    public function getName()
    {
        return 'utm_tags';
    }
}
