<?php

namespace Mautic\ReportBundle\Model;

use Mautic\CoreBundle\Templating\Helper\FormatterHelper;
use Mautic\ReportBundle\Crate\ReportDataResult;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Class CsvExporter.
 */
class ExcelExporter
{
    /**
     * @var FormatterHelper
     */
    protected $formatterHelper;

    public function __construct(FormatterHelper $formatterHelper)
    {
        $this->formatterHelper = $formatterHelper;
    }

    /**
     * @param string $name
     *
     * @throws \Exception
     */
    public function export(ReportDataResult $reportDataResult, $name, string $output = 'php://output')
    {
        if (!class_exists(Spreadsheet::class)) {
            throw new \Exception('PHPSpreadsheet is required to export to Excel spreadsheets');
        }

        try {
            $objPHPExcel = new Spreadsheet();
            $objPHPExcel->getProperties()->setTitle($name);
            $objPHPExcel->createSheet();
            $objPHPExcelSheet = $objPHPExcel->getActiveSheet();
            $reportData       = $reportDataResult->getData();
            $rowCount         = 1;

            if (empty($reportData)) {
                throw new \Exception('No report data to be exported');
            }

            $headersRow = $reportDataResult->getHeaders();
            $this->putHeader($headersRow, $objPHPExcelSheet);

            //build the data rows
            foreach ($reportData as $count=>$data) {
                $row = [];
                foreach ($data as $k => $v) {
                    $type      = $reportDataResult->getType($k);
                    $formatted = htmlspecialchars_decode($this->formatterHelper->_($v, $type, true), ENT_QUOTES);
                    $row[]     = $formatted;
                }

                //write the row
                $rowCount = $count + 2;
                $objPHPExcel->getActiveSheet()->fromArray($row, null, "A{$rowCount}");
                //free memory
                unset($row, $reportData['data'][$count]);
            }

            //Add totals to export
            $totalsRow = $reportDataResult->getTotalsToExport();
            if (!empty($totalsRow)) {
                $this->putTotals($totalsRow, $objPHPExcelSheet, 'A'.++$rowCount);
            }

            $objWriter = IOFactory::createWriter($objPHPExcel, 'Xlsx');
            $objWriter->setPreCalculateFormulas(false);

            $objWriter->save($output);
        } catch (Exception $e) {
            throw new \Exception('PHPSpreadsheet Error', 0, $e);
        }
    }

    private function putHeader($headers, Worksheet $activeSheet)
    {
        $activeSheet->fromArray($headers);
    }

    private function putTotals(array $totals, Worksheet $activeSheet, string $startCell)
    {
        $activeSheet->fromArray($totals, null, $startCell);
    }
}
