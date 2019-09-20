<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Tests\Sync\SyncDataExchange\Internal\ReportBuilder;


use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\FieldDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Request\ObjectDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Request\RequestDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Value\NormalizedValueDAO;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ObjectHelper\CompanyObjectHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ObjectHelper\ContactObjectHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ReportBuilder\FieldBuilder;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ReportBuilder\FullObjectReportBuilder;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\InputOptionsDAO;

class FullObjectReportBuilderTest extends \PHPUnit_Framework_TestCase
{
    private const INTEGRATION_NAME = 'Test';
    
    /**
     * @var ContactObjectHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $contactObjectHelper;

    /**
     * @var CompanyObjectHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $companyObjectHelper;

    /**
     * @var FieldBuilder|\PHPUnit_Framework_MockObject_MockObject
     */
    private $fieldBuilder;

    protected function setUp()
    {
        $this->contactObjectHelper = $this->createMock(ContactObjectHelper::class);
        $this->companyObjectHelper = $this->createMock(CompanyObjectHelper::class);
        $this->fieldBuilder        = $this->createMock(FieldBuilder::class);
    }

    public function testBuildingContactReport()
    {
        $requestDAO    = new RequestDAO(self::INTEGRATION_NAME, 1, new InputOptionsDAO(['integration' => self::INTEGRATION_NAME]));
        $fromDateTime  = new \DateTimeImmutable('2018-10-08 00:00:00');
        $toDateTime    = new \DateTimeImmutable('2018-10-08 00:01:00');
        $requestObject = new ObjectDAO(MauticSyncDataExchange::OBJECT_CONTACT, $fromDateTime, $toDateTime);
        $requestObject->addField('email');
        $requestDAO->addObject($requestObject);

        $this->fieldBuilder->expects($this->once())
            ->method('buildObjectField')
            ->with('email', $this->anything(), $requestObject, MauticSyncDataExchange::NAME)
            ->willReturn(
                new FieldDAO('email', new NormalizedValueDAO(NormalizedValueDAO::EMAIL_TYPE, 'test@test.com'))
            );

        $this->contactObjectHelper->expects($this->once())
            ->method('findObjectsBetweenDates')
            ->with($fromDateTime, $toDateTime, 0, 200)
            ->willReturn(
                [
                    [
                        'id'            => 1,
                        'email'         => 'test@test.com',
                        'date_modified' => '2018-10-08 00:30:00',
                    ]
                ]
            );

        $report  = $this->getReportBuilder()->buildReport($requestDAO);
        $objects = $report->getObjects(MauticSyncDataExchange::OBJECT_CONTACT);

        $this->assertTrue(isset($objects[1]));
        $this->assertEquals('test@test.com', $objects[1]->getField('email')->getValue()->getNormalizedValue());
    }

    public function testBuildingCompanyReport()
    {
        $requestDAO    = new RequestDAO(self::INTEGRATION_NAME, 1, new InputOptionsDAO(['integration' => self::INTEGRATION_NAME]));
        $fromDateTime  = new \DateTimeImmutable('2018-10-08 00:00:00');
        $toDateTime    = new \DateTimeImmutable('2018-10-08 00:01:00');
        $requestObject = new ObjectDAO(MauticSyncDataExchange::OBJECT_COMPANY, $fromDateTime, $toDateTime);
        $requestObject->addField('email');
        $requestDAO->addObject($requestObject);

        $this->fieldBuilder->expects($this->once())
            ->method('buildObjectField')
            ->with('email', $this->anything(), $requestObject, MauticSyncDataExchange::NAME)
            ->willReturn(
                new FieldDAO('email', new NormalizedValueDAO(NormalizedValueDAO::EMAIL_TYPE, 'test@test.com'))
            );

        $this->companyObjectHelper->expects($this->once())
            ->method('findObjectsBetweenDates')
            ->with($fromDateTime, $toDateTime, 0, 200)
            ->willReturn(
                [
                    [
                        'id'            => 1,
                        'email'         => 'test@test.com',
                        'date_modified' => '2018-10-08 00:30:00',
                    ]
                ]
            );

        $report  = $this->getReportBuilder()->buildReport($requestDAO);
        $objects = $report->getObjects(MauticSyncDataExchange::OBJECT_COMPANY);

        $this->assertTrue(isset($objects[1]));
        $this->assertEquals('test@test.com', $objects[1]->getField('email')->getValue()->getNormalizedValue());
    }

    /**
     * @return FullObjectReportBuilder
     */
    private function getReportBuilder()
    {
        return new FullObjectReportBuilder($this->contactObjectHelper, $this->companyObjectHelper, $this->fieldBuilder);
    }
}