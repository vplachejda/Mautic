<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Helper\Chart;

use Mautic\CoreBundle\Helper\Chart\AbstactChart;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Class ChartQuery
 *
 * Methods to get the chart data as native queries to get better performance and work with date/time native SQL queries.
 */
class ChartQuery extends AbstractChart
{
    /**
     * Doctrine's Connetion object
     *
     * @var  Connection $connection
     */
    protected $connection;

    /**
     * Match date/time unit to a SQL datetime format
     * {@link php.net/manual/en/function.date.php#refsect1-function.date-parameters}
     *
     * @var array
     */
    protected $sqlFormats = array(
        's' => 'Y-m-d H:i:s',
        'i' => 'Y-m-d H:i:00',
        'H' => 'Y-m-d H:00:00',
        'd' => 'Y-m-d 00:00:00', 'D' => 'Y-m-d 00:00:00', // ('D' is BC. Can be removed when all charts use this class)
        'W' => 'Y-m-d 00:00:00',
        'm' => 'Y-m-01 00:00:00', 'M' => 'Y-m-00 00:00:00', // ('M' is BC. Can be removed when all charts use this class)
        'Y' => 'Y-01-01 00:00:00',
    );

    /**
     * Match date/time unit to a PostgreSQL datetime format
     * {@link php.net/manual/en/function.date.php#refsect1-function.date-parameters}
     * {@link www.postgresql.org/docs/9.1/static/functions-datetime.html}
     *
     * @var array
     */
    protected $postgresTimeUnits = array(
        's' => 'second',
        'i' => 'minute',
        'H' => 'hour',
        'd' => 'day', 'D' => 'day', // ('D' is BC. Can be removed when all charts use this class)
        'W' => 'week',
        'm' => 'month', 'M' => 'month', // ('M' is BC. Can be removed when all charts use this class)
        'Y' => 'year'
    );

    /**
     * Match date/time unit to a MySql datetime format
     * {@link php.net/manual/en/function.date.php#refsect1-function.date-parameters}
     * {@link dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_date-format}
     *
     * @var array
     */
    protected $mysqlTimeUnits = array(
        's' => '%Y-%m-%d %H:%i:%s',
        'i' => '%Y-%m-%d %H:%i',
        'H' => '%Y-%m-%d %H:00',
        'd' => '%Y-%m-%d', 'D' => '%Y-%m-%d', // ('D' is BC. Can be removed when all charts use this class)
        'W' => '%Y %U',
        'm' => '%Y-%m', 'M' => '%Y-%m', // ('M' is BC. Can be removed when all charts use this class)
        'Y' => '%Y'
    );

    /**
     * Construct a new ChartQuery object
     *
     * @param  Connection $connection
     * @param  DateTime   $dateFrom
     * @param  DateTime   $dateTo
     * @param  string     $unit
     */
    public function __construct(Connection $connection, \DateTime $dateFrom, \DateTime $dateTo, $unit = null)
    {
        $this->setDateRange($dateFrom, $dateTo);
        $this->connection = $connection;
        $this->unit       = $unit;
    }

    /**
     * Check if the DB connection is to PostgreSQL database
     *
     * @return boolean
     */
    public function isPostgres()
    {
        $platform = $this->connection->getDatabasePlatform();
        return $platform instanceof \doctrine\DBAL\Platforms\PostgreSqlPlatform;
    }

    /**
     * Check if the DB connection is to MySql database
     *
     * @return boolean
     */
    public function isMysql()
    {
        $platform = $this->connection->getDatabasePlatform();
        return $platform instanceof \doctrine\DBAL\Platforms\MySqlPlatform;
    }

    /**
     * Apply where filters to the query
     *
     * @param  QueryBuilder $query
     * @param  array        $filters
     */
    public function applyFilters(&$query, $filters)
    {
        if ($filters && is_array($filters)) {
            foreach ($filters as $column => $value) {
                $valId = $column . '_val';

                // Special case: Lead list filter
                if ($column === 'leadlist_id') {
                    $query->join('t', MAUTIC_TABLE_PREFIX.'lead_lists_leads', 'lll', 'lll.lead_id = ' . $value['list_column_name']);
                    $query->andWhere('lll.leadlist_id = :' . $valId);
                    $query->setParameter($valId, $value['value']);
                } elseif (isset($value['expression']) && method_exists($query->expr(), $value['expression'])) {
                    $query->andWhere($query->expr()->{$value['expression']}($column));
                    if (isset($value['value'])) {
                        $query->setParameter($valId, $value['value']);
                    }
                } else {
                    if (is_array($value)) {
                        $query->andWhere('t.' . $column . ' IN(:' . $valId . ')');
                        $query->setParameter($valId, implode(',', $value));
                    } else {
                        $query->andWhere('t.' . $column . ' = :' . $valId);
                        $query->setParameter($valId, $value);
                    }
                }
            }
        }
    }

    /**
     * Apply date filters to the query
     *
     * @param  QueryBuilder
     * @param  string
     * @param  DateTime
     * @param  DateTime
     */
    public function applyDateFilters(&$query, $dateColumn)
    {
        if ($dateColumn) {
            $isTime = (in_array($this->unit, array('H', 'i', 's')));

            if ($this->dateFrom && $this->dateTo) {
                // Between is faster so if we know both dates...
                $dateFrom = clone $this->dateFrom;
                $dateTo = clone $this->dateTo;
                if ($isTime) $dateFrom->setTimeZone(new \DateTimeZone('UTC'));
                if ($isTime) $dateTo->setTimeZone(new \DateTimeZone('UTC'));
                $query->andWhere('t.' . $dateColumn . ' BETWEEN :dateFrom AND :dateTo');
                $query->setParameter('dateFrom', $dateFrom->format('Y-m-d H:i:s'));
                $query->setParameter('dateTo', $dateTo->format('Y-m-d H:i:s'));
            } else {
                // Apply the start date/time if set
                if ($this->dateFrom) {
                    $dateFrom = clone $this->dateFrom;
                    if ($isTime) $dateFrom->setTimeZone(new \DateTimeZone('UTC'));
                    $query->andWhere('t.' . $dateColumn . ' >= :dateFrom');
                    $query->setParameter('dateFrom', $dateFrom->format('Y-m-d H:i:s'));
                }

                // Apply the end date/time if set
                if ($this->dateTo) {
                    $dateTo = clone $this->dateTo;
                    if ($isTime) $dateTo->setTimeZone(new \DateTimeZone('UTC'));
                    $query->andWhere('t.' . $dateColumn . ' <= :dateTo');
                    $query->setParameter('dateTo', $dateTo->format('Y-m-d H:i:s'));
                }
            }
            
        }
    }

    /**
     * Get the right unit for current database platform
     *
     * @param  string     $unit {@link php.net/manual/en/function.date.php#refsect1-function.date-parameters}
     *
     * @return string
     */
    public function translateTimeUnit($unit)
    {
        if ($this->isPostgres()) {
            if (!isset($this->postgresTimeUnits[$unit])) {
                throw new \UnexpectedValueException('Date/Time unit "' . $unit . '" is not available for Postgres.');
            }

            return $this->postgresTimeUnits[$unit];
        } elseif ($this->isMySql()) {
            if (!isset($this->mysqlTimeUnits[$unit])) {
                throw new \UnexpectedValueException('Date/Time unit "' . $unit . '" is not available for MySql.');
            }

            return $this->mysqlTimeUnits[$unit];
        }

        return $unit;
    }

    /**
     * Prepare database query for fetching the line time chart data
     *
     * @param  string     $table without prefix
     * @param  string     $column name. The column must be type of datetime
     * @param  array      $filters will be added to where claues
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function prepareTimeDataQuery($table, $column, $filters = array())
    {
        // Convert time unitst to the right form for current database platform
        $dbUnit  = $this->translateTimeUnit($this->unit);
        $query   = $this->connection->createQueryBuilder();
        $limit   = $this->countAmountFromDateRange($this->unit);
        $groupBy = '';

        // Postgres and MySql are handeling date/time SQL funciton differently
        if ($this->isPostgres()) {
            if (isset($filters['groupBy'])) {
                $count = 'COUNT(DISTINCT(t.' . $filters['groupBy'] . '))';
                unset($filters['groupBy']);
            } else {
                $count = 'COUNT(*)';
            }
            $dateConstruct = 'DATE_TRUNC(\'' . $dbUnit . '\', t.' . $column . ')';
            $query->select($dateConstruct . ' AS date, ' . $count . ' AS count')
                ->groupBy($dateConstruct);
        } elseif ($this->isMysql()) {
            if (isset($filters['groupBy'])) {
                $groupBy = ', t.' . $filters['groupBy'];
                unset($filters['groupBy']);
            }
            $dateConstruct = 'DATE_FORMAT(t.' . $column . ', \'' . $dbUnit . '\')';
            $query->select($dateConstruct . ' AS date, COUNT(*) AS count')
                ->groupBy($dateConstruct . $groupBy);
        } else {
            throw new \UnexpectedValueException(__CLASS__ . '::' . __METHOD__ . ' supports only MySql a PosgreSQL database platforms.');
        }

        $query->from(MAUTIC_TABLE_PREFIX . $table, 't')
            ->orderBy($dateConstruct, 'ASC');

        $this->applyFilters($query, $filters);
        $this->applyDateFilters($query, $column);

        $query->setMaxResults($limit);

        return $query;
    }

    /**
     * Fetch data for a time related dataset
     *
     * @param  string     $table without prefix
     * @param  string     $column name. The column must be type of datetime
     * @param  array      $filters will be added to where claues
     *
     * @return array
     */
    public function fetchTimeData($table, $column, $filters = array())
    {
        $query = $this->prepareTimeDataQuery($table, $column, $filters);
        return $this->loadAndBuildTimeData($query);
    }

    /**
     * Loads data from prepared query and builds the chart data
     *
     * @param  QueryBuilder $query
     *
     * @return array
     */
    public function loadAndBuildTimeData($query)
    {
        $rawData = $query->execute()->fetchAll();
        return $this->completeTimeData($rawData);
    }

    /**
     * Go through the raw data and add the missing times
     *
     * @param  string     $table without prefix
     * @param  string     $column name. The column must be type of datetime
     * @param  array      $filters will be added to where claues
     *
     * @return array
     */
    public function completeTimeData($rawData)
    {
        $data         = array();
        $oneUnit      = $this->getUnitInterval();
        $limit        = $this->countAmountFromDateRange($this->unit);
        $previousDate = clone $this->dateFrom;
        $utcTz        = new \DateTimeZone("UTC");

        if ($this->unit === 'Y') {
            $previousDate->modify('first day of January');
        } elseif ($this->unit == 'm') {
            $previousDate->modify('first day of this month');
        } elseif ($this->unit === 'W') {
            $previousDate->modify('Monday this week');
        }

        // Convert data from DB to the chart.js format
        for ($i = 0; $i < $limit; $i++) {

            $nextDate = clone $previousDate;

            if ($this->unit === 'm') {
                $nextDate->modify('first day of next month');
            } elseif ($this->unit === 'W') {
                $nextDate->modify('Monday next week');
            }  else {
                $nextDate->add($oneUnit);
            }

            foreach ($rawData as $key => &$item) {
                if (!isset($item['date_comparison'])) {
                    /**
                     * PHP DateTime cannot parse the Y W (ex 2016 09)
                     * format, so we transform it into d-M-Y.
                     */
                    if ($this->unit === 'W' && $this->isMysql()) {
                        list($year, $week) = explode(' ', $item['date']);
                        $newDate = new \DateTime();
                        $newDate->setISODate($year, $week);
                        $item['date'] = $newDate->format('d-M-Y');
                        unset($newDate);
                    }

                    // Data from the database will always in UTC
                    $itemDate = new \DateTime($item['date'], $utcTz);

                    if (!in_array($this->unit, array('H', 'i', 's'))) {
                        // Hours do not matter so let's reset to 00:00:00 for date comparison
                        $itemDate->setTime(0, 0, 0);
                    } else {
                        // Convert to the timezone used for comparison
                        $itemDate->setTimezone($this->timezone);
                    }

                    $item['date_comparison'] = $itemDate;
                } else {
                    $itemDate = $item['date_comparison'];
                }

                // Place the right suma is between the time unit and time unit +1
                if (isset($item['count']) && $itemDate >= $previousDate && $itemDate < $nextDate) {
                    $data[$i] = $item['count'];
                    unset($rawData[$key]);
                    continue;
                }

                // Add the right item is between the time unit and time unit +1
                if (isset($item['data']) && $itemDate >= $previousDate && $itemDate < $nextDate) {
                    if (isset($data[$i])) {
                        $data[$i] += $item['data'];
                    } else {
                        $data[$i] = $item['data'];
                    }
                    unset($rawData[$key]);
                    continue;
                }
            }

            // Chart.js requires the 0 for empty data, but the array slot has to exist
            if (!isset($data[$i])) {
                $data[$i] = 0;
            }

            $previousDate = $nextDate;
        }

        return $data;
    }

    /**
     * Count occurences of a value in a column
     *
     * @param  string     $table without prefix
     * @param  string     $uniqueColumn name
     * @param  string     $dateColumn name
     * @param  array      $filters will be added to where claues
     * @param  array      $options for special behavior
     *
     * @return QueryBuilder $query
     */
    public function getCountQuery($table, $uniqueColumn, $dateColumn = null, $filters = array(), $options = array())
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('COUNT(t.' . $uniqueColumn . ') AS count')
            ->from(MAUTIC_TABLE_PREFIX . $table, 't');

        $this->applyFilters($query, $filters);
        $this->applyDateFilters($query, $dateColumn);

        // Count only unique values
        if (!empty($options['getUnique'])) {
            $selectAlso = '';
            if (isset($options['selectAlso'])) {
                $selectAlso = ', ' . implode(', ', $options['selectAlso']);
            }
            // Modify the previous query
            $query->select('t.' . $uniqueColumn . $selectAlso);
            $query->having('COUNT(*) = 1')
                ->groupBy('t.' . $uniqueColumn . $selectAlso);

            // Create a new query with subquery of the previous query
            $uniqueQuery = $this->connection->createQueryBuilder();
            $uniqueQuery->select('COUNT(t.' . $uniqueColumn . ') AS count')
                ->from('(' . $query->getSql() . ')', 't');

            // Apply params from the previous query to the new query
            $uniqueQuery->setParameters($query->getParameters());

            // Replace the new query with previous query
            $query = $uniqueQuery;
        }

        return $query;
    }

    /**
     * Count occurences of a value in a column
     *
     * @param  string     $table without prefix
     * @param  string     $uniqueColumn name
     * @param  string     $dateColumn name
     * @param  array      $filters will be added to where claues
     * @param  array      $options for special behavior
     *
     * @return integer
     */
    public function count($table, $uniqueColumn, $dateColumn = null, $filters = array(), $options = array())
    {
        $query = $this->getCountQuery($table, $uniqueColumn, $dateColumn, $filters);
        return $this->fetchCount($query);
    }

    /**
     * Fetch the count integet from a query
     *
     * @param  QueryBuilder $query
     *
     * @return integer
     */
    public function fetchCount(QueryBuilder $query)
    {
        $data = $query->execute()->fetch();
        return (int) $data['count'];
    }

    /**
     * Get the query to count how many rows is between a range of date diff in seconds
     *
     * @param  string     $table without prefix
     * @param  string     $dateColumn1
     * @param  string     $dateColumn2
     * @param  integer    $startSecond
     * @param  integer    $endSecond
     * @param  array      $filters will be added to where claues
     *
     * @return QueryBuilder $query
     */
    public function getCountDateDiffQuery($table, $dateColumn1, $dateColumn2, $startSecond = 0, $endSecond = 60, $filters = array())
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('COUNT(t.' . $dateColumn1 . ') AS count')
            ->from(MAUTIC_TABLE_PREFIX . $table, 't');

        if ($this->isPostgres()) {
            $query->where('extract(epoch from(t.' . $dateColumn2 . '::timestamp - t.' . $dateColumn1 . '::timestamp)) >= :startSecond');
            $query->andWhere('extract(epoch from(t.' . $dateColumn2 . '::timestamp - t.' . $dateColumn1 . '::timestamp)) < :endSecond');
        }

        if ($this->isMysql()) {
            $query->where('TIMESTAMPDIFF(SECOND, t.' . $dateColumn2 . ', t.' . $dateColumn1 . ') >= :startSecond');
            $query->andWhere('TIMESTAMPDIFF(SECOND, t.' . $dateColumn2 . ', t.' . $dateColumn1 . ') < :endSecond');
        }

        $query->setParameter('startSecond', $startSecond);
        $query->setParameter('endSecond', $endSecond);

        $this->applyFilters($query, $filters);
        $this->applyDateFilters($query, $dateColumn1);

        return $query;
    }

    /**
     * Count how many rows is between a range of date diff in seconds
     *
     * @param  string     $query
     *
     * @return integer
     */
    public function fetchCountDateDiff($query)
    {
        $data = $query->execute()->fetch();
        return (int) $data['count'];
    }
}
