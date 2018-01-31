<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\LeadBundle\Model\ListModel;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckQueryBuildersCommand extends ModeratedCommand
{
    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('mautic:segments:check-builders')
            ->setDescription('Compare output of query builders for given segments')
            ->addOption('--segment-id', '-i', InputOption::VALUE_OPTIONAL, 'Set the ID of segment to process')
            ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container    = $this->getContainer();
        $this->logger = $container->get('monolog.logger.mautic');

        /** @var \Mautic\LeadBundle\Model\ListModel $listModel */
        $listModel = $container->get('mautic.lead.model.list');

        $id      = $input->getOption('segment-id');
        $verbose = $input->getOption('verbose');

        if ($id) {
            $list = $listModel->getEntity($id);

            if (!$list) {
                $output->writeln('<error>Segment with id "'.$id.'" not found');

                return 1;
            }
            $this->runSegment($output, $verbose, $list, $listModel);
        } else {
            $lists = $listModel->getEntities(
                [
                    'iterator_mode' => true,
                ]
            );

            while (($l = $lists->next()) !== false) {
                // Get first item; using reset as the key will be the ID and not 0
                $l = reset($l);

                $this->runSegment($output, $verbose, $l, $listModel);
            }

            unset($l);

            unset($lists);
        }

        return 0;
    }

    private function format_period($inputSeconds)
    {
        $now = \DateTime::createFromFormat('U.u', number_format($inputSeconds, 6, '.', ''));

        return $now->format('H:i:s.u');
    }

    private function runSegment($output, $verbose, $l, ListModel $listModel)
    {
        $output->write('<info>Running segment '.$l->getId().'...old...');

        $this->logger->info(sprintf('Running OLD segment #%d', $l->getId()));

        $timer1    = microtime(true);
        $processed = $listModel->getVersionOld($l);
        $timer1    = microtime(true) - $timer1;

        $this->logger->info(sprintf('Running NEW segment #%d', $l->getId()));

        $output->write('new...');
        $timer2     = microtime(true);
        $processed2 = $listModel->getVersionNew($l);
        $timer2     = microtime(true) - $timer2;

        $processed2 = array_shift($processed2);

        if ((intval($processed['count']) != intval($processed2['count'])) or (intval($processed['maxId']) != intval($processed2['maxId']))) {
            $output->write('<error>');
        } else {
            $output->write('<info>');
        }

        $output->write(
            sprintf('old: c: %d, m: %d, time: %s(est)  <--> new: c: %d, m: %s, time: %s',
                    $processed['count'],
                    $processed['maxId'],
                    $this->format_period($timer1),
                    $processed2['count'],
                    $processed2['maxId'],
                    $this->format_period($timer2)
            )
        );

        if ((intval($processed['count']) != intval($processed2['count'])) or (intval($processed['maxId']) != intval($processed2['maxId']))) {
            $output->writeln('</error>');
        } else {
            $output->writeln('</info>');
        }
    }
}
