<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PageBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\CoreBundle\Templating\Helper\AssetsHelper;
use Mautic\PageBundle\Event as Events;
use Mautic\PageBundle\Model\PageModel;
use Mautic\PageBundle\PageEvents;
use Mautic\QueueBundle\Event\QueueConsumerEvent;
use Mautic\QueueBundle\QueueEvents;

/**
 * Class PageSubscriber.
 */
class PageSubscriber extends CommonSubscriber
{
    /**
     * @var AssetsHelper
     */
    protected $assetsHelper;

    /**
     * @var AuditLogModel
     */
    protected $auditLogModel;

    /**
     * @var IpLookupHelper
     */
    protected $ipLookupHelper;

    /**
     * @var PageModel
     */
    protected $pageModel;

    /**
     * PageSubscriber constructor.
     *
     * @param AssetsHelper   $assetsHelper
     * @param IpLookupHelper $ipLookupHelper
     * @param AuditLogModel  $auditLogModel
     * @param PageModel      $pageModel
     */
    public function __construct(
        AssetsHelper $assetsHelper,
        IpLookupHelper $ipLookupHelper,
        AuditLogModel $auditLogModel,
        PageModel $pageModel
    )
    {
        $this->assetsHelper   = $assetsHelper;
        $this->ipLookupHelper = $ipLookupHelper;
        $this->auditLogModel  = $auditLogModel;
        $this->pageModel      = $pageModel;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PageEvents::PAGE_POST_SAVE   => ['onPagePostSave', 0],
            PageEvents::PAGE_POST_DELETE => ['onPageDelete', 0],
            PageEvents::PAGE_ON_DISPLAY  => ['onPageDisplay', -255], // We want this to run last
            QueueEvents::PAGE_HIT        => ['onPageHit', 0],
        ];
    }

    /**
     * Add an entry to the audit log.
     *
     * @param Events\PageEvent $event
     */
    public function onPagePostSave(Events\PageEvent $event)
    {
        $page = $event->getPage();
        if ($details = $event->getChanges()) {
            $log = [
                'bundle'    => 'page',
                'object'    => 'page',
                'objectId'  => $page->getId(),
                'action'    => ($event->isNew()) ? 'create' : 'update',
                'details'   => $details,
                'ipAddress' => $this->ipLookupHelper->getIpAddressFromRequest(),
            ];
            $this->auditLogModel->writeToLog($log);
        }
    }

    /**
     * Add a delete entry to the audit log.
     *
     * @param Events\PageEvent $event
     */
    public function onPageDelete(Events\PageEvent $event)
    {
        $page = $event->getPage();
        $log  = [
            'bundle'    => 'page',
            'object'    => 'page',
            'objectId'  => $page->deletedId,
            'action'    => 'delete',
            'details'   => ['name' => $page->getTitle()],
            'ipAddress' => $this->ipLookupHelper->getIpAddressFromRequest(),
        ];
        $this->auditLogModel->writeToLog($log);
    }

    /**
     * Allow event listeners to add scripts to
     * - </head> : onPageDisplay_headClose
     * - <body>  : onPageDisplay_bodyOpen
     * - </body> : onPageDisplay_bodyClose.
     *
     * @param Events\PageDisplayEvent $event
     */
    public function onPageDisplay(Events\PageDisplayEvent $event)
    {
        $content = $event->getContent();

        // Get scripts to insert before </head>
        ob_start();
        $this->assetsHelper->outputScripts('onPageDisplay_headClose');
        $headCloseScripts = ob_get_clean();

        if ($headCloseScripts) {
            $content = str_ireplace('</head>', $headCloseScripts."\n</head>", $content);
        }

        // Get scripts to insert after <body>
        ob_start();
        $this->assetsHelper->outputScripts('onPageDisplay_bodyOpen');
        $bodyOpenScripts = ob_get_clean();

        if ($bodyOpenScripts) {
            preg_match('/(<body[a-z=\s\-_:"\']*>)/i', $content, $matches);

            $content = str_ireplace($matches[0], $matches[0]."\n".$bodyOpenScripts, $content);
        }

        // Get scripts to insert before </body>
        ob_start();
        $this->assetsHelper->outputScripts('onPageDisplay_bodyClose');
        $bodyCloseScripts = ob_get_clean();

        if ($bodyCloseScripts) {
            $content = str_ireplace('</body>', $bodyCloseScripts."\n</body>", $content);
        }

        $event->setContent($content);
    }

    public function onPageHit(QueueConsumerEvent $event)
    {
        $payload = $event->getPayload();
        $hit                    = $payload['hit'];
        $page                   = $payload['page'];
        $request                = $payload['request'];
        $trackingNewlyGenerated = $payload['isNew'];
        $this->pageModel->hitPage($hit, $page, $request, $trackingNewlyGenerated);
    }
}
