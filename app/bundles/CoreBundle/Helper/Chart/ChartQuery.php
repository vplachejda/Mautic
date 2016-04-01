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
        $this->unit = $unit;
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
                if ($column == 'leadlist_id') {
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
            // Apply the start date/time if set
            if ($this->dateFrom) {
                $dateFrom = clone $this->dateFrom;
                $dateFrom->setTimeZone(new \DateTimeZone('UTC'));
                $query->andWhere('t.' . $dateColumn . ' >= :dateFrom');
                $query->setParameter('dateFrom', $dateFrom->format('Y-m-d H:i:s'));
            }

            // Apply the end date/time if set
            if ($this->dateTo) {
                $dateTo = clone $this->dateTo;
                $dateTo->setTimeZone(new \DateTimeZone('UTC'));
                $query->andWhere('t.' . $dateColumn . ' <= :dateTo');
                $query->setParameter('dateTo', $dateTo->format('Y-m-d H:i:s'));
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
     * Fetch data for a time related dataset
     *
     * @param  string     $table without prefix
     * @param  string     $column name. The column must be type of datetime
     * @param  array      $filters will be added to where claues
     * @param  string     $order will be added to where claues
     *
     * @return array
     */
    public function fetchTimeData($table, $column, $filters = array(), $order = 'DESC') {
        // Convert time unitst to the right form for current database platform
        $dbUnit = $this->translateTimeUnit($this->unit);
        $query = $this->connection->createQueryBuilder();
        $limit = $this->countAmountFromDateRange($this->unit);
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
            throw new UnexpectedValueException(__CLASS__ . '::' . __METHOD__ . ' supports only MySql a PosgreSQL database platforms.');
        }

        $query->from(MAUTIC_TABLE_PREFIX . $table, 't')
            ->orderBy($dateConstruct, $order);

        // Count only with dates which are not empty
        $query->andWhere('t.' . $column . ' IS NOT NULL');

        $this->applyFilters($query, $filters);
        $this->applyDateFilters($query, $column);

        $query->setMaxResults($limit);

        // Fetch the data
        $rawData = $query->execute()->fetchAll();
        $data    = array();
        $oneUnit = $this->getUnitObject($this->unit);
        $date    = clone $this->dateTo;
        $date->format($this->sqlFormats[$this->unit]);

        // Convert data from DB to the chart.js format
        for ($i = 0; $i < $limit; $i++) {

            $nextDate = clone $date;

            if ($order == 'DESC') {
                $nextDate->sub($oneUnit);
            } else {
                $nextDate->add($oneUnit);
            }

            foreach ($rawData as $key => $item) {
                $itemDate = (new \DateTime($item['date'], new \DateTimeZone('UTC')));

                // The right value is between the time unit and time unit +1 for ASC ordering
                if ($order == 'ASC' && $itemDate >= $date && $itemDate < $nextDate) {
                    $data[$i] = $item['count'];
                    unset($rawData[$key]);
                    continue;
                }

                // The right value is between the time unit and time unit -1 for DESC ordering
                if ($order == 'DESC' && $itemDate <= $date && $itemDate > $nextDate) {
                    $data[$i] = $item['count'];
                    unset($rawData[$key]);
                    continue;
                }
            }

            // Chart.js requires the 0 for empty data, but the array slot has to exist
            if (!isset($data[$i])) {
                $data[$i] = 0;
            }

            if ($order == 'DESC') {
                $date->sub($oneUnit);
            } else {
                $date->add($oneUnit);
            }
        }

        return array_reverse($data);
    }

    /**
     * Count occurences of a value in a column
     *
     * @param  string     $table without prefix
     * @param  string     $uniqueColumn name
     * @param  string     $dateColumn name
     * @param  array      $filters will be added to where claues
     * @param  array      $options for special behavior
     */
    public function count($table, $uniqueColumn, $dateColumn = null, $filters = array(), $options = array()) {
        $query = $this->connection->createQueryBuilder();

        $query->select('COUNT(t.' . $uniqueColumn . ') AS count')
            ->from(MAUTIC_TABLE_PREFIX . $table, 't');

        $this->applyFilters($query, $filters);
        $this->applyDateFilters($query, $dateColumn);

        // Count only unique values
        if (!empty($options['getUnique'])) {
            // Modify the previous query
            $query->select('t.' . $uniqueColumn);
            $query->having('COUNT(*) = 1')
                ->groupBy('t.' . $uniqueColumn);

            // Create a new query with subquery of the previous query
            $uniqueQuery = $this->connection->createQueryBuilder();
            $uniqueQuery->select('COUNT(t.' . $uniqueColumn . ') AS count')
                ->from('(' . $query->getSql() . ')', 't');

            // Apply params from the previous query to the new query
            $uniqueQuery->setParameters($query->getParameters());

            // Replace the new query with previous query
            $query = $uniqueQuery;
        }

        // Fetch the count
        $data = $query->execute()->fetch();

        return (int) $data['count'];
    }

    /**
     * Count how many rows is between a range of date diff in seconds
     *
     * @param  string     $table without prefix
     * @param  string     $dateColumn1
     * @param  string     $dateColumn2
     * @param  integer    $startSecond
     * @param  integer    $endSecond
     * @param  array      $filters will be added to where claues
     */
    public function countDateDiff($table, $dateColumn1, $dateColumn2, $startSecond = 0, $endSecond = 60, $filters = array()) {
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

        $data = $query->execute()->fetch();

        return (int) $data['count'];
    }

    /**
     * Sum values in a column
     *
     * @param  string     $table without prefix
     * @param  string     $column name
     * @param  array      $filters will be added to where claues
     * @param  array      $options for special behavior
     */
    public function sum($table, $column, $filters = array(), $options = array()) {
        $query = $this->connection->createQueryBuilder();

        $query->select('sum(t.' . $column . ') AS result')
            ->from(MAUTIC_TABLE_PREFIX . $table, 't');

        $this->applyFilters($query, $filters);

        $data = $query->execute()->fetch();

        return (int) $data['result'];
    }
}
