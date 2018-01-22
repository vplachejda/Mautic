<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Segment\Decorator\Date;

class DateDefault extends DateOptionAbstract implements DateOptionsInterface
{
    /**
     * {@inheritdoc}
     */
    protected function modifyBaseDate()
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function getModifierForBetweenRange()
    {
        return '+0 day';
    }
}
