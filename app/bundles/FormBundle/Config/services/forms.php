<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

$container->setDefinition(
    'mautic.form.type.form',
    new Definition(
        'Mautic\FormBundle\Form\Type\FormType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'mauticform'
    ));

$container->setDefinition(
    'mautic.form.type.field',
    new Definition(
        'Mautic\FormBundle\Form\Type\FieldType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'formfield'
    ));


$container->setDefinition(
    'mautic.form.type.action',
    new Definition(
        'Mautic\FormBundle\Form\Type\ActionType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'formaction'
    ));

$container->setDefinition(
    'mautic.form.type.field_propertytext',
    new Definition(
        'Mautic\FormBundle\Form\Type\FormFieldTextType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'formfield_text',
    ));

$container->setDefinition(
    'mautic.form.type.field_propertybutton',
    new Definition(
        'Mautic\FormBundle\Form\Type\FormFieldButtonType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'formfield_button'
    ));

$container->setDefinition(
    'mautic.form.type.field_propertyplaceholder',
    new Definition(
        'Mautic\FormBundle\Form\Type\FormFieldPlaceholderType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'formfield_placeholder'
    ));

$container->setDefinition(
    'mautic.form.type.field_propertyselect',
    new Definition(
        'Mautic\FormBundle\Form\Type\FormFieldSelectType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'formfield_select'
    ));

$container->setDefinition(
    'mautic.form.type.field_propertycaptcha',
    new Definition(
        'Mautic\FormBundle\Form\Type\FormFieldCaptchaType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'formfield_captcha'
    ));

$container->setDefinition('mautic.form.type.pointaction_formsubmit', new Definition(
    'Mautic\FormBundle\Form\Type\PointActionFormSubmitType'
))
    ->addTag('form.type', array(
        'alias' => 'pointaction_formsubmit',
    ));

$container->setDefinition('mautic.form.type.form_list', new Definition(
    'Mautic\FormBundle\Form\Type\FormListType',
    array(new Reference('mautic.factory'))
))
    ->addTag('form.type', array(
        'alias' => 'form_list'
    ));


$container->setDefinition('mautic.form.type.campaignevent_formsubmit', new Definition(
    'Mautic\FormBundle\Form\Type\CampaignEventFormSubmitType'
))
    ->addTag('form.type', array(
        'alias' => 'campaignevent_formsubmit',
    ));
