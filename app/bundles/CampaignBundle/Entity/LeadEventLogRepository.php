<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\LeadBundle\Entity\TimelineTrait;

/**
 * LeadEventLogRepository.
 */
class LeadEventLogRepository extends CommonRepository
{
    use TimelineTrait;

    /**
     * Get a lead's page event log.
     *
     * @param int   $leadId
     * @param array $options
     *
     * @return array
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLeadLogs($leadId, array $options = [])
    {
        $query = $this->getEntityManager()
            ->getConnection()
            ->createQueryBuilder()
            ->select('ll.event_id,
                    ll.campaign_id,
                    ll.date_triggered as dateTriggered,
                    e.name AS event_name,
                    e.description AS event_description,
                    c.name AS campaign_name,
                    c.description AS campaign_description,
                    ll.metadata,
                    e.type,
                    ll.is_scheduled as isScheduled,
                    ll.trigger_date as triggerDate
                    '
            )
            ->from(MAUTIC_TABLE_PREFIX.'campaign_lead_event_log', 'll')
            ->leftJoin('ll', MAUTIC_TABLE_PREFIX.'campaign_events', 'e', 'll.event_id = e.id')
            ->leftJoin('ll', MAUTIC_TABLE_PREFIX.'campaigns', 'c', 'll.campaign_id = c.id')
            ->where('ll.lead_id = '.(int) $leadId)
            ->andWhere('e.event_type = :eventType')
            ->andWhere('ll.metadata NOT LIKE \'%{s:6:"failed";i:1%\'')
            ->setParameter('eventType', 'action');

        if (isset($options['search']) && $options['search']) {
            $query->andWhere($query->expr()->orX(
                $query->expr()->like('e.name', $query->expr()->literal('%'.$options['search'].'%')),
                $query->expr()->like('e.description', $query->expr()->literal('%'.$options['search'].'%')),
                $query->expr()->like('c.name', $query->expr()->literal('%'.$options['search'].'%')),
                $query->expr()->like('c.description', $query->expr()->literal('%'.$options['search'].'%'))
            ));
        }

        if (isset($options['scheduledState'])) {
            $query->andWhere(
                $query->expr()->eq('ll.is_scheduled', ':scheduled')
            )
                ->setParameter('scheduled', $options['scheduledState'], 'boolean');
        }

        return $this->getTimelineResults($query, $options, 'e.name', 'll.date_triggered', ['metadata'], ['dateTriggered', 'triggerDate']);
    }

    /**
     * Get a lead's upcoming events.
     *
     * @param array $options
     *
     * @return array
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getUpcomingEvents(array $options = null)
    {
        $leadIps = [];

        $query = $this->_em->getConnection()->createQueryBuilder();
        $today = new DateTimeHelper();
        $query->from(MAUTIC_TABLE_PREFIX.'campaign_lead_event_log', 'll')
            ->select('ll.event_id,
                    ll.campaign_id,
                    ll.trigger_date,
                    ll.lead_id,
                    e.name AS event_name,
                    e.description AS event_description,
                    c.name AS campaign_name,
                    c.description AS campaign_description,
                    CONCAT(CONCAT(l.firstname, \' \'), l.lastname) AS lead_name')
            ->leftJoin('ll', MAUTIC_TABLE_PREFIX.'campaign_events', 'e', 'e.id = ll.event_id')
            ->leftJoin('ll', MAUTIC_TABLE_PREFIX.'campaigns', 'c', 'c.id = e.campaign_id')
            ->leftJoin('ll', MAUTIC_TABLE_PREFIX.'leads', 'l', 'l.id = ll.lead_id')
            ->where($query->expr()->gte('ll.trigger_date', ':today'))
            ->setParameter('today', $today->toUtcString());

        if (isset($options['lead'])) {
            /** @var \Mautic\CoreBundle\Entity\IpAddress $ip */
            foreach ($options['lead']->getIpAddresses() as $ip) {
                $leadIps[] = $ip->getId();
            }

            $query->andWhere('ll.lead_id = :leadId')
                ->setParameter('leadId', $options['lead']->getId());
        }

        if (isset($options['scheduled'])) {
            $query->andWhere('ll.is_scheduled = :scheduled')
                ->setParameter('scheduled', $options['scheduled'], 'boolean');
        }

        if (isset($options['eventType'])) {
            $query->andwhere('e.event_type = :eventType')
                ->setParameter('eventType', $options['eventType']);
        }

        if (isset($options['type'])) {
            $query->andwhere('e.type = :type')
                ->setParameter('type', $options['type']);
        }

        if (isset($options['limit'])) {
            $query->setMaxResults($options['limit']);
        } else {
            $query->setMaxResults(10);
        }

        $query->orderBy('ll.trigger_date');

        if (!empty($ipIds)) {
            $query->orWhere('ll.ip_address IN ('.implode(',', $ipIds).')');
        }

        if (!empty($options['canViewOthers']) && isset($this->currentUser)) {
            $query->andWhere('c.created_by = :userId')
                ->setParameter('userId', $this->currentUser->getId());
        }

        return $query->execute()->fetchAll();
    }

    /**
     * @param      $campaignId
     * @param bool $excludeScheduled
     *
     * @return array
     */
    public function getCampaignLogCounts($campaignId, $excludeScheduled = false)
    {
        $q = $this->_em->getConnection()->createQueryBuilder()
            ->select('o.event_id, count(o.lead_id) as lead_count')
            ->from(MAUTIC_TABLE_PREFIX.'campaign_lead_event_log', 'o');

        $expr = $q->expr()->andX(
            $q->expr()->eq('o.campaign_id', (int) $campaignId),
            $q->expr()->orX(
                $q->expr()->isNull('o.non_action_path_taken'),
                $q->expr()->eq('o.non_action_path_taken', ':false')
            )
        );

        if ($excludeScheduled) {
            $expr->add(
                $q->expr()->eq('o.is_scheduled', ':false')
            );
        }

        $q->where($expr)
            ->setParameter('false', false, 'boolean')
            ->groupBy('o.event_id');

        $results = $q->execute()->fetchAll();

        $return = [];

        //group by event id
        foreach ($results as $l) {
            $return[$l['event_id']] = $l['lead_count'];
        }

        return $return;
    }

    /**
     * @param $campaignId
     * @param $leadId
     */
    public function removeScheduledEvents($campaignId, $leadId)
    {
        $conn = $this->_em->getConnection();
        $conn->delete(MAUTIC_TABLE_PREFIX.'campaign_lead_event_log', [
            'lead_id'      => (int) $leadId,
            'campaign_id'  => (int) $campaignId,
            'is_scheduled' => 1,
        ]);
    }

    /**
     * Updates lead ID (e.g. after a lead merge).
     *
     * @param $fromLeadId
     * @param $toLeadId
     */
    public function updateLead($fromLeadId, $toLeadId)
    {
        // First check to ensure the $toLead doesn't already exist
        $results = $this->_em->getConnection()->createQueryBuilder()
            ->select('cl.event_id')
            ->from(MAUTIC_TABLE_PREFIX.'campaign_lead_event_log', 'cl')
            ->where('cl.lead_id = '.$toLeadId)
            ->execute()
            ->fetchAll();
        $exists = [];
        foreach ($results as $r) {
            $exists[] = $r['event_id'];
        }

        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->update(MAUTIC_TABLE_PREFIX.'campaign_lead_event_log')
            ->set('lead_id', (int) $toLeadId)
            ->where('lead_id = '.(int) $fromLeadId);

        if (!empty($exists)) {
            $q->andWhere(
                $q->expr()->notIn('event_id', $exists)
            )->execute();

            // Delete remaining leads as the new lead already belongs
            $this->_em->getConnection()->createQueryBuilder()
                ->delete(MAUTIC_TABLE_PREFIX.'campaign_lead_event_log')
                ->where('lead_id = '.(int) $fromLeadId)
                ->execute();
        } else {
            $q->execute();
        }
    }
}
