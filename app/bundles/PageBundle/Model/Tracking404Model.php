<?php

namespace Mautic\PageBundle\Model;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\PageBundle\Entity\Page;
use Mautic\PageBundle\Entity\Redirect;
use Symfony\Component\HttpFoundation\Request;

class Tracking404Model
{
    private \Mautic\LeadBundle\Tracker\ContactTracker $contactTracker;

    private \Mautic\PageBundle\Model\PageModel $pageModel;

    private \Mautic\CoreBundle\Helper\CoreParametersHelper $coreParametersHelper;

    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        ContactTracker $contactTracker,
        PageModel $pageModel
    ) {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->contactTracker       = $contactTracker;
        $this->pageModel            = $pageModel;
    }

    /**
     * @param Page|Redirect $entity
     *
     * @throws \Exception
     */
    public function hitPage($entity, Request $request): void
    {
        $this->pageModel->hitPage($entity, $request, 404);
    }

    public function isTrackable(): bool
    {
        if (!$this->coreParametersHelper->get('do_not_track_404_anonymous')) {
            return true;
        }
        // already tracked and identified contact
        if ($lead = $this->contactTracker->getContactByTrackedDevice()) {
            if (!$lead->isAnonymous()) {
                return true;
            }
        }

        return false;
    }
}
