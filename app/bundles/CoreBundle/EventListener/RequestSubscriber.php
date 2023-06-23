<?php

namespace Mautic\CoreBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class RequestSubscriber implements EventSubscriberInterface
{
    /**
     * @var CsrfTokenManagerInterface
     */
    private $tokenManager;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var Environment
     */
    private $twig;

    public function __construct(
        CsrfTokenManagerInterface $tokenManager,
        TranslatorInterface $translator,
        Environment $twig
    ) {
        $this->tokenManager = $tokenManager;
        $this->translator   = $translator;
        $this->twig         = $twig;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['validateCsrfTokenForAjaxPost', 0],
        ];
    }

    public function validateCsrfTokenForAjaxPost(RequestEvent $event)
    {
        $request = $event->getRequest();

        if ($this->isAjaxPost($request) && $this->isSecurePath($request) && !$this->isCsrfTokenFromRequestHeaderValid($request)) {
            $message  = $this->translator->trans('mautic.core.error.csrf', [], 'flashes');
            $data     = ['flashes' => ['error' => $message]];
            $content  = $this->twig->render('@MauticCore/Notification/flash_messages.html.twig', $data);
            $response = new JsonResponse(['flashes' => $content], Response::HTTP_OK);
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }

    /**
     * @return bool
     */
    private function isAjaxPost(Request $request)
    {
        return $request->isXmlHttpRequest() && Request::METHOD_POST === $request->getMethod();
    }

    /**
     * @return bool
     */
    private function isSecurePath(Request $request)
    {
        return 1 === preg_match('/^\/s\//', $request->getPathinfo());
    }

    /**
     * @return bool
     */
    private function isCsrfTokenFromRequestHeaderValid(Request $request)
    {
        $csrfRequestToken = $request->headers->get('X-CSRF-Token');

        return $this->tokenManager->isTokenValid(new CsrfToken('mautic_ajax_post', $csrfRequestToken));
    }
}
