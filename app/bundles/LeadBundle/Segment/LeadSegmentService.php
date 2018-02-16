<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Segment;

use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListSegmentRepository;
use Mautic\LeadBundle\Segment\Query\LeadSegmentQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Symfony\Bridge\Monolog\Logger;

class LeadSegmentService
{
    /**
     * @var LeadSegmentFilterFactory
     */
    private $leadSegmentFilterFactory;

    /**
     * @var LeadSegmentQueryBuilder
     */
    private $leadSegmentQueryBuilder;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var QueryBuilder
     */
    private $preparedQB;

    /**
     * LeadSegmentService constructor.
     *
     * @param LeadSegmentFilterFactory  $leadSegmentFilterFactory
     * @param LeadListSegmentRepository $leadListSegmentRepository
     * @param LeadSegmentQueryBuilder   $queryBuilder
     * @param Logger                    $logger
     */
    public function __construct(
        LeadSegmentFilterFactory $leadSegmentFilterFactory,
        LeadSegmentQueryBuilder $queryBuilder,
        Logger $logger
    ) {
        $this->leadSegmentFilterFactory  = $leadSegmentFilterFactory;
        $this->leadSegmentQueryBuilder   = $queryBuilder;
        $this->logger                    = $logger;
    }

    /**
     * @param LeadList $leadList
     * @param          $batchLimiters
     *
     * @return Query\QueryBuilder|QueryBuilder
     */
    private function getNewLeadListLeadsQuery(LeadList $leadList, $batchLimiters)
    {
        if (!is_null($this->preparedQB)) {
            return $this->preparedQB;
        }

        $segmentFilters = $this->leadSegmentFilterFactory->getLeadListFilters($leadList);

        $queryBuilder = $this->leadSegmentQueryBuilder->assembleContactsSegmentQueryBuilder($segmentFilters);
        $queryBuilder = $this->leadSegmentQueryBuilder->addNewLeadsRestrictions($queryBuilder, $leadList->getId(), $batchLimiters);
        $queryBuilder = $this->leadSegmentQueryBuilder->addManuallyUnsubsribedQuery($queryBuilder, $leadList->getId());
        //dump($queryBuilder->getSQL());
        //dump($queryBuilder->getParameters());
        //dump($queryBuilder->execute());
        //exit;

        return $queryBuilder;
    }

    /**
     * @param LeadList $leadList
     * @param array    $batchLimiters
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getNewLeadListLeadsCount(LeadList $leadList, array $batchLimiters)
    {
        $segmentFilters = $this->leadSegmentFilterFactory->getLeadListFilters($leadList);

        if (!count($segmentFilters)) {
            $this->logger->debug('Segment QB: Segment has no filters', ['segmentId' => $leadList->getId()]);

            return [$leadList->getId() => [
                'count' => '0',
                'maxId' => '0',
            ],
            ];
        }

        $qb = $this->getNewLeadListLeadsQuery($leadList, $batchLimiters);

        $qb = $this->leadSegmentQueryBuilder->wrapInCount($qb);

        $this->logger->debug('Segment QB: Create SQL: '.$qb->getDebugOutput(), ['segmentId' => $leadList->getId()]);

        $result = $this->timedFetch($qb, $leadList->getId());

        return [$leadList->getId() => $result];
    }

    /**
     * @param LeadList $leadList
     * @param array    $batchLimiters
     * @param int      $limit
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getNewLeadListLeads(LeadList $leadList, array $batchLimiters, $limit = 1000)
    {
        $queryBuilder = $this->getNewLeadListLeadsQuery($leadList, $batchLimiters);
        $queryBuilder->select('DISTINCT l.id');

        $this->logger->debug('Segment QB: Create Leads SQL: '.$queryBuilder->getDebugOutput(), ['segmentId' => $leadList->getId()]);

        $queryBuilder->setMaxResults($limit);

        if (!empty($batchLimiters['minId']) && !empty($batchLimiters['maxId'])) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->comparison('l.id', 'BETWEEN', "{$batchLimiters['minId']} and {$batchLimiters['maxId']}")
            );
        } elseif (!empty($batchLimiters['maxId'])) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->lte('l.id', $batchLimiters['maxId'])
            );
        }

        if (!empty($batchLimiters['dateTime'])) {
            // Only leads in the list at the time of count
            $queryBuilder->andWhere(
                $queryBuilder->expr()->lte('l.date_added', $queryBuilder->expr()->literal($batchLimiters['dateTime']))
            );
        }

        $result = $this->timedFetchAll($queryBuilder, $leadList->getId());

        return [$leadList->getId() => $result];
    }

    /**
     * @param LeadList $leadList
     *
     * @return QueryBuilder
     */
    private function getOrphanedLeadListLeadsQueryBuilder(LeadList $leadList)
    {
        $segmentFilters = $this->leadSegmentFilterFactory->getLeadListFilters($leadList);

        $queryBuilder = $this->leadSegmentQueryBuilder->assembleContactsSegmentQueryBuilder($segmentFilters);

        $queryBuilder->rightJoin('l', MAUTIC_TABLE_PREFIX.'lead_lists_leads', 'orp', 'l.id = orp.lead_id and orp.leadlist_id = '.$leadList->getId());
        $queryBuilder->andWhere($queryBuilder->expr()->andX(
            $queryBuilder->expr()->isNull('l.id'),
            $queryBuilder->expr()->eq('orp.leadlist_id', $leadList->getId())
        ));

        $queryBuilder->select($queryBuilder->guessPrimaryLeadIdColumn().' as id');

        return $queryBuilder;
    }

    /**
     * @param LeadList $leadList
     * @param array    $batchLimiters
     * @param int      $limit
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getOrphanedLeadListLeadsCount(LeadList $leadList)
    {
        $queryBuilder = $this->getOrphanedLeadListLeadsQueryBuilder($leadList);
        $queryBuilder = $this->leadSegmentQueryBuilder->wrapInCount($queryBuilder);

        $this->logger->debug('Segment QB: Orphan Leads Count SQL: '.$queryBuilder->getDebugOutput(), ['segmentId' => $leadList->getId()]);

        $result = $this->timedFetch($queryBuilder, $leadList->getId());

        return [$leadList->getId() => $result];
    }

    /**
     * @param LeadList $leadList
     * @param array    $batchLimiters
     * @param int      $limit
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getOrphanedLeadListLeads(LeadList $leadList)
    {
        $queryBuilder = $this->getOrphanedLeadListLeadsQueryBuilder($leadList);

        $this->logger->debug('Segment QB: Orphan Leads SQL: '.$queryBuilder->getDebugOutput(), ['segmentId' => $leadList->getId()]);

        $result = $this->timedFetchAll($queryBuilder, $leadList->getId());

        return [$leadList->getId() => $result];
    }

    /**
     * Formatting helper.
     *
     * @param $inputSeconds
     *
     * @return string
     */
    private function format_period($inputSeconds)
    {
        $now = \DateTime::createFromFormat('U.u', number_format($inputSeconds, 6, '.', ''));

        return $now->format('H:i:s.u');
    }

    /**
     * @param QueryBuilder $qb
     * @param int          $segmentId
     *
     * @return mixed
     *
     * @throws \Exception
     */
    private function timedFetch(QueryBuilder $qb, $segmentId)
    {
        try {
            $start  = microtime(true);

            $result = $qb->execute()->fetch(\PDO::FETCH_ASSOC);

            $end = microtime(true) - $start;

            $this->logger->debug('Segment QB: Query took: '.$this->format_period($end).', Result count: '.count($result), ['segmentId' => $segmentId]);
        } catch (\Exception $e) {
            $this->logger->error('Segment QB: Query Exception: '.$e->getMessage(), [
                'query' => $qb->getSQL(), 'parameters' => $qb->getParameters(),
            ]);
            throw $e;
        }

        return $result;
    }

    /**
     * @param QueryBuilder $qb
     * @param int          $segmentId
     *
     * @return mixed
     *
     * @throws \Exception
     */
    private function timedFetchAll(QueryBuilder $qb, $segmentId)
    {
        try {
            $start  = microtime(true);
            $result = $qb->execute()->fetchAll(\PDO::FETCH_ASSOC);

            $end = microtime(true) - $start;

            $this->logger->debug('Segment QB: Query took: '.$this->format_period($end).'ms. Result count: '.count($result), ['segmentId' => $segmentId]);
        } catch (\Exception $e) {
            $this->logger->error('Segment QB: Query Exception: '.$e->getMessage(), [
                'query' => $qb->getSQL(), 'parameters' => $qb->getParameters(),
            ]);
            throw $e;
        }

        return $result;
    }
}
