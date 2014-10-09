<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

//Lead forms
$container->setDefinition(
    'mautic.form.type.lead',
    new Definition(
        'Mautic\LeadBundle\Form\Type\LeadType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'lead',
    ));

//Lead list forms
$container->setDefinition(
    'mautic.form.type.leadlist',
    new Definition(
        'Mautic\LeadBundle\Form\Type\ListType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadlist',
    ));

//Lead list choice form
$container->setDefinition(
    'mautic.form.type.leadlist_choices',
    new Definition(
        'Mautic\LeadBundle\Form\Type\LeadListType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadlist_choices',
    ));


//Lead list filter form
$container->setDefinition(
    'mautic.form.type.leadlist_filters',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FilterType')
)
    ->addTag('form.type', array(
        'alias' => 'leadlist_filters',
    ));

//Lead field form
$container->setDefinition(
    'mautic.form.type.leadfield',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FieldType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadfield',
    ));

//Lead list
$container->setDefinition(
    'mautic.form.type.leadlist',
    new Definition(
        'Mautic\LeadBundle\Form\Type\ListType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadlist',
    ));

//Form submit action forms
$container->setDefinition(
    'mautic.form.type.lead.submitaction.createlead',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FormSubmitActionCreateLeadType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'lead_submitaction_createlead',
    ));

$container->setDefinition(
    'mautic.form.type.lead.submitaction.mappedfields',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FormSubmitActionMappedFieldsType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'lead_submitaction_mappedfields',
    ));

$container->setDefinition(
    'mautic.form.type.lead.submitaction.pointschange',
    new Definition(
        'Mautic\LeadBundle\Form\Type\FormSubmitActionPointsChangeType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'lead_submitaction_pointschange',
    ));

$container->setDefinition(
    'mautic.form.type.lead.submitaction.changelist',
    new Definition(
        'Mautic\LeadBundle\Form\Type\EventListType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadlist_action_type',
    ));

$container->setDefinition(
    'mautic.form.type.leadpoints_trigger',
    new Definition(
        'Mautic\LeadBundle\Form\Type\PointTriggerType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadpoints_trigger',
    ));

$container->setDefinition(
    'mautic.form.type.leadpoints_action',
    new Definition(
        'Mautic\LeadBundle\Form\Type\PointActionType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadpoints_action',
    ));

$container->setDefinition(
    'mautic.form.type.leadlist_trigger',
    new Definition(
        'Mautic\LeadBundle\Form\Type\ListTriggerType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadlist_trigger',
    ));

$container->setDefinition(
    'mautic.form.type.leadlist_action',
    new Definition(
        'Mautic\LeadBundle\Form\Type\ListActionType'
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadlist_action',
    ));

//Lead note form
$container->setDefinition(
    'mautic.form.type.leadnote',
    new Definition(
        'Mautic\LeadBundle\Form\Type\NoteType',
        array(new Reference('mautic.factory'))
    )
)
    ->addTag('form.type', array(
        'alias' => 'leadnote',
    ));
