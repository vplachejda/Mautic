<?php

namespace Mautic\PageBundle\EventListener;

use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\CoreBundle\Twig\Helper\AssetsHelper;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\PageBundle\Entity\HitRepository;
use Mautic\PageBundle\Entity\PageRepository;
use Mautic\PageBundle\Entity\RedirectRepository;
use Mautic\PageBundle\Event as Events;
use Mautic\PageBundle\Model\PageModel;
use Mautic\PageBundle\PageEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PageSubscriber implements EventSubscriberInterface
{
    /**
     * @var AssetsHelper
     */
    private $assetsHelper;

    /**
     * @var AuditLogModel
     */
    private $auditLogModel;

    /**
     * @var IpLookupHelper
     */
    private $ipLookupHelper;

    /**
     * @var PageModel
     */
    private $pageModel;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var HitRepository
     */
    private $hitRepository;

    /**
     * @var PageRepository
     */
    private $pageRepository;

    /**
     * @var RedirectRepository
     */
    private $redirectRepository;

    /**
     * @var LeadRepository
     */
    private $contactRepository;

    public function __construct(
        AssetsHelper $assetsHelper,
        IpLookupHelper $ipLookupHelper,
        AuditLogModel $auditLogModel,
        PageModel $pageModel,
        LoggerInterface $logger,
        HitRepository $hitRepository,
        PageRepository $pageRepository,
        RedirectRepository $redirectRepository,
        LeadRepository $contactRepository
    ) {
        $this->assetsHelper       = $assetsHelper;
        $this->ipLookupHelper     = $ipLookupHelper;
        $this->auditLogModel      = $auditLogModel;
        $this->pageModel          = $pageModel;
        $this->logger             = $logger;
        $this->hitRepository      = $hitRepository;
        $this->pageRepository     = $pageRepository;
        $this->redirectRepository = $redirectRepository;
        $this->contactRepository  = $contactRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            PageEvents::PAGE_POST_SAVE   => ['onPagePostSave', 0],
            PageEvents::PAGE_POST_DELETE => ['onPageDelete', 0],
            PageEvents::PAGE_ON_DISPLAY  => ['onPageDisplay', -255], // We want this to run last
        ];
    }

    /**
     * Add an entry to the audit log.
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

        // Get scripts to insert before a custom tag
        $params = $event->getParams();
        if (count($params) > 0) {
            if (isset($params['custom_tag']) && $customTag = $params['custom_tag']) {
                ob_start();
                $this->assetsHelper->outputScripts('customTag');
                $bodyCustomTag = ob_get_clean();

                if ($bodyCustomTag) {
                    $content = str_ireplace($customTag, $bodyCustomTag."\n".$customTag, $content);
                }
            }
        }

        $event->setContent($content);
    }
}
