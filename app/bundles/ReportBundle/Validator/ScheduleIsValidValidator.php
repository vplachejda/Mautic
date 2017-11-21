<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace Mautic\ReportBundle\Validator;

use Mautic\ReportBundle\Entity\Report;
use Mautic\ReportBundle\Exception\ScheduleNotValidException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ScheduleIsValidValidator extends ConstraintValidator
{
    /**
     * @param Report     $report
     * @param Constraint $constraint
     */
    public function validate($report, Constraint $constraint)
    {
        if (!$report->isScheduled()) {
            $report->setAsNotScheduled();

            return;
        }
        if ($report->isScheduledDaily()) {
            $report->ensureIsDailyScheduled();

            return;
        }
        if ($report->isScheduledWeekly()) {
            try {
                $report->ensureIsWeeklyScheduled();

                return;
            } catch (ScheduleNotValidException $e) {
                $this->addViolation();
            }
        }
        if ($report->isScheduledMonthly()) {
            try {
                $report->ensureIsMonthlyScheduled();

                return;
            } catch (ScheduleNotValidException $e) {
                $this->addViolation();
            }
        }
    }

    private function addViolation()
    {
        $this->context->buildViolation('mautic.report.scheduler.notValid')
            ->atPath('isScheduled')
            ->addViolation();
    }
}
