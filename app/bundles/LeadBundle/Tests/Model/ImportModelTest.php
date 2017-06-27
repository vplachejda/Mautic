<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Tests;

use Mautic\LeadBundle\Entity\Import;
use Mautic\LeadBundle\Entity\LeadEventLog;
use Mautic\LeadBundle\Helper\Progress;

class ImportModelTest extends StandardImportTestHelper
{
    public function testInitEventLog()
    {
        $userId   = 4;
        $userName = 'John Doe';
        $fileName = 'import.csv';
        $line     = 104;
        $model    = $this->initImportModel();
        $entity   = $this->initImportEntity();
        $entity->setCreatedBy($userId)
            ->setCreatedByUser($userName)
            ->setOriginalFile($fileName);
        $log = $model->initEventLog($entity, $line);

        $this->assertTrue($log instanceof LeadEventLog);
        $this->assertSame($userId, $log->getUserId());
        $this->assertSame($userName, $log->getUserName());
        $this->assertSame('lead', $log->getBundle());
        $this->assertSame('import', $log->getObject());
        $this->assertSame(['line' => $line, 'file' => $fileName], $log->getProperties());
    }

    public function testProcess()
    {
        $model  = $this->initImportModel();
        $entity = $this->initImportEntity();
        $entity->start();
        $model->process($entity, new Progress());
        $entity->end();

        $this->assertEquals(100, $entity->getProgressPercentage());
        $this->assertSame(4, $entity->getInsertedCount());
        $this->assertSame(2, $entity->getIgnoredCount());
        $this->assertSame(Import::IMPORTED, $entity->getStatus());
    }

    public function testIsEmptyCsvRow()
    {
        $model    = $this->initImportModel();
        $testData = [
            [
                'row' => '',
                'res' => true,
            ],
            [
                'row' => [],
                'res' => true,
            ],
            [
                'row' => [null],
                'res' => true,
            ],
            [
                'row' => [''],
                'res' => true,
            ],
            [
                'row' => ['John'],
                'res' => false,
            ],
            [
                'row' => ['John', 'Doe'],
                'res' => false,
            ],
        ];

        foreach ($testData as $test) {
            $this->assertSame(
                $test['res'],
                $model->isEmptyCsvRow($test['row']),
                'Failed on row '.var_export($test['row'], true)
            );
        }
    }

    public function testTrimArrayValues()
    {
        $model    = $this->initImportModel();
        $testData = [
            [
                'row' => ['John '],
                'res' => ['John'],
            ],
            [
                'row' => ['  John  ', ' Do  e '],
                'res' => ['John', 'Do  e'],
            ],
            [
                'row' => ['key' => '  John  ', 2 => ' Do  e '],
                'res' => ['key' => 'John', 2 => 'Do  e'],
            ],
        ];

        foreach ($testData as $test) {
            $this->assertSame(
                $test['res'],
                $model->trimArrayValues($test['row']),
                'Failed on row '.var_export($test['row'], true)
            );
        }
    }

    public function testHasMoreValuesThanColumns()
    {
        $model    = $this->initImportModel();
        $columns  = 3;
        $testData = [
            [
                'row' => ['John'],
                'mod' => ['John', '', ''],
                'res' => false,
            ],
            [
                'row' => ['John', 'Doe'],
                'mod' => ['John', 'Doe', ''],
                'res' => false,
            ],
            [
                'row' => ['key' => 'John', 2 => 'Doe', 'stuff'],
                'mod' => ['key' => 'John', 2 => 'Doe', 'stuff'],
                'res' => false,
            ],
            [
                'row' => ['key' => 'John', 2 => 'Doe', 'stuff', 'this is too much'],
                'mod' => ['key' => 'John', 2 => 'Doe', 'stuff', 'this is too much'],
                'res' => true,
            ],
        ];

        foreach ($testData as $test) {
            $res = $model->hasMoreValuesThanColumns($test['row'], $columns);
            $this->assertSame(
                $test['res'],
                $res,
                'Failed on row '.var_export($test['row'], true)
            );
            $this->assertSame($test['mod'], $test['row']);
        }
    }
}
