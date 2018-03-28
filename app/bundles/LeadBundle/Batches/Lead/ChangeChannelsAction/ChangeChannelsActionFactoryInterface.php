<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Batches\Lead\ChangeChannelsAction;

use Mautic\CoreBundle\Batches\ActionInterface;

interface ChangeChannelsActionFactoryInterface
{
    /**
     * Create action.
     *
     * @param array     $leadsIds
     * @param array     $subscribedChannels
     * @param array     $frequencyNumberEmail
     * @param array     $frequencyTimeEmail
     * @param \DateTime $contactPauseStartDateEmail
     * @param \DateTime $contactPauseEndDateEmail
     *
     * @return ActionInterface
     */
    public function create(
        array $leadsIds,
        array $subscribedChannels,
        array $frequencyNumberEmail,
        array $frequencyTimeEmail,
        \DateTime $contactPauseStartDateEmail,
        \DateTime $contactPauseEndDateEmail
    );
}
