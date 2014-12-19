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
use Symfony\Component\DependencyInjection\Parameter;

//Custom form widgets
$container->setDefinition('mautic.form.type.asset', new Definition(
    'Mautic\AssetBundle\Form\Type\AssetType',
    array(
        new Reference('mautic.factory')
    )
))
    ->addTag('form.type', array(
        'alias' => 'asset',
    ));

$container->setDefinition('mautic.form.type.assetcategory', new Definition(
    'Mautic\AssetBundle\Form\Type\CategoryType',
    array(new Reference('mautic.factory'))
))
    ->addTag('form.type', array(
        'alias' => 'assetcategory',
    ));

$container->setDefinition('mautic.form.type.pointaction_assetdownload', new Definition(
    'Mautic\AssetBundle\Form\Type\PointActionAssetDownloadType'
))
    ->addTag('form.type', array(
        'alias' => 'pointaction_assetdownload',
    ));

$container->setDefinition('mautic.form.type.campaignevent_assetdownload', new Definition(
    'Mautic\AssetBundle\Form\Type\CampaignEventAssetDownloadType'
))
    ->addTag('form.type', array(
        'alias' => 'campaignevent_assetdownload',
    ));

$container->setDefinition('mautic.form.type.formsubmit_assetdownload', new Definition(
    'Mautic\AssetBundle\Form\Type\FormSubmitActionDownloadFileType'
))
    ->addTag('form.type', array(
        'alias' => 'asset_submitaction_downloadfile',
    ));

$container->setDefinition('mautic.form.type.assetlist', new Definition(
    'Mautic\AssetBundle\Form\Type\AssetListType',
    array(new Reference('mautic.factory'))
))
    ->addTag('form.type', array(
        'alias' => 'asset_list',
    ));

$container->setDefinition('mautic.form.type.assetconfig', new Definition(
    'Mautic\AssetBundle\Form\Type\ConfigType',
    array(new Reference('mautic.factory'))
))
    ->addTag('form.type', array(
        'alias' => 'assetconfig',
    ));
