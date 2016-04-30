<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\DependencyInjection;

use Mautic\CoreBundle\Helper\ServiceLoaderHelper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader;

/**
 * Class MauticCoreExtension
 *
 * This is the class that loads and manages your bundle configuration
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class MauticCoreExtension extends Extension
{

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $bundles = array_merge($container->getParameter('mautic.bundles'), $container->getParameter('mautic.plugin.bundles'));

        foreach ($bundles as $bundle) {
            if (!empty($bundle['config']['services'])) {
                $config = $bundle['config']['services'];
                foreach ($config as $type => $services) {
                    switch ($type) {
                        case 'events':
                            $defaultTag = 'kernel.event_subscriber';
                            break;
                        case 'forms':
                            $defaultTag = 'form.type';
                            break;
                        case 'helpers':
                            $defaultTag = 'templating.helper';
                            break;
                        default:
                            $defaultTag = false;
                            break;
                    }

                    foreach ($services as $name => $details) {
                        if (!is_array($details)) {
                            // Set parameter
                            $container->setParameter($name, $details);
                            continue;
                        }

                        // Set service alias
                        if (isset($details['serviceAlias'])) {
                            $container->setAlias(sprintf($details['serviceAlias'], $name), $name);
                        }

                        // Generate definition arguments
                        $definitionArguments = array();
                        if (!isset($details['arguments'])) {
                            $details['arguments'] = array();
                        } elseif (!is_array($details['arguments'])) {
                            $details['arguments'] = array($details['arguments']);
                        }

                        // Add MauticFactory to events
                        if ($type == 'events' && !in_array('mautic.factory', $details['arguments'])) {
                            $details['arguments'][] = 'mautic.factory';
                        }

                        foreach ($details['arguments'] as $argument) {
                            if (is_array($argument) || is_object($argument)) {
                                foreach ($argument as $k => &$v) {
                                    if (strpos($v, '%') === 0) {
                                        $v = $container->getParameter(substr($v, 1, -1));
                                    }
                                }
                                $definitionArguments[] = $argument;
                            } elseif (is_bool($argument) || strpos($argument, '%') === 0 || strpos($argument, '\\') !== false) {
                                // Parameter or Class
                                $definitionArguments[] = $argument;
                            } elseif (strpos($argument, '"') === 0) {
                                // String
                                $definitionArguments[] = substr($argument, 1, -1);
                            } else {
                                // Reference
                                $definitionArguments[] = new Reference($argument);
                            }
                        }

                        // Add the service
                        $definition = $container->setDefinition($name, new Definition(
                            $details['class'],
                            $definitionArguments
                        ));

                        // Generate tag and tag arguments
                        if (isset($details['tags'])) {
                            $tagArguments = (!empty($details['tagArguments'])) ? $details['tagArguments'] : array();
                            foreach ($details['tags'] as $k => $tag) {
                                if (!isset($tagArguments[$k])) {
                                    $tagArguments[$k] = array();
                                }

                                if (!empty($details['alias'])) {
                                    $tagArguments[$k]['alias'] = $details['alias'];
                                }

                                $definition->addTag($tag, $tagArguments[$k]);
                            }
                        } else {
                            $tag          = (!empty($details['tag'])) ? $details['tag'] : $defaultTag;
                            $tagArguments = (!empty($details['tagArguments'])) ? $details['tagArguments'] : array();

                            if (!empty($tag)) {
                                if (!empty($details['alias'])) {
                                    $tagArguments['alias'] = $details['alias'];
                                }

                                $definition->addTag($tag, $tagArguments);
                            }
                        }

                        // Set public service
                        if (!empty($details['public'])) {
                            $definition->setPublic($details['public']);
                        }

                        // Set lazy service
                        if (!empty($details['lazy'])) {
                            $definition->setLazy($details['lazy']);
                        }

                        // Set synthetic service
                        if (!empty($details['synthetic'])) {
                            $definition->setSynthetic($details['synthetic']);
                        }

                        // Set abstract service
                        if (!empty($details['abstract'])) {
                            $definition->setAbstract($details['abstract']);
                        }

                        // Set include file
                        if (!empty($details['file'])) {
                            $definition->setFile($details['file']);
                        }

                        // Set service configurator
                        if (!empty($details['configurator'])) {
                            $definition->setConfigurator($details['configurator']);
                        }

                        // Set scope - Deprecated as of Symfony 2.8 and removed in 3.0
                        if (!empty($details['scope'])) {
                            $definition->setScope($details['scope']);
                        } elseif ($type == 'templating') {
                            $definition->setScope('request');
                        }

                        // Set factory service - Deprecated as of Symfony 2.6 and removed in Symfony 3.0
                        if (!empty($details['factoryService'])) {
                            $definition->setFactoryService($details['factoryService']);
                        }

                        // Set factory class - Deprecated as of Symfony 2.6 and removed in Symfony 3.0
                        if (!empty($details['factoryClass'])) {
                            $definition->setFactoryClass($details['factoryClass']);
                        }

                        // Set factory method - Deprecated as of Symfony 2.6 and removed in Symfony 3.0
                        if (!empty($details['factoryMethod'])) {
                            $definition->setFactoryMethod($details['factoryMethod']);
                        }

                        // Set factory - Preferred API since Symfony 2.6
                        if (!empty($details['factory'])) {
                            $factory = $details['factory'];

                            /*
                             * Standardize to an array then convert a service to a Reference if needed
                             *
                             * This supports three syntaxes:
                             *
                             * 1) @service::method or Class::method
                             * 2) array('@service', 'method') or array('Class', 'method')
                             * 3) "Unknown" - Just pass it to the definition
                             *
                             * Services must always be prefaced with an @ symbol (similar to "normal" config files)
                             */
                            if (is_string($factory) && strpos($factory, '::') !== false) {
                                $factory = explode('::', $factory, 2);
                            }

                            // Check if the first item in the factory array is a service and if so fetch its reference
                            if (is_array($factory) && strpos($factory[0], '@') === 0) {
                                // Exclude the leading @ character in the service ID
                                $factory[0] = new Reference(substr($factory[0], 1));
                            }

                            $definition->setFactory($factory);
                        }

                        // Set method calls
                        if (!empty($details['methodCalls'])) {
                            foreach ($details['methodCalls'] as $method => $methodArguments) {
                                $methodCallArguments = array();
                                foreach ($methodArguments as $argument) {
                                    if (is_array($argument) || is_object($argument)) {
                                        foreach ($argument as $k => &$v) {
                                            if (strpos($v, '%') === 0) {
                                                $v = $container->getParameter(substr($v, 1, -1));
                                            }
                                        }
                                        $methodCallArguments[] = $argument;
                                    } elseif (is_bool($argument) || strpos($argument, '%') === 0 || strpos($argument, '\\') !== false) {
                                        // Parameter or Class
                                        $methodCallArguments[] = $argument;
                                    } elseif (strpos($argument, '"') === 0) {
                                        // String
                                        $methodCallArguments[] = substr($argument, 1, -1);
                                    } else {
                                        // Reference
                                        $methodCallArguments[] = new Reference($argument);
                                    }
                                }

                                $definition->addMethodCall($method, $methodCallArguments);
                            }
                        }

                        // Set deprecated service
                        if (!empty($details['decoratedService'])) {
                            // This should be an array and the first parameter cannot be empty
                            if (!is_array($details['decoratedService'])) {
                                throw new InvalidArgumentException('The "decoratedService" definition must be an array.');
                            }

                            // The second parameter of setDecoratedService is optional, check if there is a second key in the array
                            $secondParam = !empty($details['decoratedService'][1]) ? $details['decoratedService'][1] : null;

                            $definition->setDecoratedService($details['decoratedService'][0], $secondParam);
                        }

                        unset($definition);
                    }
                }
            }
        }

        unset($bundles);
    }
}
