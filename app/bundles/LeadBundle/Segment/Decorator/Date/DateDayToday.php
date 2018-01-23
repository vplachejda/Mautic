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

class DateDayToday extends DateOptionAbstract
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
        return '+1 day';
    }
}
