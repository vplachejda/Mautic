<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributorcomp. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Entity;

use Doctrine\ORM\Query;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * Class CompanyRepository
 */
class CompanyRepository extends CommonRepository
{

    /**
     * {@inheritdoc}
     *
     * @param integer $id
     *
     * @return mixed|null
     */
    public function getEntity($id = 0)
    {
        try {
            /** @var Lead $entity */
            $entity = $this
                ->createQueryBuilder('comp')
                ->select('comp, u')
                ->leftJoin('comp.owner', 'u')
                ->where('comp.id = :companyId')
                ->setParameter('companyId', $id)
                ->getQuery()
                ->getSingleResult();
        } catch (\Exception $e) {
            $entity = null;
        }

        if ($entity != null) {
            $fieldValues = $this->getFieldValues($id);
            $entity->setFields($fieldValues);
        }

        return $entity;
    }
    /**
     * Get a list of leads
     *
     * @param array $args
     *
     * @return array
     */
    public function getEntities($args = array())
    {
        //Get the list of custom fields
        $fq = $this->_em->getConnection()->createQueryBuilder();
        $fq->select('f.id, f.label, f.alias, f.type, f.field_group as "group"')
            ->from(MAUTIC_TABLE_PREFIX . 'lead_fields', 'f')
            ->where('f.is_published = :published')
            ->andWhere($fq->expr()->eq('object',':company'))
            ->setParameter('company', 'company')
            ->setParameter('published', true, 'boolean');
        $results = $fq->execute()->fetchAll();

        $fields = array();
        foreach ($results as $r) {
            $fields[$r['alias']] = $r;
        }

        unset($results);

        //Fix arguments if necessary
        $args = $this->convertOrmProperties('Mautic\\LeadBundle\\Entity\\Company', $args);

        //DBAL
        $dq = $this->getEntityManager()->getConnection()->createQueryBuilder();

        $dq->select('COUNT(comp.id) as count')
            ->from(MAUTIC_TABLE_PREFIX.'companies', 'comp')
            ->leftJoin('comp', MAUTIC_TABLE_PREFIX.'users', 'u', 'u.id = comp.owner_id');

        // Filter by an entity query
        if (isset($args['entity_query'])) {
            $dq->andWhere(
                sprintf('EXISTS (%s)', $args['entity_query']->getSQL())
            );

            if (isset($args['entity_parameters'])) {
                foreach ($args['entity_parameters'] as $name => $value) {
                    $dq->setParameter($name, $value);
                }
            }
        }

        $this->buildWhereClause($dq, $args);

        //get a total count
        $result = $dq->execute()->fetchAll();
        $total  = $result[0]['count'];

        //now get the actual paginated results
        $this->buildOrderByClause($dq, $args);
        $this->buildLimiterClauses($dq, $args);

        $dq->resetQueryPart('select')
            ->select('comp.*');
        $results = $dq->execute()->fetchAll();

        //loop over results to put fields in something that can be assigned to the entities
        $fieldValues = array();
        $groups      = array('core', 'other');

        foreach ($results as $result) {
            $companyId = $result['id'];
            //unset all the columns that are not fields
            $this->removeNonFieldColumns($result);

            foreach ($result as $k => $r) {
                if (isset($fields[$k])) {
                    $fieldValues[$companyId][$fields[$k]['group']][$fields[$k]['alias']] = $fields[$k];
                    $fieldValues[$companyId][$fields[$k]['group']][$fields[$k]['alias']]['value'] = $r;
                }
            }

            //make sure each group key is present
            foreach ($groups as $g) {
                if (!isset($fieldValues[$companyId][$g])) {
                    $fieldValues[$companyId][$g] = array();
                }
            }
        }

        unset($results, $fields);

        //get an array of IDs for ORM query
        $ids = array_keys($fieldValues);

        if (count($ids)) {
            //ORM

            //build the order by id since the order was applied above
            //unfortunately, doctrine does not have a way to natively support this and can't use MySQL's FIELD function
            //since we have to be cross-platform; it's way ugly

            //We should probably totally ditch orm for leads
            $order = '(CASE';
            foreach ($ids as $count => $id) {
                $order .= ' WHEN comp.id = ' . $id . ' THEN ' . $count;
                $count++;
            }
            $order .= ' ELSE ' . $count . ' END) AS HIDDEN ORD';

            //ORM - generates lead entities
            $q = $this->_em->createQueryBuilder();
            $q->select('comp, u,' . $order)
                ->from('MauticLeadBundle:Company', 'comp', 'comp.id')
                ->leftJoin('comp.owner', 'u');

            //only pull the leads as filtered via DBAL
            $q->where(
                $q->expr()->in('comp.id', ':companyIds')
            )->setParameter('companyIds', $ids);

            $q->orderBy('ORD', 'ASC');

            $results = $q->getQuery()
                ->useQueryCache(false)
                ->useResultCache(false)
                ->getResult();

            //assign fields
            foreach ($results as $r) {
                $companyId = $r->getId();
                $r->setFields($fieldValues[$companyId]);

            }
        } else {
            $results = array();
        }

        return (!empty($args['withTotalCount'])) ?
            array(
                'count' => $total,
                'results' => $results
            ) : $results;
    }
    /**
     * {@inheritdoc}
     *
     * @param $entity
     * @param $flush
     */
    public function saveEntity($entity, $flush = true)
    {
        $this->_em->persist($entity);

        if ($flush)
            $this->_em->flush($entity);

        $fields = $entity->getUpdatedFields();
        if (!empty($fields)) {
            $this->_em->getConnection()->update(MAUTIC_TABLE_PREFIX . 'companies', $fields, array('id' => $entity->getId()));
        }
    }


    /**
     * Persist an array of entities
     *
     * @param array $entities
     */
    public function saveEntities($entities)
    {
        foreach ($entities as $k => $entity) {
            // Leads cannot be batched due to requiring the ID to update the fields
            $this->saveEntity($entity);
        }
    }
    /**
     * Get a list of fields and values
     *
     * @param           $id
     * @param bool|true $byGroup
     *
     * @return array
     */
    public function getFieldValues($id, $byGroup = true)
    {
        //Get the list of custom fields
        $fq = $this->_em->getConnection()->createQueryBuilder();
        $fq->select('f.id, f.label, f.alias, f.type, f.field_group as "group", f.field_order, f.object')
            ->from(MAUTIC_TABLE_PREFIX . 'lead_fields', 'f')
            ->where('f.is_published = :published')
            ->andWhere($fq->expr()->eq('f.object',':object'))
            ->setParameter('object','company')
            ->orderBy('f.field_order', 'asc')
            ->setParameter('published', true, 'boolean');
        $results = $fq->execute()->fetchAll();

        $fields = array();
        foreach ($results as $r) {
            $fields[$r['alias']] = $r;
        }

        //use DBAL to get entity fields
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('*')
            ->from(MAUTIC_TABLE_PREFIX . 'companies', 'comp')
            ->where('comp.id = :companyId')
            ->setParameter('companyId', $id);
        $companyValues = $q->execute()->fetch();
        $this->removeNonFieldColumns($companyValues);

        // Reorder leadValues based on field order
        $companyValues = array_merge(array_flip(array_keys($fields)), $companyValues);

        $fieldValues = array();

        //loop over results to put fields in something that can be assigned to the entities
        foreach ($companyValues as $k => $r) {
            if (isset($fields[$k])) {
                if ($byGroup) {
                    $fieldValues[$fields[$k]['group']][$fields[$k]['alias']]          = $fields[$k];
                    $fieldValues[$fields[$k]['group']][$fields[$k]['alias']]['value'] = $r;
                } else {
                    $fieldValues[$fields[$k]['alias']]          = $fields[$k];
                    $fieldValues[$fields[$k]['alias']]['value'] = $r;
                }
            }
        }

        if ($byGroup) {
            //make sure each group key is present
            $groups = array('core', 'social', 'personal', 'professional');
            foreach ($groups as $g) {
                if (!isset($fieldValues[$g])) {
                    $fieldValues[$g] = array();
                }
            }
        }

        return $fieldValues;
    }
    /**
     * Get companies by lead
     *
     * @param      $leadId
     *
     * @return array
     */
    public function getCompaniesByLeadId($leadId)
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();

        $q->select('comp.id, comp.companyname')
            ->from(MAUTIC_TABLE_PREFIX.'companies', 'comp')
            ->leftJoin('comp',MAUTIC_TABLE_PREFIX.'companies_leads', 'cl', 'cl.company_id = comp.id')
            ->where('cl.lead_id = :leadId')
            ->setParameter('leadId', $leadId)
            ->orderBy('comp.id', 'DESC');
        $results = $q->execute()->fetchAll();

        return $results;
    }
    /**
     * Function to remove non custom field columns from an arrayed lead row
     *
     * @param array $r
     */
    protected function removeNonFieldColumns(&$r)
    {
        $baseCols = $this->getBaseColumns('Mautic\\LeadBundle\\Entity\\Company', true);
        foreach ($baseCols as $c) {
            unset($r[$c]);
        }
        unset($r['owner_id']);
    }

    /**
     * {@inheritdoc}
     */
    public function getTableAlias()
    {
        return 'comp';
    }


    /**
     * {@inheritdoc}
     */
    protected function addCatchAllWhereClause(&$q, $filter)
    {
        return $this->addStandardCatchAllWhereClause($q, $filter, array(
            'comp.companyname',
            'comp.companydescription'
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function addSearchCommandWhereClause(&$q, $filter)
    {
        return $this->addStandardSearchCommandWhereClause($q, $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchCommands()
    {
        return $this->getStandardSearchCommands();
    }

    /**
     * Get a list of lists
     *
     * @param bool   $user
     * @param string $alias
     * @param string $id
     *
     * @return array
     */
    public function getCompanies($user = false, $id = '')
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        static $companies = array();

        if (is_object($user)) {
            $user = $user->getId();
        }

        $key = (int) $user.$id;
        if (isset($companies[$key])) {
            return $companies[$key];
        }

        $q->select('comp.*')
            ->from(MAUTIC_TABLE_PREFIX .'companies', 'comp');

        if ($user) {
            $q->orWhere('comp.created_by = :user');
            $q->setParameter('user', $user);
        }

        if (!empty($id)) {
            $q->where(
                $q->expr()->eq('comp.id', $id)
            );
        }

        $q->orderBy('comp.id', 'DESC');

        $results = $q->execute()->fetchAll();

        $companies[$key] = $results;

        return $results;
    }

    /**
    * Get a count of leads that belong to the company
    *
    * @param $companyIds
    *
    * @return array
    */
    public function getLeadCount($companyIds)
    {
        $q = $this->_em->getConnection()->createQueryBuilder();

        $q->select('count(cl.lead_id) as thecount, cl.company_id')
            ->from(MAUTIC_TABLE_PREFIX.'companies_leads', 'cl');

        $returnArray = (is_array($companyIds));

        if (!$returnArray) {
            $companyIds = array($companyIds);
        }

        $q->where(
            $q->expr()->in('cl.company_id', $companyIds),
            $q->expr()->eq('cl.manually_removed', ':false')
        )
            ->setParameter('false', false, 'boolean')
            ->groupBy('cl.company_id');

        $result = $q->execute()->fetchAll();

        $return = array();
        foreach ($result as $r) {
                $return[$r['company_id']] = $r['thecount'];
            }

        // Ensure lists without leads have a value
        foreach ($companyIds as $l) {
                if (!isset($return[$l])) {
                        $return[$l] = 0;
                    }
        }

        return ($returnArray) ? $return : $return[$companyIds[0]];
    }
}
