<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ApiBundle\Entity\oAuth1;

use Mautic\CoreBundle\Entity\CommonRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\UserBundle\Entity\User;

/**
 * ConsumerRepository
 */
class ConsumerRepository extends CommonRepository
{

    public function getUserClients(User $user)
    {
        $result = $this->createQueryBuilder('c')
            ->join('c.users', 'u')
            ->where("u.id = " . $user->getId())
            ->getQuery()
            ->getResult();
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $args
     * @return Paginator
     */
    public function getEntities($args = array())
    {
        $q = $this
            ->createQueryBuilder('c');

        if (!$this->buildClauses($q, $args)) {
            return array();
        }

        $query = $q->getQuery();
        $result = new Paginator($query);
        return $result;
    }

    protected function addCatchAllWhereClause(&$q, $filter)
    {
        $unique  = $this->generateRandomParameterName(); //ensure that the string has a unique parameter identifier
        $string  = ($filter->strict) ? $filter->string : "%{$filter->string}%";

        $expr = $q->expr()->orX(
            $q->expr()->like('c.name',  ':'.$unique),
            $q->expr()->like('c.callback', ':'.$unique)
        );

        if ($filter->not) {
            $expr = $q->expr()->not($expr);
        }

        return array(
            $expr,
            array("$unique" => $string)
        );
    }

    protected function addSearchCommandWhereClause(&$q, $filter)
    {
        $command         = $field = $filter->command;
        $unique          = $this->generateRandomParameterName();
        $returnParameter = true; //returning a parameter that is not used will lead to a Doctrine error
        $expr            = false;

        switch ($command) {
            case $this->translator->trans('mautic.core.searchcommand.name'):
                $expr = $q->expr()->like("c.name", ':'.$unique);
                break;
            case $this->translator->trans('mautic.api.client.searchcommand.callback'):
                $expr = $q->expr()->like('c.redirectUris', ":$unique");
                break;
        }


        $string  = ($filter->strict) ? $filter->string : "%{$filter->string}%";
        if ($expr && $filter->not) {
            $expr = $q->expr()->not($expr);
        }
        return array(
            $expr,
            ($returnParameter) ? array("$unique" => $string) : array()
        );

    }

    public function getSearchCommands()
    {
        return array(
            'mautic.core.searchcommand.name',
            'mautic.api.client.searchcommand.callback',
        );
    }

    protected function getDefaultOrder()
    {
        return array(
            array('c.name', 'ASC')
        );
    }

}
