<?php

namespace Mautic\CoreBundle\Tests\Unit\Helper;

use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Loader\ParameterLoader;

class DateTimeHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @testdox The guessTimezoneFromOffset returns correct values
     *
     * @covers \Mautic\CoreBundle\Helper\DateTimeHelper::guessTimezoneFromOffset
     */
    public function testGuessTimezoneFromOffset(): void
    {
        $helper   = new DateTimeHelper();
        $timezone = $helper->guessTimezoneFromOffset();
        $this->assertEquals($timezone, 'Europe/London');
        $timezone = $helper->guessTimezoneFromOffset(3600);
        $this->assertEquals($timezone, 'Europe/Paris');
        $timezone = $helper->guessTimezoneFromOffset(-2 * 3600);
        $this->assertEquals($timezone, 'America/Goose_Bay'); // Is it really in timezone -2
        $timezone = $helper->guessTimezoneFromOffset(-5 * 3600);
        $this->assertEquals($timezone, 'America/New_York');
    }

    public function testBuildIntervalWithBadUnit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $helper = new DateTimeHelper();
        $helper->buildInterval(4, 'j');
    }

    public function testBuildIntervalWithRightUnits(): void
    {
        $helper   = new DateTimeHelper();
        $interval = $helper->buildInterval(4, 'Y');
        $this->assertEquals(new \DateInterval('P4Y'), $interval);
        $interval = $helper->buildInterval(4, 'M');
        $this->assertEquals(new \DateInterval('P4M'), $interval);
        $interval = $helper->buildInterval(4, 'I');
        $this->assertEquals(new \DateInterval('PT4M'), $interval);
        $interval = $helper->buildInterval(4, 'S');
        $this->assertEquals(new \DateInterval('PT4S'), $interval);
    }

    public function testvalidateMysqlDateTimeUnitWillThrowExceptionOnBadUnit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DateTimeHelper::validateMysqlDateTimeUnit('D');
    }

    public function testvalidateMysqlDateTimeUnitWillNotThrowExceptionOnExpectedUnit(): void
    {
        DateTimeHelper::validateMysqlDateTimeUnit('s');
        DateTimeHelper::validateMysqlDateTimeUnit('i');
        DateTimeHelper::validateMysqlDateTimeUnit('H');
        DateTimeHelper::validateMysqlDateTimeUnit('d');
        DateTimeHelper::validateMysqlDateTimeUnit('W');
        DateTimeHelper::validateMysqlDateTimeUnit('m');
        DateTimeHelper::validateMysqlDateTimeUnit('Y');

        $this->assertTrue(true, 'Just to avoid the risky test warning...');
    }

    public function testGetLocalTimezoneOffset(): void
    {
        $timezone = (new ParameterLoader())->getParameterBag()->get('default_timezone');
        $helper   = new DateTimeHelper('now', DateTimeHelper::FORMAT_DB, $timezone);
        $date     = new \DateTime();
        $date->setTimezone(new \DateTimeZone($timezone));
        $this->assertEquals($date->format('P'), $helper->getLocalTimezoneOffset());
    }
}
