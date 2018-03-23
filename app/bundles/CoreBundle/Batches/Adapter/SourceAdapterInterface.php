<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Batches\Adapter;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Via this interface you are able to load source data.
 */
interface SourceAdapterInterface
{
    /**
     * Startup method with container. It is called right after a batch action is in run (before any other method on this object will be called).
     *
     * @param ContainerInterface $container
     */
    public function startup(ContainerInterface $container);

    /**
     * Define how source objects will be loaded.
     *
     * @param int[] $ids
     *
     * @return array
     */
    public function loadObjectsById(array $ids);

    /**
     * Get array of ids from request.
     *
     * @param Request $request
     *
     * @return int[]
     */
    public function getIdList(Request $request);
}