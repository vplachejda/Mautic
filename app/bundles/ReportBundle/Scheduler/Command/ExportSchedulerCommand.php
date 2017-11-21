<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ReportBundle\Scheduler\Command;

use Mautic\ReportBundle\Model\ReportExporter;
use Mautic\ReportBundle\Scheduler\Option\ExportOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportSchedulerCommand extends Command
{
    /**
     * @var ReportExporter
     */
    private $reportExporter;

    public function __construct(ReportExporter $reportExporter)
    {
        parent::__construct();
        $this->reportExporter = $reportExporter;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('mautic:repots:scheduler')
            ->setDescription('Processes scheduler for report\'s export')
            ->addOption('--row-limit', 'limit', InputOption::VALUE_OPTIONAL, 'Limit number of exported rows at a time. Defaults to value set in config.')
            ->addOption('--report', 'report', InputOption::VALUE_OPTIONAL, 'ID of report. Process all reports if not set.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rowLimit = $input->getOption('row-limit');
        $report   = $input->getOption('report');

        try {
            $exportOption = new ExportOption($rowLimit, $report);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>parameters "row-limit" and "report" have to be numbers</error>');

            return;
        }

        $this->reportExporter->processExport($exportOption);
    }
}
