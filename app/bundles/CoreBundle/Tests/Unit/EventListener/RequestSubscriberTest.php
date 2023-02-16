<?php

namespace Mautic\CoreBundle\Tests\Unit\EventListener;

use Mautic\CoreBundle\EventListener\RequestSubscriber;
use Mautic\CoreBundle\Helper\TemplatingHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Templating\DelegatingEngine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RequestSubscriberTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var RequestSubscriber
     */
    private $subscriber;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var MockObject&RequestEvent
     */
    private $event;

    protected function setUp(): void
    {
        $aCsrfTokenId    = 45;
        $aCsrfTokenValue = 'csrf-token-value';

        $csrfTokenManagerMock = $this->createMock(CsrfTokenManagerInterface::class);

        $csrfTokenManagerMock
            ->method('getToken')
            ->willReturn(new CsrfToken($aCsrfTokenId, $aCsrfTokenValue));

        $this->request = new Request();

        $this->event = $this->getMockBuilder(RequestEvent::class)
            ->setConstructorArgs([
                $this->createMock(HttpKernelInterface::class),
                $this->request,
                HttpKernelInterface::MASTER_REQUEST,
            ])
            ->getMock();

        $this->event
            ->expects($this->any())
            ->method('getRequest')
            ->willReturn($this->request);

        $templatingHelper = $this->createMock(TemplatingHelper::class);

        $templatingHelper
            ->method('getTemplating')
            ->willReturn($this->createMock(DelegatingEngine::class));

        $this->subscriber = new RequestSubscriber(
            $csrfTokenManagerMock,
            $this->createMock(TranslatorInterface::class),
            $templatingHelper
        );
    }

    public function testTheValidateCsrfTokenForAjaxPostMethodAsRegularPost(): void
    {
        $this->event
            ->expects($this->never())
            ->method('setResponse');

        $this->request->server->set('REQUEST_METHOD', 'POST');

        $this->subscriber->validateCsrfTokenForAjaxPost($this->event);
    }

    public function testTheValidateCsrfTokenForAjaxPostMethodAsAjaxGet(): void
    {
        $this->event
            ->expects($this->never())
            ->method('setResponse');

        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->request->server->set('REQUEST_METHOD', 'GET');

        $this->subscriber->validateCsrfTokenForAjaxPost($this->event);
    }

    public function testTheValidateCsrfTokenForAjaxPostMethodAsAjaxPostOnPublicRoute(): void
    {
        $this->event
            ->expects($this->never())
            ->method('setResponse');

        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->request->server->set('REQUEST_METHOD', 'POST');
        $this->request->server->set('REQUEST_URI', '/some-public-page');

        $this->subscriber->validateCsrfTokenForAjaxPost($this->event);
    }

    public function testTheValidateCsrfTokenForAjaxPostMethodAsAjaxPostOnSecureRouteWithMissingCsrf(): void
    {
        $this->event
            ->expects($this->once())
            ->method('setResponse');

        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->request->server->set('REQUEST_METHOD', 'POST');
        $this->request->server->set('REQUEST_URI', '/s/some-secure-page');

        $this->subscriber->validateCsrfTokenForAjaxPost($this->event);
    }

    public function testTheValidateCsrfTokenForAjaxPostMethodAsAjaxPostOnSecureRouteWithInvalidCsrf(): void
    {
        $this->event
            ->expects($this->once())
            ->method('setResponse');

        $this->request->headers->set('X-CSRF-Token', 'invalid-csrf-token-value');
        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->request->server->set('REQUEST_METHOD', 'POST');
        $this->request->server->set('REQUEST_URI', '/s/some-secure-page');

        $this->subscriber->validateCsrfTokenForAjaxPost($this->event);
    }

    public function testTheValidateCsrfTokenForAjaxPostMethodAsAjaxPostOnSecureRouteWithMatchingCsrf(): void
    {
        $this->event
            ->expects($this->never())
            ->method('setResponse');

        $this->request->headers->set('X-CSRF-Token', 'csrf-token-value');
        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->request->server->set('REQUEST_METHOD', 'POST');
        $this->request->server->set('REQUEST_URI', '/s/some-secure-page');

        $this->subscriber->validateCsrfTokenForAjaxPost($this->event);
    }
}
