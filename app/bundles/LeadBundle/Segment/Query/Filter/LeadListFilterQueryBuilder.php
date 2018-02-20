<?php
/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Segment\Query\Filter;

use Doctrine\ORM\EntityManager;
use Mautic\LeadBundle\Segment\LeadSegmentFilter;
use Mautic\LeadBundle\Segment\LeadSegmentFilterFactory;
use Mautic\LeadBundle\Segment\Query\LeadSegmentQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\RandomParameterName;

/**
 * Class LeadListFilterQueryBuilder.
 */
class LeadListFilterQueryBuilder extends BaseFilterQueryBuilder
{
    /**
     * @var LeadSegmentQueryBuilder
     */
    private $leadSegmentQueryBuilder;

    /**
     * @var LeadSegmentFilterFactory
     */
    private $leadSegmentFilterFactory;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * LeadListFilterQueryBuilder constructor.
     *
     * @param RandomParameterName      $randomParameterNameService
     * @param LeadSegmentQueryBuilder  $leadSegmentQueryBuilder
     * @param EntityManager            $entityManager
     * @param LeadSegmentFilterFactory $leadSegmentFilterFactory
     */
    public function __construct(
        RandomParameterName $randomParameterNameService,
        LeadSegmentQueryBuilder $leadSegmentQueryBuilder,
        EntityManager $entityManager,
        LeadSegmentFilterFactory $leadSegmentFilterFactory
    ) {
        parent::__construct($randomParameterNameService);

        $this->leadSegmentQueryBuilder  = $leadSegmentQueryBuilder;
        $this->leadSegmentFilterFactory = $leadSegmentFilterFactory;
        $this->entityManager            = $entityManager;
    }

    /**
     * @return string
     */
    public static function getServiceId()
    {
        return 'mautic.lead.query.builder.special.leadlist';
    }

    /**
     * @param QueryBuilder      $queryBuilder
     * @param LeadSegmentFilter $filter
     *
     * @return QueryBuilder
     *
     * @throws \Mautic\LeadBundle\Segment\Exception\SegmentQueryException
     */
    public function applyQuery(QueryBuilder $queryBuilder, LeadSegmentFilter $filter)
    {
        $segmentIds = $filter->getParameterValue();

        if (!is_array($segmentIds)) {
            $segmentIds = [intval($segmentIds)];
        }

        foreach ($segmentIds as $segmentId) {
            $exclusion = in_array($filter->getOperator(), ['notExists', 'notIn']);

            $contactSegments = $this->entityManager->getRepository('MauticLeadBundle:LeadList')->findBy(
                ['id' => $segmentId]
            );

            foreach ($contactSegments as $contactSegment) {
                $filters = $this->leadSegmentFilterFactory->getLeadListFilters($contactSegment);

                $segmentQueryBuilder = $this->leadSegmentQueryBuilder->assembleContactsSegmentQueryBuilder($filters);

                //  If the segment contains no filters; it means its for manually subscribed only
                if (count($filters)) {
                    $segmentQueryBuilder = $this->leadSegmentQueryBuilder->addManuallyUnsubsribedQuery($segmentQueryBuilder, $contactSegment->getId());
                }

                $segmentQueryBuilder = $this->leadSegmentQueryBuilder->addManuallySubscribedQuery($segmentQueryBuilder, $contactSegment->getId());
                $segmentQueryBuilder->select('l.id');

                $parameters = $segmentQueryBuilder->getParameters();
                foreach ($parameters as $key => $value) {
                    $queryBuilder->setParameter($key, $value);
                }

                $segmentAlias = $this->generateRandomParameterName();
                if ($exclusion) {
                    $queryBuilder->leftJoin('l', '('.$segmentQueryBuilder->getSQL().') ', $segmentAlias, $segmentAlias.'.id = l.id');
                    $expression = $queryBuilder->expr()->isNull($segmentAlias.'.id');
                } else {
                    $queryBuilder->leftJoin('l', '('.$segmentQueryBuilder->getSQL().') ', $segmentAlias, $segmentAlias.'.id = l.id');
                    $orExpressions[] = $queryBuilder->expr()->isNotNull($segmentAlias.'.id');
                }
                $queryBuilder->addSelect($segmentAlias.'.id as '.$segmentAlias.'_id');
            }
        }

        $expression = isset($orExpressions) ? '('.join(' OR ', $orExpressions).')' : $expression;

        $queryBuilder->addLogic($expression, $filter->getGlue());

        return $queryBuilder;
    }
}
