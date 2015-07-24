<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\AddonBundle\Entity;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * Class AddonRepository
 */
class AddonRepository extends CommonRepository
{

    /**
     * Find an addon record by bundle name
     *
     * @param string $bundle
     *
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByBundle($bundle)
    {
        $q = $this->createQueryBuilder($this->getTableAlias());
        $q->where($q->expr()->eq('i.bundle', ':bundle'))
            ->setParameter('bundle', $bundle);

        return $q->getQuery()->getOneOrNullResult();
    }

    /**
     * @return array
     */
    public function getInstalled()
    {
        $i = $this->getTableAlias();
        $q = $this->_em->createQueryBuilder();
        $q->select($i);
        $q->from('MauticAddonBundle:Addon', $i, "$i.bundle");

        $results = $q->getQuery()->getResult();

        return $results;
    }

    /**
     * Retrieves the enabled status of all addon bundles
     *
     * @param $withId
     *
     * @return array
     */
    public function getBundleStatus($withId = false)
    {
        $q = $this->createQueryBuilder($this->getTableAlias());

        if ($withId) {
            $q->select('i.bundle AS bundle, i.isEnabled AS enabled, i.id');
        } else {
            $q->select('i.bundle AS bundle, i.isEnabled AS enabled');
        }

        $results = $q->getQuery()->getArrayResult();

        $return = array();

        foreach ($results as $result) {
            $return[$result['bundle']] = ($withId) ? $result : $result['enabled'];
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntities($args = array())
    {
        $q = $this->_em->createQueryBuilder();
        $q->select($this->getTableAlias())
            ->from('MauticAddonBundle:Addon', $this->getTableAlias(), $this->getTableAlias() . '.id');

        $args['qb'] = $q;

        return parent::getEntities($args);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOrder()
    {
        return array(
            array('i.title', 'ASC')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTableAlias()
    {
        return 'i';
    }
}
