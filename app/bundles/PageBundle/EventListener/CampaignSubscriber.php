<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PageBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CampaignBundle\Model\EventModel;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PageBundle\Entity\Page;
use Mautic\PageBundle\Event\PageHitEvent;
use Mautic\PageBundle\Model\PageModel;
use Mautic\PageBundle\PageEvents;

/**
 * Class CampaignSubscriber
 */
class CampaignSubscriber extends CommonSubscriber
{
    /**
     * @var PageModel
     */
    protected $pageModel;

    /**
     * @var EventModel
     */
    protected $campaignEventModel;

    /**
     * CampaignSubscriber constructor.
     *
     * @param MauticFactory $factory
     * @param PageModel $pageModel
     * @param EventModel $campaignEventModel
     */
    public function __construct(MauticFactory $factory, PageModel $pageModel, EventModel $campaignEventModel)
    {
        $this->pageModel = $pageModel;
        $this->campaignEventModel = $campaignEventModel;

        parent::__construct($factory);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            PageEvents::PAGE_ON_HIT           => ['onPageHit', 0],
            PageEvents::ON_CAMPAIGN_TRIGGER_DECISION => ['onCampaignTriggerDecision', 0]
        ];
    }

    /**
     * Add event triggers and actions
     *
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        //Add trigger
        $pageHitTrigger = array(
            'label'       => 'mautic.page.campaign.event.pagehit',
            'description' => 'mautic.page.campaign.event.pagehit_descr',
            'formType'    => 'campaignevent_pagehit',
            'eventName'   => PageEvents::ON_CAMPAIGN_TRIGGER_DECISION
        );
        $event->addLeadDecision('page.pagehit', $pageHitTrigger);
    }

    /**
     * Trigger actions for page hits
     *
     * @param PageHitEvent $event
     */
    public function onPageHit(PageHitEvent $event)
    {
        $hit    = $event->getHit();
        $page   = $hit->getPage();
        $typeId = $page instanceof Page ? 'page.pagehit.' . $page->getId() : null;

        $this->campaignEventModel->triggerEvent('page.pagehit', $hit, $typeId);
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerDecision(CampaignExecutionEvent $event)
    {
        $eventDetails = $event->getEventDetails();
        $config = $event->getConfig();

        if ($eventDetails == null) {
            return true;
        }

        $pageHit = $eventDetails->getPage();

        // Check Landing Pages
        if ($pageHit instanceof Page) {
            list($parent, $children)  = $this->pageModel->getVariants($pageHit);
            //use the parent (self or configured parent)
            $pageHitId = $parent->getId();
        } else {
            $pageHitId = 0;
        }

        $limitToPages = $config['pages'];

        $urlMatches   = array();

        // Check Landing Pages URL or Tracing Pixel URL
        if (isset($config['url']) && $config['url']) {
            $pageUrl        = $eventDetails->getUrl();
            $limitToUrls    = explode(',', $config['url']);

            foreach ($limitToUrls as $url) {
                $url = trim($url);
                $urlMatches[$url] = fnmatch($url, $pageUrl);
            }
        }

        // **Page hit is true if:**
        // 1. no landing page is set and no URL rule is set
        $applyToAny = (empty($config['url']) && empty($limitToPages));

        // 2. some landing pages are set and page ID match
        $langingPageIsHit = (!empty($limitToPages) && in_array($pageHitId, $limitToPages));

        // 3. URL rule is set and match with URL hit
        $urlIsHit = (!empty($config['url']) && in_array(true, $urlMatches));

        if ($applyToAny || $langingPageIsHit || $urlIsHit) {
            return $event->setResult(true);
        }

        return $event->setResult(false);
    }
}
