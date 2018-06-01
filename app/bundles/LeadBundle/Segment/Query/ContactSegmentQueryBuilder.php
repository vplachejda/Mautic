<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Segment\Query;

use Doctrine\ORM\EntityManager;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Event\LeadListFilteringEvent;
use Mautic\LeadBundle\Event\LeadListQueryBuilderGeneratedEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\ContactSegmentFilters;
use Mautic\LeadBundle\Segment\Exception\SegmentQueryException;
use Mautic\LeadBundle\Segment\RandomParameterName;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ContactSegmentQueryBuilder is responsible for building queries for segments.
 */
class ContactSegmentQueryBuilder
{
    /** @var EntityManager */
    private $entityManager;

    /** @var RandomParameterName */
    private $randomParameterName;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * ContactSegmentQueryBuilder constructor.
     *
     * @param EntityManager            $entityManager
     * @param RandomParameterName      $randomParameterName
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EntityManager $entityManager, RandomParameterName $randomParameterName, EventDispatcherInterface $dispatcher)
    {
        $this->entityManager       = $entityManager;
        $this->randomParameterName = $randomParameterName;
        $this->dispatcher          = $dispatcher;
    }

    /**
     * @param ContactSegmentFilters $contactSegmentFilters
     * @param null                  $backReference
     *
     * @return QueryBuilder
     *
     * @throws SegmentQueryException
     */
    public function assembleContactsSegmentQueryBuilder(ContactSegmentFilters $contactSegmentFilters, $backReference = null)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = new QueryBuilder($this->entityManager->getConnection());

        $queryBuilder->select('l.id')->from(MAUTIC_TABLE_PREFIX.'leads', 'l');

        $references = [];

        /** @var ContactSegmentFilter $filter */
        foreach ($contactSegmentFilters as $filter) {
            $segmentIdArray = is_array($filter->getParameterValue()) ? $filter->getParameterValue() : [$filter->getParameterValue()];

            //  We will handle references differently than regular segments
            if ($filter->isContactSegmentReference()) {
                if (!is_null($backReference) || in_array($backReference, $this->getContactSegmentRelations($segmentIdArray))) {
                    throw new SegmentQueryException('Circular reference detected.');
                }
                $references = $references + $segmentIdArray;
            }
            //  This has to run for every filter
            $filterCrate = $filter->contactSegmentFilterCrate->getArray();

            $queryBuilder = $filter->applyQuery($queryBuilder);

            if ($this->dispatcher->hasListeners(LeadEvents::LIST_FILTERS_ON_FILTERING)) {
                $alias = $this->generateRandomParameterName();
                $event = new LeadListFilteringEvent($filterCrate, null, $alias, $filterCrate['operator'], $queryBuilder, $this->entityManager);
                $this->dispatcher->dispatch(LeadEvents::LIST_FILTERS_ON_FILTERING, $event);
                if ($event->isFilteringDone()) {
                    $queryBuilder->andWhere($event->getSubQuery());
                }
            }
        }

        $queryBuilder->applyStackLogic();

        return $queryBuilder;
    }

    /**
     * Get the list of segment's related segments.
     *
     * @param $id array
     *
     * @return array
     */
    private function getContactSegmentRelations(array $id)
    {
        $referencedContactSegments = $this->entityManager->getRepository('MauticLeadBundle:LeadList')->findBy(
            ['id' => $id]
        );

        $relations = [];
        foreach ($referencedContactSegments as $segment) {
            $filters = $segment->getFilters();
            foreach ($filters as $filter) {
                if ($filter['field'] == 'leadlist') {
                    $relations[] = $filter['filter'];
                }
            }
        }

        return $relations;
    }

    /**
     * @param QueryBuilder $qb
     *
     * @return QueryBuilder
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function wrapInCount(QueryBuilder $qb)
    {
        // Add count functions to the query
        $queryBuilder = new QueryBuilder($this->entityManager->getConnection());

        //  If there is any right join in the query we need to select its it
        $primary = $qb->guessPrimaryLeadContactIdColumn();

        $currentSelects = [];
        foreach ($qb->getQueryParts()['select'] as $select) {
            if ($select != $primary) {
                $currentSelects[] = $select;
            }
        }

        $qb->select('DISTINCT '.$primary.' as leadIdPrimary');
        foreach ($currentSelects as $select) {
            $qb->addSelect($select);
        }

        $queryBuilder->select('count(leadIdPrimary) count, max(leadIdPrimary) maxId, min(leadIdPrimary) minId')
                     ->from('('.$qb->getSQL().')', 'sss');
        $queryBuilder->setParameters($qb->getParameters());

        return $queryBuilder;
    }

    /**
     * Restrict the query to NEW members of segment.
     *
     * @param QueryBuilder $queryBuilder
     * @param              $segmentId
     * @param              $batchRestrictions
     *
     * @return QueryBuilder
     *
     * @throws QueryException
     */
    public function addNewContactsRestrictions(QueryBuilder $queryBuilder, $segmentId, $batchRestrictions)
    {
        $parts     = $queryBuilder->getQueryParts();
        $setHaving = (count($parts['groupBy']) || !is_null($parts['having']));

        $tableAlias = $this->generateRandomParameterName();
        $queryBuilder->leftJoin('l', MAUTIC_TABLE_PREFIX.'lead_lists_leads', $tableAlias, $tableAlias.'.lead_id = l.id');
        $queryBuilder->addSelect($tableAlias.'.lead_id AS '.$tableAlias.'_lead_id');

        if (isset($batchRestrictions['dateTime'])) {
            $expression = $queryBuilder->expr()->andX(
                $queryBuilder->expr()->eq($tableAlias.'.leadlist_id', $segmentId),
                $queryBuilder->expr()->lte('l.date_added', "'".$batchRestrictions['dateTime']."'")
            );
        } else {
            $expression = $queryBuilder->expr()->eq($tableAlias.'.leadlist_id', $segmentId);
        }
        
        $queryBuilder->addJoinCondition($tableAlias, $expression);

        if ($setHaving) {
            $restrictionExpression = $queryBuilder->expr()->isNull($tableAlias.'_lead_id');
            $queryBuilder->andHaving($restrictionExpression);
        } else {
            $restrictionExpression = $queryBuilder->expr()->isNull($tableAlias.'.lead_id');
            $queryBuilder->andWhere($restrictionExpression);
        }

        return $queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param              $leadListId
     *
     * @return QueryBuilder
     *
     * @throws QueryException
     */
    public function addManuallySubscribedQuery(QueryBuilder $queryBuilder, $leadListId)
    {
        $tableAlias = $this->generateRandomParameterName();
        $queryBuilder->leftJoin('l', MAUTIC_TABLE_PREFIX.'lead_lists_leads', $tableAlias,
                                'l.id = '.$tableAlias.'.lead_id and '.$tableAlias.'.leadlist_id = '.intval($leadListId));
        $queryBuilder->addJoinCondition($tableAlias,
                                        $queryBuilder->expr()->andX(
                                            $queryBuilder->expr()->eq($tableAlias.'.manually_added', 1)
                                        )
        );
        $queryBuilder->orWhere($queryBuilder->expr()->isNotNull($tableAlias.'.lead_id'));

        return $queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param              $leadListId
     *
     * @return QueryBuilder
     *
     * @throws QueryException
     */
    public function addManuallyUnsubscribedQuery(QueryBuilder $queryBuilder, $leadListId)
    {
        $tableAlias = $this->generateRandomParameterName();
        $queryBuilder->leftJoin('l', MAUTIC_TABLE_PREFIX.'lead_lists_leads', $tableAlias,
                                'l.id = '.$tableAlias.'.lead_id and '.$tableAlias.'.leadlist_id = '.intval($leadListId));
        $queryBuilder->addJoinCondition($tableAlias, $queryBuilder->expr()->eq($tableAlias.'.manually_removed', 1));
        $queryBuilder->andWhere($queryBuilder->expr()->isNull($tableAlias.'.lead_id'));

        return $queryBuilder;
    }

    /**
     * @param LeadList     $segment
     * @param QueryBuilder $queryBuilder
     */
    public function queryBuilderGenerated(LeadList $segment, QueryBuilder $queryBuilder)
    {
        if (!$this->dispatcher->hasListeners(LeadEvents::LIST_FILTERS_QUERYBUILDER_GENERATED)) {
            return;
        }

        $event = new LeadListQueryBuilderGeneratedEvent($segment, $queryBuilder);
        $this->dispatcher->dispatch(LeadEvents::LIST_FILTERS_QUERYBUILDER_GENERATED, $event);
    }

    /**
     * Generate a unique parameter name.
     *
     * @return string
     */
    private function generateRandomParameterName()
    {
        return $this->randomParameterName->generateRandomParameterName();
    }
}
