<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Batches\Handler;

use Mautic\CoreBundle\Batches\Adapter\HandlerAdapterInterface;
use Mautic\CoreBundle\Batches\Exception\BatchActionFailException;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Symfony\Component\HttpFoundation\Request;

class SegmentHandlerAdapter implements HandlerAdapterInterface
{
    /**
     * @var LeadModel
     */
    private $leadModel;

    /**
     * @var CorePermissions
     */
    private $security;

    /**
     * @var array
     */
    private $addLeadLists = [];

    /**
     * @var array
     */
    private $removeLeadLists = [];

    /**
     * SegmentHandlerAdapter constructor.
     *
     * @param LeadModel $leadModel
     * @param CorePermissions $security
     */
    public function __construct(LeadModel $leadModel, CorePermissions $security)
    {
        $this->leadModel = $leadModel;
        $this->security = $security;
    }

    /**
     * @see HandlerAdapterInterface::loadSettings()
     * {@inheritdoc}
     */
    public function loadSettings(Request $request)
    {
        $data = $request->get('lead_batch', [], true);

        $this->addLeadLists = array_key_exists('add', $data) ? $data['add'] : [];
        $this->removeLeadLists = array_key_exists('remove', $data) ? $data['remove'] : [];
    }

    /**
     * @see HandlerAdapterInterface::update()
     * {@inheritdoc}
     */
    public function update($object)
    {
        if ($object instanceof Lead) {
            return $this->updateLead($object);
        }

        throw BatchActionFailException::sourceInHandlerNotImplementedYet($object, $this);
    }

    /**
     * Update for lead source
     *
     * @param Lead $lead
     */
    private function updateLead(Lead $lead)
    {
        if (!$this->security->hasEntityAccess('lead:leads:editown', 'lead:leads:editother', $lead->getPermissionUser())) {
            return;
        }

        if (!empty($this->addLeadLists)) {
            $this->leadModel->addToLists($lead, $this->addLeadLists);
        }

        if (!empty($this->removeLeadLists)) {
            $this->leadModel->removeFromLists($lead, $this->removeLeadLists);
        }
    }

    /**
     * @see HandlerAdapterInterface::store()
     * {@inheritdoc}
     */
    public function store(array $objects)
    {
        $this->leadModel->saveEntities($objects);
    }
}