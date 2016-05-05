<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PageBundle\Entity;

use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * Class PageRepository
 */
class PageRepository extends CommonRepository
{

    /**
     * {@inheritdoc}
     */
    public function getEntities($args = array())
    {
        $q = $this
            ->createQueryBuilder('p')
            ->select('p')
            ->leftJoin('p.category', 'c');

        $args['qb'] = $q;

        return parent::getEntities($args);
    }

    /**
     * @param string $alias
     * @param Page   $entity
     *
     * @return mixed
     */
    public function checkUniqueAlias($alias, $entity = null)
    {
        $q = $this->createQueryBuilder('e')
            ->select('count(e.id) as alias_count')
            ->where('e.alias = :alias');
        $q->setParameter('alias', $alias);

        if (!empty($entity)) {
            $parent   = $entity->getTranslationParent();
            $children = $entity->getTranslationChildren();
            if ($parent || count($children)) {
                //allow same alias among language group
                $ids = array();

                if (!empty($parent)) {
                    $children = $parent->getTranslationChildren();
                    $ids[] = $parent->getId();
                }

                foreach ($children as $child) {
                    if ($child->getId() != $entity->getId()) {
                        $ids[] = $child->getId();
                    }
                }
                $q->andWhere($q->expr()->notIn('e.id', $ids));
            }
            $parent   = $entity->getVariantParent();
            $children = $entity->getVariantChildren();
            if ($parent || count($children)) {
                //allow same alias among language group
                $ids = array();

                if (!empty($parent)) {
                    $children = $parent->getVariantChildren();
                    $ids[] = $parent->getId();
                }

                foreach ($children as $child) {
                    if ($child->getId() != $entity->getId()) {
                        $ids[] = $child->getId();
                    }
                }
                $q->andWhere($q->expr()->notIn('e.id', $ids));
            }
            if ($entity->getId()) {
                $q->andWhere('e.id != :id');
                $q->setParameter('id', $entity->getId());
            }
        }

        $results = $q->getQuery()->getSingleResult();

        return $results['alias_count'];
    }

    /**
     * @param string $search
     * @param int    $limit
     * @param int    $start
     * @param bool   $viewOther
     * @param bool   $topLevel
     * @param array $ignoreIds
     *
     * @return array
     */
    public function getPageList($search = '', $limit = 10, $start = 0, $viewOther = false, $topLevel = false, $ignoreIds = array())
    {
        $q = $this->createQueryBuilder('p');
        $q->select('partial p.{id, title, language, alias}');

        if (!empty($search)) {
            $q->andWhere($q->expr()->like('p.title', ':search'))
                ->setParameter('search', "{$search}%");
        }

        if (!$viewOther) {
            $q->andWhere($q->expr()->eq('p.createdBy', ':id'))
                ->setParameter('id', $this->currentUser->getId());
        }

        if ($topLevel == 'translation') {
            //only get top level pages
            $q->andWhere($q->expr()->isNull('p.translationParent'));
            //translations cannot be assigned to a/b tests
            $q->andWhere($q->expr()->isNull('p.variantParent'));
        } elseif ($topLevel == 'variant') {
            $q->andWhere($q->expr()->isNull('p.variantParent'));
        }

        if (!empty($ignoreIds)) {
            $q->andWhere($q->expr()->notIn('p.id', ':pageIds'))
                ->setParameter('pageIds', $ignoreIds);
        }
        $q->orderBy('p.title');

        if (!empty($limit)) {
            $q->setFirstResult($start)
                ->setMaxResults($limit);
        }

        return $q->getQuery()->getArrayResult();
    }

    /**
     * {@inheritdoc}
     */
    protected function addCatchAllWhereClause(&$q, $filter)
    {
        $unique  = $this->generateRandomParameterName(); //ensure that the string has a unique parameter identifier
        $string  = ($filter->strict) ? $filter->string : "%{$filter->string}%";

        $expr = $q->expr()->orX(
            $q->expr()->like('p.title',  ":$unique"),
            $q->expr()->like('p.alias',  ":$unique")
        );
        if ($filter->not) {
            $expr = $q->expr()->not($expr);
        }
        return array(
            $expr,
            array("$unique" => $string)
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function addSearchCommandWhereClause(&$q, $filter)
    {
        $command         = $filter->command;
        $unique          = $this->generateRandomParameterName();
        $returnParameter = true; //returning a parameter that is not used will lead to a Doctrine error
        $expr            = false;
        switch ($command) {
            case $this->translator->trans('mautic.core.searchcommand.ispublished'):
                $expr = $q->expr()->eq("p.isPublished", ":$unique");
                $forceParameters = array($unique => true);
                break;
            case $this->translator->trans('mautic.core.searchcommand.isunpublished'):
                $expr = $q->expr()->eq("p.isPublished", ":$unique");
                $forceParameters = array($unique => false);
                break;
            case $this->translator->trans('mautic.core.searchcommand.isuncategorized'):
                $expr = $q->expr()->orX(
                    $q->expr()->isNull('p.category'),
                    $q->expr()->eq('p.category', $q->expr()->literal(''))
                );
                $returnParameter = false;
                break;
            case $this->translator->trans('mautic.core.searchcommand.ismine'):
                $expr = $q->expr()->eq("IDENTITY(p.createdBy)", $this->currentUser->getId());
                $returnParameter = false;
                break;
            case $this->translator->trans('mautic.core.searchcommand.category'):
                $expr = $q->expr()->like('c.alias', ":$unique");
                $filter->strict = true;
                break;
            case $this->translator->trans('mautic.core.searchcommand.lang'):
                $langUnique       = $this->generateRandomParameterName();
                $langValue        = $filter->string . "_%";
                $forceParameters = array(
                    $langUnique => $langValue,
                    $unique     => $filter->string
                );
                $expr = $q->expr()->orX(
                    $q->expr()->eq('p.language', ":$unique"),
                    $q->expr()->like('p.language', ":$langUnique")
                );
                break;
        }

        if ($expr && $filter->not) {
            $expr = $q->expr()->not($expr);
        }

        if (!empty($forceParameters)) {
            $parameters = $forceParameters;
        } elseif (!$returnParameter) {
            $parameters = array();
        } else {
            $string     = ($filter->strict) ? $filter->string : "%{$filter->string}%";
            $parameters = array("$unique" => $string);
        }

        return array( $expr, $parameters );
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchCommands()
    {
        return array(
            'mautic.core.searchcommand.ispublished',
            'mautic.core.searchcommand.isunpublished',
            'mautic.core.searchcommand.isuncategorized',
            'mautic.core.searchcommand.ismine',
            'mautic.core.searchcommand.category',
            'mautic.core.searchcommand.lang'
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOrder()
    {
        return array(
            array('p.title', 'ASC')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTableAlias()
    {
        return 'p';
    }

    /**
     * Resets variant_start_date and variant_hits
     *
     * @param $variantParentId
     * @param $date
     */
    public function resetVariants($variantParentId, $date)
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->update(MAUTIC_TABLE_PREFIX . 'pages')
            ->set('variant_hits', 0)
            ->set('variant_start_date', ':date')
            ->setParameter('date', $date)
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->eq('id', (int) $variantParentId),
                    $qb->expr()->eq('variant_parent_id', (int) $variantParentId)
                )
            )
            ->execute();
    }

    /**
     * Null variant and translation parent
     *
     * @param $ids
     */
    public function nullParents($ids)
    {
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->update(MAUTIC_TABLE_PREFIX . 'pages')
            ->set('variant_parent_id', ':null')
            ->setParameter('null', null)
            ->where(
                $qb->expr()->in('variant_parent_id', $ids)
            )
            ->execute();

        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb->update(MAUTIC_TABLE_PREFIX . 'pages')
            ->set('translation_parent_id', ':null')
            ->setParameter('null', null)
            ->where(
                $qb->expr()->in('translation_parent_id', $ids)
            )
            ->execute();
    }

    /**
     * Up the hit count
     *
     * @param            $id
     * @param int        $increaseBy
     * @param bool|false $unique
     * @param bool|false $variant
     */
    public function upHitCount($id, $increaseBy = 1, $unique = false, $variant = false)
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();

        $q->update(MAUTIC_TABLE_PREFIX.'pages')
            ->set('hits', 'hits + ' . (int) $increaseBy)
            ->where('id = ' . (int) $id);

        if ($unique) {
            $q->set('unique_hits', 'unique_hits + ' . (int) $increaseBy);
        }

        if ($variant) {
            $q->set('variant_hits', 'variant_hits + ' . (int) $increaseBy);
        }

        $q->execute();
    }
}
