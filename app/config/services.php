<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;

// This is loaded by \Mautic\CoreBundle\DependencyInjection\MauticCoreExtension to auto-wire services
// if the bundle do not cover it itself by their own *Extension and services.php which is prefered.
return function (ContainerConfigurator $configurator, ContainerInterface $container) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public()
    ;

    $bundles = array_merge($container->getParameter('mautic.bundles'), $container->getParameter('mautic.plugin.bundles'));

    // Autoconfigure services for bundles that do not have its own Config/services.php
    foreach ($bundles as $bundle) {
        if (file_exists($bundle['directory'].'/Config/services.php')) {
            continue;
        }

        $excludes = [
            'Controller', // Enabling this will require to refactor all controllers to use DI.
        ];

        $services->load($bundle['namespace'].'\\', $bundle['directory'])
            ->exclude($bundle['directory'].'/{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

        if (is_dir($bundle['directory'].'/Entity')) {
            $services->load($bundle['namespace'].'\\Entity\\', $bundle['directory'].'/Entity/*Repository.php');
        }
    }
};
