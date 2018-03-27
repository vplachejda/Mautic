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