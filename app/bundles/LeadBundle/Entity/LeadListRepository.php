<?php
/**
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Entity;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\TimeType;
use Doctrine\ORM\PersistentCollection;
use Mautic\CoreBundle\Doctrine\QueryFormatter\AbstractFormatter;
use Mautic\CoreBundle\Doctrine\Type\UTCDateTimeType;
use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Helper\InputHelper;

/**
 * LeadListRepository.
 */
class LeadListRepository extends CommonRepository
{
    use OperatorListTrait;
    use ExpressionHelperTrait;

    /**
     * @var bool
     */
    protected $listFiltersInnerJoinCompany = false;

    /**
     * {@inheritdoc}
     *
     * @param int $id
     *
     * @return mixed|null
     */
    public function getEntity($id = 0)
    {
        try {
            $entity = $this
                ->createQueryBuilder('l')
                ->where('l.id = :listId')
                ->setParameter('listId', $id)
                ->getQuery()
                ->getSingleResult();
        } catch (\Exception $e) {
            $entity = null;
        }

        return $entity;
    }

    /**
     * Get a list of lists.
     *
     * @param bool   $user
     * @param string $alias
     * @param string $id
     *
     * @return array
     */
    public function getLists($user = false, $alias = '', $id = '')
    {
        static $lists = [];

        if (is_object($user)) {
            $user = $user->getId();
        }

        $key = (int) $user.$alias.$id;
        if (isset($lists[$key])) {
            return $lists[$key];
        }

        $q = $this->_em->createQueryBuilder()
            ->from('MauticLeadBundle:LeadList', 'l', 'l.id');

        $q->select('partial l.{id, name, alias}')
            ->andWhere($q->expr()->eq('l.isPublished', ':true'))
            ->setParameter('true', true, 'boolean');

        if (!empty($user)) {
            $q->andWhere($q->expr()->eq('l.isGlobal', ':true'));
            $q->orWhere('l.createdBy = :user');
            $q->setParameter('user', $user);
        }

        if (!empty($alias)) {
            $q->andWhere('l.alias = :alias');
            $q->setParameter('alias', $alias);
        }

        if (!empty($id)) {
            $q->andWhere(
                $q->expr()->neq('l.id', $id)
            );
        }

        $q->orderBy('l.name');

        $results = $q->getQuery()->getArrayResult();

        $lists[$key] = $results;

        return $results;
    }

    /**
     * Get lists for a specific lead.
     *
     * @param      $lead
     * @param bool $forList
     * @param bool $singleArrayHydration
     *
     * @return mixed
     */
    public function getLeadLists($lead, $forList = false, $singleArrayHydration = false)
    {
        if (is_array($lead)) {
            $q = $this->_em->createQueryBuilder()
                ->from('MauticLeadBundle:LeadList', 'l', 'l.id');

            if ($forList) {
                $q->select('partial l.{id, alias, name}, partial il.{lead, list, dateAdded, manuallyAdded, manuallyRemoved}');
            } else {
                $q->select('l, partial lead.{id}');
            }

            $q->leftJoin('l.leads', 'il')
                ->leftJoin('il.lead', 'lead');

            $q->where(
                $q->expr()->andX(
                    $q->expr()->in('lead.id', ':leads'),
                    $q->expr()->in('il.manuallyRemoved', ':false')
                )
            )
                ->setParameter('leads', $lead)
                ->setParameter('false', false, 'boolean');

            $result = $q->getQuery()->getArrayResult();

            $return = [];
            foreach ($result as $r) {
                foreach ($r['leads'] as $l) {
                    $return[$l['lead_id']][$r['id']] = $r;
                }
            }

            return $return;
        } else {
            $q = $this->_em->createQueryBuilder()
                ->from('MauticLeadBundle:LeadList', 'l', 'l.id');

            if ($forList) {
                $q->select('partial l.{id, alias, name}, partial il.{lead, list, dateAdded, manuallyAdded, manuallyRemoved}');
            } else {
                $q->select('l');
            }

            $q->leftJoin('l.leads', 'il');

            $q->where(
                $q->expr()->andX(
                    $q->expr()->eq('IDENTITY(il.lead)', (int) $lead),
                    $q->expr()->in('il.manuallyRemoved', ':false')
                )
            )
                ->setParameter('false', false, 'boolean');

            return ($singleArrayHydration) ? $q->getQuery()->getArrayResult() : $q->getQuery()->getResult();
        }
    }

    /**
     * Return a list of global lists.
     *
     * @return array
     */
    public function getGlobalLists()
    {
        $q = $this->_em->createQueryBuilder()
            ->from('MauticLeadBundle:LeadList', 'l', 'l.id');

        $q->select('partial l.{id, name, alias}')
            ->where($q->expr()->eq('l.isPublished', 'true'))
            ->setParameter(':true', true, 'boolean')
            ->andWhere($q->expr()->eq('l.isGlobal', ':true'))
            ->orderBy('l.name');

        $results = $q->getQuery()->getArrayResult();

        return $results;
    }

    /**
     * Get a count of leads that belong to the list.
     *
     * @param $listIds
     *
     * @return array
     */
    public function getLeadCount($listIds)
    {
        $q = $this->_em->getConnection()->createQueryBuilder();

        $q->select('count(l.lead_id) as thecount, l.leadlist_id')
            ->from(MAUTIC_TABLE_PREFIX.'lead_lists_leads', 'l');

        $returnArray = (is_array($listIds));

        if (!$returnArray) {
            $listIds = [$listIds];
        }

        $q->where(
            $q->expr()->in('l.leadlist_id', $listIds),
            $q->expr()->eq('l.manually_removed', ':false')
        )
            ->setParameter('false', false, 'boolean')
            ->groupBy('l.leadlist_id');

        $result = $q->execute()->fetchAll();

        $return = [];
        foreach ($result as $r) {
            $return[$r['leadlist_id']] = $r['thecount'];
        }

        // Ensure lists without leads have a value
        foreach ($listIds as $l) {
            if (!isset($return[$l])) {
                $return[$l] = 0;
            }
        }

        return ($returnArray) ? $return : $return[$listIds[0]];
    }

    /**
     * @param       $lists
     * @param array $args
     *
     * @return array
     */
    public function getLeadsByList($lists, $args = [])
    {
        // Return only IDs
        $idOnly = (!array_key_exists('idOnly', $args)) ? false : $args['idOnly'];
        // Return counts
        $countOnly = (!array_key_exists('countOnly', $args)) ? false : $args['countOnly'];
        // Return only leads that have not been added or manually manipulated to the lists yet
        $newOnly = (!array_key_exists('newOnly', $args)) ? false : $args['newOnly'];
        // Return leads that do not belong to a list based on filters
        $nonMembersOnly = (!array_key_exists('nonMembersOnly', $args)) ? false : $args['nonMembersOnly'];
        // Use filters to dynamically generate the list
        $dynamic = ($newOnly || $nonMembersOnly);
        // Limiters
        $batchLimiters = (!array_key_exists('batchLimiters', $args)) ? false : $args['batchLimiters'];
        $start         = (!array_key_exists('start', $args)) ? false : $args['start'];
        $limit         = (!array_key_exists('limit', $args)) ? false : $args['limit'];
        $withMinId     = (!array_key_exists('withMinId', $args)) ? false : $args['withMinId'];

        if (!$lists instanceof PersistentCollection && !is_array($lists) || isset($lists['id'])) {
            $lists = [$lists];
        }

        $return = [];
        foreach ($lists as $l) {
            $leads = ($countOnly) ? 0 : [];

            if ($l instanceof LeadList) {
                $id      = $l->getId();
                $filters = $l->getFilters();
            } elseif (is_array($l)) {
                $id      = $l['id'];
                $filters = (!$dynamic) ? [] : $l['filters'];
            } elseif (!$dynamic) {
                $id      = $l;
                $filters = [];
            }

            $objectFilters         = $this->arrangeFilters($filters);
            $includesContactFields = isset($objectFilters['lead']);
            $includesCompanyFields = isset($objectFilters['company']);

            if ($dynamic && count($filters)) {
                $q          = $this->getEntityManager()->getConnection()->createQueryBuilder();
                $parameters = [];

                if ($includesContactFields) {
                    $expr = $this->getListFilterExpr($objectFilters['lead'], $parameters, $q, false, null, 'lead');
                } else {
                    $expr = $q->expr()->andX();
                }
                if ($includesCompanyFields) {
                    $this->listFiltersInnerJoinCompany = false;
                    $exprCompany                       = $this->getListFilterExpr($objectFilters['company'], $parameters, $q, false, null, 'company');
                }

                foreach ($parameters as $k => $v) {
                    switch (true) {
                        case is_bool($v):
                            $paramType = 'boolean';
                            break;

                        case is_int($v):
                            $paramType = 'integer';
                            break;

                        case is_float($v):
                            $paramType = 'float';
                            break;

                        default:
                            $paramType = null;
                            break;
                    }
                    $q->setParameter($k, $v, $paramType);
                }

                if ($countOnly) {
                    $count  = ($includesCompanyFields) ? 'count(distinct(l.id))' : 'count(l.id)';
                    $select = $count.' as lead_count, max(l.id) as max_id';
                    if ($withMinId) {
                        $select .= ', min(l.id) as min_id';
                    }
                } elseif ($idOnly) {
                    $select = 'l.id';
                } else {
                    $select = 'l.*';
                }

                $q->select($select)
                    ->from(MAUTIC_TABLE_PREFIX.'leads', 'l');

                $batchExpr = $q->expr()->andX();
                // Only leads that existed at the time of count
                if ($batchLimiters) {
                    if (!empty($batchLimiters['minId']) && !empty($batchLimiters['maxId'])) {
                        $batchExpr->add(
                            $q->expr()->comparison('l.id', 'BETWEEN', "{$batchLimiters['minId']} and {$batchLimiters['maxId']}")
                        );
                    } elseif (!empty($batchLimiters['maxId'])) {
                        $batchExpr->add(
                            $q->expr()->lte('l.id', $batchLimiters['maxId'])
                        );
                    }
                }

                if ($newOnly) {
                    if ($includesCompanyFields) {
                        $this->applyCompanyFieldFilters($q, $exprCompany);
                    }

                    // Leads that do not have any record in the lead_lists_leads table for this lead list
                    // For non null fields - it's apparently better to use left join over not exists due to not using nullable
                    // fields - https://explainextended.com/2009/09/18/not-in-vs-not-exists-vs-left-join-is-null-mysql/
                    $listOnExpr = $q->expr()->andX(
                        $q->expr()->eq('ll.leadlist_id', $id),
                        $q->expr()->eq('ll.lead_id', 'l.id')
                    );

                    if (!empty($batchLimiters['dateTime'])) {
                        // Only leads in the list at the time of count
                        $listOnExpr->add(
                            $q->expr()->lte('ll.date_added', $q->expr()->literal($batchLimiters['dateTime']))
                        );
                    }

                    $q->leftJoin(
                        'l',
                        MAUTIC_TABLE_PREFIX.'lead_lists_leads',
                        'll',
                        $listOnExpr
                    );
                    $expr->add($q->expr()->isNull('ll.lead_id'));

                    if ($batchExpr->count()) {
                        $expr->add($batchExpr);
                    }

                    $q->andWhere($expr);
                } elseif ($nonMembersOnly) {
                    // Only leads that are part of the list that no longer match filters and have not been manually removed
                    $q->join('l', MAUTIC_TABLE_PREFIX.'lead_lists_leads', 'll', 'l.id = ll.lead_id');

                    $mainExpr = $q->expr()->andX();
                    if ($batchLimiters && !empty($batchLimiters['dateTime'])) {
                        // Only leads in the list at the time of count
                        $mainExpr->add(
                            $q->expr()->lte('ll.date_added', $q->expr()->literal($batchLimiters['dateTime']))
                        );
                    }

                    // Ignore those that have been manually added
                    $mainExpr->addMultiple(
                        [
                            $q->expr()->eq('ll.manually_added', ':false'),
                            $q->expr()->eq('ll.leadlist_id', (int) $id),
                        ]
                    );
                    $q->setParameter('false', false, 'boolean');

                    // Find the contacts that are in the segment but no longer have filters that are applicable
                    $sq = $this->getEntityManager()->getConnection()->createQueryBuilder();
                    $sq->select('l.id')
                        ->from(MAUTIC_TABLE_PREFIX.'leads', 'l');

                    if ($includesContactFields) {
                        $sq->andWhere($expr);
                    }

                    if ($includesCompanyFields) {
                        $this->applyCompanyFieldFilters($sq, $exprCompany);
                    }

                    $mainExpr->add(
                        sprintf('l.id NOT IN (%s)', $sq->getSQL())
                    );

                    if ($batchExpr->count()) {
                        $mainExpr->add($batchExpr);
                    }

                    $q->andWhere($mainExpr);
                }

                // Set limits if applied
                if (!empty($limit)) {
                    $q->setFirstResult($start)
                        ->setMaxResults($limit);
                }

                if ($countOnly) {
                    // remove any possible group by
                    $q->resetQueryPart('groupBy');
                }

                $results = $q->execute()->fetchAll();

                foreach ($results as $r) {
                    if ($countOnly) {
                        $leads = [
                            'count' => $r['lead_count'],
                            'maxId' => $r['max_id'],
                        ];
                        if ($withMinId) {
                            $leads['minId'] = $r['min_id'];
                        }
                    } elseif ($idOnly) {
                        $leads[$r['id']] = $r['id'];
                    } else {
                        $leads[$r['id']] = $r;
                    }
                }
            } elseif (!$dynamic) {
                $q = $this->_em->getConnection()->createQueryBuilder();
                if ($countOnly) {
                    $q->select('max(ll.lead_id) as max_id, count(ll.lead_id) as lead_count')
                        ->from(MAUTIC_TABLE_PREFIX.'lead_lists_leads', 'll');
                } elseif ($idOnly) {
                    $q->select('ll.lead_id as id')
                        ->from(MAUTIC_TABLE_PREFIX.'lead_lists_leads', 'll');
                } else {
                    $q->select('l.*')
                        ->from(MAUTIC_TABLE_PREFIX.'leads', 'l')
                        ->join('l', MAUTIC_TABLE_PREFIX.'lead_lists_leads', 'll', 'l.id = ll.lead_id');
                }

                // Filter by list
                $expr = $q->expr()->andX(
                    $q->expr()->eq('ll.leadlist_id', ':list'),
                    $q->expr()->eq('ll.manually_removed', ':false')
                );

                $q->setParameter('list', (int) $id)
                    ->setParameter('false', false, 'boolean');

                // Set limits if applied
                if (!empty($limit)) {
                    $q->setFirstResult($start)
                        ->setMaxResults($limit);
                }

                $q->where($expr);

                $results = $q->execute()->fetchAll();

                foreach ($results as $r) {
                    if ($countOnly) {
                        $leads = [
                            'count' => $r['lead_count'],
                            'maxId' => $r['max_id'],
                        ];
                    } elseif ($idOnly) {
                        $leads[] = $r['id'];
                    } else {
                        $leads[] = $r;
                    }
                }
            }

            $return[$id] = $leads;

            unset($filters, $parameters, $q, $expr, $results, $dynamicExpr, $leads);
        }

        return $return;
    }

    /**
     * @param $filters
     *
     * @return array
     */
    public function arrangeFilters($filters)
    {
        $objectFilters = [];
        if (empty($filters)) {
            $objectFilters['lead'][] = $filters;
        }
        foreach ($filters as $filter) {
            $object = (isset($filter['object'])) ? $filter['object'] : 'lead';
            switch ($object) {
                case 'company':
                    $objectFilters['company'][] = $filter;
                    break;
                default:
                    $objectFilters['lead'][] = $filter;
                    break;
            }
        }

        return $objectFilters;
    }

    /**
     * @param              $filters
     * @param              $parameters
     * @param QueryBuilder $q
     * @param bool         $not
     * @param null         $leadId
     * @param string       $object
     *
     * @return \Doctrine\DBAL\Query\Expression\CompositeExpression|mixed
     */
    public function getListFilterExpr($filters, &$parameters, QueryBuilder $q, $not = false, $leadId = null, $object = 'lead')
    {
        static $leadTable;
        static $companyTable;

        if (!count($filters)) {
            return $q->expr()->andX();
        }

        $isCompany = ('company' === $object);
        $schema    = $this->_em->getConnection()->getSchemaManager();
        // Get table columns
        if (null === $leadTable) {
            /** @var \Doctrine\DBAL\Schema\Column[] $leadTable */
            $leadTable = $schema->listTableColumns(MAUTIC_TABLE_PREFIX.'leads');
        }
        if (null === $companyTable) {
            $companyTable = $schema->listTableColumns(MAUTIC_TABLE_PREFIX.'companies');
        }
        $options   = $this->getFilterExpressionFunctions();
        $groups    = [];
        $groupExpr = $q->expr()->andX();

        foreach ($filters as $k => $details) {
            if (isset($details['object']) && $details['object'] != $object) {
                continue;
            }

            if ($object == 'lead') {
                $column = isset($leadTable[$details['field']]) ? $leadTable[$details['field']] : false;
            } elseif ($object == 'company') {
                $column = isset($companyTable[$details['field']]) ? $companyTable[$details['field']] : false;
            }

            //DBAL does not have a not() function so we have to use the opposite
            $func = (!$not)
                ? $options[$details['operator']]['expr']
                :
                $options[$details['operator']]['negate_expr'];
            if ($object == 'lead') {
                $field = "l.{$details['field']}";
            } elseif ($object == 'company') {
                $field = "comp.{$details['field']}";
            }
            // Format the field based on platform specific functions that DBAL doesn't support natively
            if ($column) {
                $formatter  = AbstractFormatter::createFormatter($this->_em->getConnection());
                $columnType = $column->getType();

                switch ($details['type']) {
                    case 'datetime':
                        if (!$columnType instanceof UTCDateTimeType) {
                            $field = $formatter->toDateTime($field);
                        }
                        break;
                    case 'date':
                        if (!$columnType instanceof DateType && !$columnType instanceof UTCDateTimeType) {
                            $field = $formatter->toDate($field);
                        }
                        break;
                    case 'time':
                        if (!$columnType instanceof TimeType && !$columnType instanceof UTCDateTimeType) {
                            $field = $formatter->toTime($field);
                        }
                        break;
                    case 'number':
                        if (!$columnType instanceof IntegerType && !$columnType instanceof FloatType) {
                            $field = $formatter->toNumeric($field);
                        }
                        break;
                }
            }

            //the next one will determine the group
            if ($details['glue'] == 'or') {
                // Create a new group of andX expressions
                if ($groupExpr->count()) {
                    $groups[]  = $groupExpr;
                    $groupExpr = $q->expr()->andX();
                }
            }

            $parameter        = $this->generateRandomParameterName();
            $exprParameter    = ":$parameter";
            $ignoreAutoFilter = false;

            // Special handling of relative date strings
            if ($details['type'] == 'datetime' || $details['type'] == 'date') {
                $relativeDateStrings = $this->getRelativeDateStrings();
                // Check if the column type is a date/time stamp
                $isTimestamp = ($columnType instanceof UTCDateTimeType || $details['type'] == 'datetime');
                $getDate     = function (&$string) use ($isTimestamp, $relativeDateStrings, &$details, &$func, $not) {
                    $key             = array_search($string, $relativeDateStrings);
                    $dtHelper        = new DateTimeHelper('midnight today', null, 'local');
                    $requiresBetween = (in_array($func, ['eq', 'neq']) && $isTimestamp);

                    $timeframe  = str_replace('mautic.lead.list.', '', $key);
                    $modifier   = false;
                    $isRelative = true;

                    switch ($timeframe) {
                        case 'today':
                        case 'tomorrow':
                        case 'yesterday':
                            if ($timeframe == 'yesterday') {
                                $dtHelper->modify('-1 day');
                            } elseif ($timeframe == 'tomorrow') {
                                $dtHelper->modify('+1 day');
                            }

                            // Today = 2015-08-28 00:00:00
                            if ($requiresBetween) {
                                // eq:
                                //  field >= 2015-08-28 00:00:00
                                //  field < 2015-08-29 00:00:00

                                // neq:
                                // field < 2015-08-28 00:00:00
                                // field >= 2015-08-29 00:00:00
                                $modifier = '+1 day';
                            } else {
                                // lt:
                                //  field < 2015-08-28 00:00:00
                                // gt:
                                //  field > 2015-08-28 23:59:59

                                // lte:
                                //  field <= 2015-08-28 23:59:59
                                // gte:
                                //  field >= 2015-08-28 00:00:00
                                if (in_array($func, ['gt', 'lte'])) {
                                    $modifier = '+1 day -1 second';
                                }
                            }
                            break;
                        case 'week_last':
                        case 'week_next':
                        case 'week_this':
                            $interval = str_replace('week_', '', $timeframe);
                            $dtHelper->setDateTime('midnight monday '.$interval.' week', null);

                            // This week: Monday 2015-08-24 00:00:00
                            if ($requiresBetween) {
                                // eq:
                                //  field >= Mon 2015-08-24 00:00:00
                                //  field <  Mon 2015-08-31 00:00:00

                                // neq:
                                // field <  Mon 2015-08-24 00:00:00
                                // field >= Mon 2015-08-31 00:00:00
                                $modifier = '+1 week';
                            } else {
                                // lt:
                                //  field < Mon 2015-08-24 00:00:00
                                // gt:
                                //  field > Sun 2015-08-30 23:59:59

                                // lte:
                                //  field <= Sun 2015-08-30 23:59:59
                                // gte:
                                //  field >= Mon 2015-08-24 00:00:00
                                if (in_array($func, ['gt', 'lte'])) {
                                    $modifier = '+1 week -1 second';
                                }
                            }
                            break;

                        case 'month_last':
                        case 'month_next':
                        case 'month_this':
                            $interval = substr($key, -4);
                            $dtHelper->setDateTime('midnight first day of '.$interval.' month', null);

                            // This month: 2015-08-01 00:00:00
                            if ($requiresBetween) {
                                // eq:
                                //  field >= 2015-08-01 00:00:00
                                //  field <  2015-09:01 00:00:00

                                // neq:
                                // field <  2015-08-01 00:00:00
                                // field >= 2016-09-01 00:00:00
                                $modifier = '+1 month';
                            } else {
                                // lt:
                                //  field < 2015-08-01 00:00:00
                                // gt:
                                //  field > 2015-08-31 23:59:59

                                // lte:
                                //  field <= 2015-08-31 23:59:59
                                // gte:
                                //  field >= 2015-08-01 00:00:00
                                if (in_array($func, ['gt', 'lte'])) {
                                    $modifier = '+1 month -1 second';
                                }
                            }
                            break;
                        case 'year_last':
                        case 'year_next':
                        case 'year_this':
                            $interval = substr($key, -4);
                            $dtHelper->setDateTime('midnight first day of '.$interval.' year', null);

                            // This year: 2015-01-01 00:00:00
                            if ($requiresBetween) {
                                // eq:
                                //  field >= 2015-01-01 00:00:00
                                //  field <  2016-01-01 00:00:00

                                // neq:
                                // field <  2015-01-01 00:00:00
                                // field >= 2016-01-01 00:00:00
                                $modifier = '+1 year';
                            } else {
                                // lt:
                                //  field < 2015-01-01 00:00:00
                                // gt:
                                //  field > 2015-12-31 23:59:59

                                // lte:
                                //  field <= 2015-12-31 23:59:59
                                // gte:
                                //  field >= 2015-01-01 00:00:00
                                if (in_array($func, ['gt', 'lte'])) {
                                    $modifier = '+1 year -1 second';
                                }
                            }
                            break;
                        default:
                            $isRelative = false;
                            break;
                    }

                    // check does this match php date params pattern?
                    if (stristr($string[0], '-') or stristr($string[0], '+')) {
                        $date = new \DateTime('now');
                        $date->modify($string);
                        $dateTime = $date->format('Y-m-d H:i:s');
                        $dtHelper->setDateTime($dateTime, null);
                        $isRelative = true;
                    }

                    if ($isRelative) {
                        if ($requiresBetween) {
                            $startWith = ($isTimestamp) ? $dtHelper->toUtcString('Y-m-d H:i:s') : $dtHelper->toUtcString('Y-m-d');

                            $dtHelper->modify($modifier);
                            $endWith = ($isTimestamp) ? $dtHelper->toUtcString('Y-m-d H:i:s') : $dtHelper->toUtcString('Y-m-d');

                            // Use a between statement
                            $func              = ($func == 'neq') ? 'notBetween' : 'between';
                            $details['filter'] = [$startWith, $endWith];
                        } else {
                            if ($modifier) {
                                $dtHelper->modify($modifier);
                            }

                            $details['filter'] = ($isTimestamp) ? $dtHelper->toUtcString('Y-m-d H:i:s') : $dtHelper->toUtcString('Y-m-d');
                        }
                    }
                };

                if (is_array($details['filter'])) {
                    foreach ($details['filter'] as &$filterValue) {
                        $getDate($filterValue);
                    }
                } else {
                    $getDate($details['filter']);
                }
            }

            // Generate a unique alias
            $alias = $this->generateRandomParameterName();

            switch ($details['field']) {
                case 'hit_url':
                    $operand = (($func == 'eq') || ($func == 'like')) ? 'EXISTS' : 'NOT EXISTS';

                    $subqb = $this->_em->getConnection()
                        ->createQueryBuilder()
                        ->select('id')
                        ->from(MAUTIC_TABLE_PREFIX.'page_hits', $alias);
                    switch ($func) {
                        case 'eq':
                        case 'neq':
                            $parameters[$parameter] = $details['filter'];

                            $subqb->where($q->expr()
                                ->andX($q->expr()
                                ->eq($alias.'.url', $exprParameter), $q->expr()
                                ->eq($alias.'.lead_id', 'l.id')));
                            break;
                        case 'like':
                        case '!like':
                            $details['filter'] = '%'.$details['filter'].'%';
                            $subqb->where($q->expr()
                                ->andX($q->expr()
                                ->like($alias.'.url', $exprParameter), $q->expr()
                                ->eq($alias.'.lead_id', 'l.id')));
                            break;
                    }
                    // Specific lead
                    if (!empty($leadId)) {
                        $subqb->andWhere($subqb->expr()
                            ->eq($alias.'.lead_id', $leadId));
                    }
                    $groupExpr->add(sprintf('%s (%s)', $operand, $subqb->getSQL()));
                    break;

                case 'dnc_bounced':
                case 'dnc_unsubscribed':
                case 'dnc_bounced_sms':
                case 'dnc_unsubscribed_sms':
                    // Special handling of do not contact
                    $func = (($func == 'eq' && $details['filter']) || ($func == 'neq' && !$details['filter'])) ? 'EXISTS' : 'NOT EXISTS';

                    $parts   = explode('_', $details['field']);
                    $channel = 'email';

                    if (count($parts) === 3) {
                        $channel = $parts[2];
                    }

                    $channelParameter = $this->generateRandomParameterName();
                    $subqb            = $this->_em->getConnection()->createQueryBuilder()
                        ->select('null')
                        ->from(MAUTIC_TABLE_PREFIX.'lead_donotcontact', $alias)
                        ->where(
                            $q->expr()->andX(
                                $q->expr()->eq($alias.'.reason', $exprParameter),
                                $q->expr()->eq($alias.'.lead_id', 'l.id'),
                                $q->expr()->eq($alias.'.channel', ":$channelParameter")
                            )
                        );

                    // Specific lead
                    if (!empty($leadId)) {
                        $subqb->andWhere(
                            $subqb->expr()->eq($alias.'.lead_id', $leadId)
                        );
                    }

                    $groupExpr->add(
                        sprintf('%s (%s)', $func, $subqb->getSQL())
                    );

                    // Filter will always be true and differentiated via EXISTS/NOT EXISTS
                    $details['filter'] = true;

                    $ignoreAutoFilter = true;

                    $parameters[$parameter]        = ($parts[1] === 'bounced') ? DoNotContact::BOUNCED : DoNotContact::UNSUBSCRIBED;
                    $parameters[$channelParameter] = $channel;

                    break;

                case 'leadlist':
                case 'tags':
                case 'lead_email_received':

                    // Special handling of lead lists and tags
                    $func = in_array($func, ['eq', 'in']) ? 'EXISTS' : 'NOT EXISTS';

                    $ignoreAutoFilter = true;
                    foreach ($details['filter'] as &$value) {
                        $value = (int) $value;
                    }

                    $subQb   = $this->_em->getConnection()->createQueryBuilder();
                    $subExpr = $subQb->expr()->andX(
                        $subQb->expr()->eq($alias.'.lead_id', 'l.id')
                    );

                    // Specific lead
                    if (!empty($leadId)) {
                        $subExpr->add(
                            $subQb->expr()->eq($alias.'.lead_id', $leadId)
                        );
                    }

                    switch ($details['field']) {
                        case 'leadlist':
                            $table  = 'lead_lists_leads';
                            $column = 'leadlist_id';

                            $falseParameter = $this->generateRandomParameterName();
                            $subExpr->add(
                                $subQb->expr()->eq($alias.'.manually_removed', ":$falseParameter")
                            );
                            $parameters[$falseParameter] = false;
                            break;
                        case 'tags':
                            $table  = 'lead_tags_xref';
                            $column = 'tag_id';
                            break;
                        case 'lead_email_received':
                            $table  = 'email_stats';
                            $column = 'email_id';

                            $trueParameter = $this->generateRandomParameterName();
                            $subExpr->add(
                                $subQb->expr()->eq($alias.'.is_read', ":$trueParameter")
                            );
                            $parameters[$trueParameter] = true;
                            break;
                    }

                    $subExpr->add(
                        $subQb->expr()->in(sprintf('%s.%s', $alias, $column), $details['filter'])
                    );

                    $subQb->select('null')
                        ->from(MAUTIC_TABLE_PREFIX.$table, $alias)
                        ->where($subExpr);

                    $groupExpr->add(
                        sprintf('%s (%s)', $func, $subQb->getSQL())
                    );

                    break;
                case 'stage':
                    $operand = in_array($func, ['eq', 'neq']) ? 'EXISTS' : 'NOT EXISTS';

                    $subQb = $this->_em->getConnection()
                        ->createQueryBuilder()
                        ->select('null')
                        ->from(MAUTIC_TABLE_PREFIX.'stages', $alias);
                    switch ($func) {
                        case 'eq':
                        case 'neq':
                            $parameters[$parameter] = $details['filter'];
                            $subQb->where(
                                $q->expr()->andX(
                                    $q->expr()->eq($alias.'.id', 'l.stage_id'),
                                    $q->expr()->eq($alias.'.id', ":$parameter")
                                )
                            );
                            break;
                    }

                    $groupExpr->add(sprintf('%s (%s)', $operand, $subQb->getSQL()));

                    break;
                default:
                    if ($isCompany) {
                        // Must tell getLeadsByList how to best handle the relationship with the companies table
                        if (!in_array($func, ['empty', 'neq', 'notIn', 'notLike'])) {
                            $this->listFiltersInnerJoinCompany = true;
                        }
                    }

                    switch ($func) {
                        case 'between':
                        case 'notBetween':
                            // Filter should be saved with double || to separate options
                            $parameter2              = $this->generateRandomParameterName();
                            $parameters[$parameter]  = $details['filter'][0];
                            $parameters[$parameter2] = $details['filter'][1];
                            $exprParameter2          = ":$parameter2";
                            $ignoreAutoFilter        = true;

                            if ($func == 'between') {
                                $groupExpr->add(
                                    $q->expr()->andX(
                                        $q->expr()->gte($field, $exprParameter),
                                        $q->expr()->lt($field, $exprParameter2)
                                    )
                                );
                            } else {
                                $groupExpr->add(
                                    $q->expr()->andX(
                                        $q->expr()->lt($field, $exprParameter),
                                        $q->expr()->gte($field, $exprParameter2)
                                    )
                                );
                            }
                            break;

                        case 'notEmpty':
                            $groupExpr->add(
                                $q->expr()->andX(
                                    $q->expr()->isNotNull($field),
                                    $q->expr()->neq($field, $q->expr()->literal(''))
                                )
                            );
                            $ignoreAutoFilter = true;
                            break;

                        case 'empty':
                            $details['filter'] = '';
                            $groupExpr->add(
                                $this->generateFilterExpression($q, $field, 'eq', $exprParameter, true)
                            );
                            break;

                        case 'in':
                        case 'notIn':
                            foreach ($details['filter'] as &$value) {
                                $value = $q->expr()->literal(
                                    InputHelper::clean($value)
                                );
                            }
                            $groupExpr->add(
                                $this->generateFilterExpression($q, $field, $func, $details['filter'], null)
                            );

                            $ignoreAutoFilter = true;
                            break;

                        case 'neq':
                            $groupExpr->add(
                                $this->generateFilterExpression($q, $field, $func, $exprParameter, null)
                            );
                            break;

                        case 'like':
                        case 'notLike':
                            if (strpos($details['filter'], '%') === false) {
                                $details['filter'] = '%'.$details['filter'].'%';
                            }

                            $groupExpr->add(
                                $this->generateFilterExpression($q, $field, $func, $exprParameter, null)
                            );
                            break;

                        default:
                            $groupExpr->add($q->expr()->$func($field, $exprParameter));
                    }
            }

            if (!$ignoreAutoFilter) {
                if (!is_array($details['filter'])) {
                    switch ($details['type']) {
                        case 'number':
                            $details['filter'] = (float) $details['filter'];
                            break;

                        case 'boolean':
                            $details['filter'] = (bool) $details['filter'];
                            break;
                    }
                }

                $parameters[$parameter] = $details['filter'];
            }
        }

        // Get the last of the filters
        if ($groupExpr->count()) {
            $groups[] = $groupExpr;
        }

        if (count($groups) === 1) {
            // Only one andX expression
            $expr = $groups[0];
        } else {
            // Sets of expressions grouped by OR
            $orX = $q->expr()->orX();
            $orX->addMultiple($groups);

            // Wrap in a andX for other functions to append
            $expr = $q->expr()->andX($orX);
        }

        return $expr;
    }

    /**
     * @param \Doctrine\DBAL\Query\QueryBuilder|\Doctrine\ORM\QueryBuilder $q
     * @param                                                              $filter
     *
     * @return array
     */
    protected function addCatchAllWhereClause(&$q, $filter)
    {
        return $this->addStandardCatchAllWhereClause(
            $q,
            $filter,
            [
                'l.name',
                'l.alias',
            ]
        );
    }

    /**
     * @param \Doctrine\DBAL\Query\QueryBuilder|\Doctrine\ORM\QueryBuilder $q
     * @param                                                              $filter
     *
     * @return array
     */
    protected function addSearchCommandWhereClause(&$q, $filter)
    {
        $command         = $filter->command;
        $unique          = $this->generateRandomParameterName();
        $returnParameter = true; //returning a parameter that is not used will lead to a Doctrine error
        $expr            = false;

        switch ($command) {
            case $this->translator->trans('mautic.core.searchcommand.ismine'):
                $expr            = $q->expr()->eq('l.createdBy', $this->currentUser->getId());
                $returnParameter = false;
                break;
            case $this->translator->trans('mautic.lead.list.searchcommand.isglobal'):
                $expr            = $q->expr()->eq('l.isGlobal', ":$unique");
                $forceParameters = [$unique => true];
                break;
            case $this->translator->trans('mautic.core.searchcommand.ispublished'):
                $expr            = $q->expr()->eq('l.isPublished', ":$unique");
                $forceParameters = [$unique => true];
                break;
            case $this->translator->trans('mautic.core.searchcommand.isunpublished'):
                $expr            = $q->expr()->eq('l.isPublished', ":$unique");
                $forceParameters = [$unique => false];
                break;
            case $this->translator->trans('mautic.core.searchcommand.name'):
                $expr = $q->expr()->like('l.name', ':'.$unique);
                break;
        }

        $parameters = [];
        if (!empty($forceParameters)) {
            $parameters = $forceParameters;
        } elseif ($returnParameter) {
            $string     = ($filter->strict) ? $filter->string : "%{$filter->string}%";
            $parameters = ["$unique" => $string];
        }

        return [
            $expr,
            $parameters,
        ];
    }

    /**
     * @return array
     */
    public function getSearchCommands()
    {
        return [
            'mautic.lead.list.searchcommand.isglobal',
            'mautic.core.searchcommand.ismine',
            'mautic.core.searchcommand.ispublished',
            'mautic.core.searchcommand.isinactive',
            'mautic.core.searchcommand.name',
        ];
    }

    /**
     * @return array
     */
    public function getRelativeDateStrings()
    {
        $keys = self::getRelativeDateTranslationKeys();

        $strings = [];
        foreach ($keys as $key) {
            $strings[$key] = $this->translator->trans($key);
        }

        return $strings;
    }

    /**
     * @return array
     */
    public static function getRelativeDateTranslationKeys()
    {
        return [
            'mautic.lead.list.month_last',
            'mautic.lead.list.month_next',
            'mautic.lead.list.month_this',
            'mautic.lead.list.today',
            'mautic.lead.list.tomorrow',
            'mautic.lead.list.yesterday',
            'mautic.lead.list.week_last',
            'mautic.lead.list.week_next',
            'mautic.lead.list.week_this',
            'mautic.lead.list.year_last',
            'mautic.lead.list.year_next',
            'mautic.lead.list.year_this',
        ];
    }

    /**
     * @return string
     */
    protected function getDefaultOrder()
    {
        return [
            ['l.name', 'ASC'],
        ];
    }

    /**
     * @return string
     */
    public function getTableAlias()
    {
        return 'l';
    }

    /**
     * If there is a negate comparison such as not equal, empty, isNotLike or isNotIn then contacts without companies should
     * be included but the way the relationship is handled needs to be different to optimize best for a posit vs negate.
     *
     * @param $q
     * @param $exprCompany
     */
    private function applyCompanyFieldFilters($q, $exprCompany)
    {
        $joinType = ($this->listFiltersInnerJoinCompany) ? 'join' : 'leftJoin';
        // Join company tables for query optimization
        $q->$joinType('l', MAUTIC_TABLE_PREFIX.'companies_leads', 'cl', 'l.id = cl.lead_id')
            ->$joinType(
                'cl',
                MAUTIC_TABLE_PREFIX.'companies',
                'comp',
                'cl.company_id = comp.id'
            )
            ->andWhere($exprCompany);

        // Return only unique contacts
        $q->groupBy('l.id');
    }
}
