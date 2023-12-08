<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessVariableRector;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Symfony\Symfony42\Rector\MethodCall\ContainerGetToConstructorInjectionRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\BoolReturnTypeFromStrictScalarReturnsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnDirectArrayRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictBoolReturnExprRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictConstantReturnRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictSetUpRector;

return static function (Rector\Config\RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/app/bundles',
        __DIR__.'/plugins',
    ]);

    $rectorConfig->skip([
        '*/Test/*',
        '*/Tests/*',
        '*.html.php',
        ContainerGetToConstructorInjectionRector::class => [
            // Requires quite a refactoring
            __DIR__.'/app/bundles/CoreBundle/Factory/MauticFactory.php',
        ],

        ReturnTypeFromReturnDirectArrayRector::class => [
            // require bit test update
            __DIR__.'/app/bundles/LeadBundle/Model/LeadModel.php',
            // array vs doctrine collection
            __DIR__.'/app/bundles/CoreBundle/Entity/TranslationEntityTrait.php',
        ],

        // lets handle later, once we have more type declaratoins
        \Rector\DeadCode\Rector\Cast\RecastingRemovalRector::class,

        \Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector::class => [
            // entities
            __DIR__.'/app/bundles/UserBundle/Entity',
            // typo fallback
            __DIR__.'/app/bundles/LeadBundle/Entity/LeadField.php',
        ],

        ReturnTypeFromStrictBoolReturnExprRector::class => [
            __DIR__.'/app/bundles/LeadBundle/Segment/Decorator/BaseDecorator.php',
            // requires quite a refactoring
            __DIR__.'/app/bundles/CoreBundle/Factory/MauticFactory.php',
        ],

        RemoveUnusedVariableAssignRector::class => [
            // unset variable to clear garbage collector
            __DIR__.'/app/bundles/LeadBundle/Model/ImportModel.php',
        ],

        TypedPropertyFromStrictConstructorRector::class => [
            // entities magic
            __DIR__.'/app/bundles/LeadBundle/Entity',

            // fixed in rector dev-main
            __DIR__.'/app/bundles/CoreBundle/DependencyInjection/Builder/BundleMetadata.php',
        ],

        \Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__.'/app/bundles/CacheBundle/EventListener/CacheClearSubscriber.php',
            __DIR__.'/app/bundles/ReportBundle/Event/ReportBuilderEvent.php',
            // false positive
            __DIR__.'/app/bundles/CoreBundle/DependencyInjection/Builder/BundleMetadata.php',
        ],

        // handle later with full PHP 8.0 upgrade
        \Rector\Php80\Rector\FunctionLike\MixedTypeRector::class,
        \Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector::class,
        \Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector::class,
        \Rector\CodeQuality\Rector\ClassMethod\OptionalParametersAfterRequiredRector::class,

        // handle later, case by case as lot of chnaged code
        \Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector::class => [
            __DIR__.'/app/bundles/PointBundle/Controller/TriggerController.php',
            __DIR__.'/app/bundles/LeadBundle/Controller/ImportController.php',
            __DIR__.'/app/bundles/FormBundle/Controller/FormController.php',
            // watch out on this one - the variables are set magically via $$name
            // @see app/bundles/FormBundle/Form/Type/FieldType.php:99
            __DIR__.'/app/bundles/FormBundle/Form/Type/FieldType.php',
        ],
    ]);

    foreach (['dev', 'test', 'prod'] as $environment) {
        $environmentCap = ucfirst($environment);
        $xmlPath        = __DIR__."/var/cache/{$environment}/appAppKernel{$environmentCap}DebugContainer.xml";
        if (file_exists($xmlPath)) {
            $rectorConfig->symfonyContainerXml($xmlPath);
            break;
        }
    }

    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory(__DIR__.'/var/cache/rector');

    // Define what rule sets will be applied
    $rectorConfig->sets([
        // helps with rebase of PRs for Symfony 3 and 4, @see https://github.com/mautic/mautic/pull/12676#issuecomment-1695531274
        // remove when not needed to keep memory usage lower
        \Rector\Symfony\Set\SymfonyLevelSetList::UP_TO_SYMFONY_54,

        \Rector\Doctrine\Set\DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_CODE_QUALITY,
        \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_COMMON_20,
        \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_DBAL_211,
        \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_DBAL_30,
        // \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_DBAL_40, this rule should run after the upgrade to doctrine 4.0
        \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_ORM_213,
        \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_ORM_214,
        \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_ORM_29,
        // \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_REPOSITORY_AS_SERVICE, will break code in Mautic, needs to be fixed first
        \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_ORM_25,

        \Rector\Set\ValueObject\SetList::DEAD_CODE,
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_80,
    ]);

    // Define what single rules will be applied
    $rectorConfig->rules([
        \Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\NumericReturnTypeFromStrictScalarReturnsRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnNewRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNewArrayRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictParamRector::class,
        \Rector\TypeDeclaration\Rector\Class_\ReturnTypeFromStrictTernaryRector::class,

        // \Rector\Php80\Rector\Catch_\RemoveUnusedVariableInCatchRector::class,
        \Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector::class,
        BoolReturnTypeFromStrictScalarReturnsRector::class,
        AddVoidReturnTypeWhereNoReturnRector::class,
        TypedPropertyFromStrictConstructorRector::class,
        TypedPropertyFromStrictSetUpRector::class,
        RemoveUnusedVariableAssignRector::class,
        RemoveUselessVarTagRector::class,
        SimplifyUselessVariableRector::class,
        ReturnTypeFromStrictBoolReturnExprRector::class,
        ReturnTypeFromStrictConstantReturnRector::class,
        ReturnTypeFromReturnDirectArrayRector::class,
        ContainerGetToConstructorInjectionRector::class,

        // PHP 8.0
        \Rector\Php80\Rector\NotIdentical\StrContainsRector::class,
        \Rector\Php80\Rector\Identical\StrStartsWithRector::class,
    ]);

    $rectorConfig->phpVersion(\Rector\Core\ValueObject\PhpVersion::PHP_80);
};
