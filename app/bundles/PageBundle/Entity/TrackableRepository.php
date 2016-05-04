<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PageBundle\Entity;

use Doctrine\ORM\Query;
use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\EmailBundle\Entity\Email;

/**
 * Class TrackableRepository
 */
class TrackableRepository extends CommonRepository
{
    /**
     * Find redirects that are trackable
     *
     * @param $channel
     * @param $channelId
     *
     * @return mixed
     */
    public function findByChannel($channel, $channelId)
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();

        return $q->select('r.redirect_id, r.url, r.hits, r.unique_hits')
            ->from(MAUTIC_TABLE_PREFIX.'page_redirects', 'r')
            ->innerJoin('r',MAUTIC_TABLE_PREFIX.'channel_url_trackables', $this->getTableAlias(),
                $q->expr()->andX(
                    $q->expr()->eq('r.id', 't.redirect_id'),
                    $q->expr()->eq('t.channel', ':channel'),
                    $q->expr()->eq('t.channel_id', (int) $channelId)
                )
            )
            ->setParameter('channel', $channel)
            ->orderBy('r.url')
            ->execute()
            ->fetchAll();
    }

    /**
     * Get a Trackable by Redirect URL
     *
     * @param $url
     * @param $channel
     * @param $channelId
     *
     * @return array
     */
    public function findByUrl($url, $channel, $channelId)
    {
        $alias = $this->getTableAlias();
        $q = $this->createQueryBuilder($alias)
            ->innerJoin("$alias.redirect", 'r');

        $q->where(
            $q->expr()->andX(
                $q->expr()->eq("$alias.channel", ':channel'),
                $q->expr()->eq("$alias.channelId", (int) $channelId),
                $q->expr()->eq('r.url', ':url')
            )
        )
            ->setParameter('url', $url)
            ->setParameter('channel', $channel);

        $result = $q->getQuery()->getResult();
        
        return ($result) ? $result[0] : null;
    }

    /**
     * Get an array of Trackable entities by Redirect URLs
     *
     * @param array $urls
     * @param       $channel
     * @param       $channelId
     *
     * @return array
     */
    public function findByUrls(array $urls, $channel, $channelId)
    {
        $alias = $this->getTableAlias();
        $q = $this->createQueryBuilder($alias)
            ->innerJoin("$alias.redirect", 'r');

        $q->where(
            $q->expr()->andX(
                $q->expr()->eq("$alias.channel", ':channel'),
                $q->expr()->eq("$alias.channelId", (int) $channelId),
                $q->expr()->in('r.url', ':urls')
            )
        )
            ->setParameter('urls', $urls)
            ->setParameter('channel', $channel);

        return $q->getQuery()->getResult();
    }

    /**
     * Up the hit count
     *
     * @param      $redirectId
     * @param      $channel
     * @param      $channelId
     * @param int  $increaseBy
     * @param bool $unique
     */
    public function upHitCount($redirectId, $channel, $channelId, $increaseBy = 1, $unique = false)
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();

        $q->update(MAUTIC_TABLE_PREFIX.'channel_url_trackables')
            ->set('hits', 'hits + ' . (int) $increaseBy)
            ->where(
                $q->expr()->andX(
                    $q->expr()->eq('redirect_id' , (int) $redirectId),
                    $q->expr()->eq('channel', ':channel'),
                    $q->expr()->eq('channel_id', (int) $channelId)
                )
            )
            ->setParameter('channel', $channel);

        if ($unique) {
            $q->set('unique_hits', 'unique_hits + ' . (int) $increaseBy);
        }

        $q->execute();
    }

    /**
     * @return string
     */
    public function getTableAlias()
    {
        return 't';
    }
}
