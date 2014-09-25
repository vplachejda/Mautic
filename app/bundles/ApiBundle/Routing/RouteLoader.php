<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ApiBundle\Routing;

use Mautic\ApiBundle\ApiEvents;
use Mautic\CoreBundle\Event\RouteEvent;
use Mautic\CoreBundle\Factory\MauticFactory;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteLoader
 *
 * @package Mautic\ApiBundle\Routing
 */

class RouteLoader extends Loader
{
    private $loaded    = false;
    private $bundles;
    private $apiEnabled;

    /**
     * @param Container $container
     */
    public function __construct(MauticFactory $factory)
    {
        $this->bundles    = $factory->getParameter('bundles');
        $this->apiEnabled = $factory->getParameter('api_enabled');
    }

    /**
     * Load each bundles routing.php file
     *
     * @param mixed $resource
     * @param null  $type
     * @return RouteCollection
     * @throws \RuntimeException
     */
    public function load($resource, $type = null)
    {
        if (true === $this->loaded) {
            throw new \RuntimeException('Do not add the "mautic.api" loader twice');
        }

        if (!empty($this->apiEnabled)) {
            $collection = new RouteCollection();
            foreach ($this->bundles as $bundle) {
                $routing = $bundle['directory'] . "/Config/api.php";
                if (file_exists($routing)) {
                    $collection->addCollection($this->import($routing));
                } else {
                    $routing = $bundle['directory'] . "/Config/routing/api.php";
                    if (file_exists($routing)) {
                        $collection->addCollection($this->import($routing));
                    }
                }
            }
        } else {
            $collection = new RouteCollection();
        }

        $this->loaded = true;

        return $collection;
    }

    /**
     * @param mixed $resource
     * @param null  $type
     * @return bool
     */
    public function supports($resource, $type = null)
    {
        return 'mautic.api' === $type;
    }
}