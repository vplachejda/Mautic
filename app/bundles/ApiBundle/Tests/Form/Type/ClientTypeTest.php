<?php

declare(strict_types=1);

/*
 * @copyright   2021 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\ApiBundle\Tests\Form\Type;

use Mautic\ApiBundle\Entity\oAuth1\Consumer;
use Mautic\ApiBundle\Form\Type\ClientType;
use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ClientTypeTest extends TestCase
{
    /**
     * @var ClientType
     */
    private $clientType;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var FormBuilderInterface
     */
    private $builder;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Consumer
     */
    private $consumer;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->translator   = $this->createMock(TranslatorInterface::class);
        $this->validator    = $this->createMock(ValidatorInterface::class);
        $this->session      = $this->createMock(Session::class);
        $this->router       = $this->createMock(RouterInterface::class);
        $this->builder      = $this->createMock(FormBuilderInterface::class);
        $this->request      = $this->createMock(Request::class);
        $this->consumer     = new Consumer();

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($this->request);

        $this->request->expects($this->once())
            ->method('get')
            ->with('api_mode', null);

        $this->clientType = new ClientType(
            $this->requestStack,
            $this->translator,
            $this->validator,
            $this->session,
            $this->router
        );
    }

    public function testThatBuildFormCallsEventSubscribers(): void
    {
        $options = [
            'data' => $this->consumer,
        ];

        $this->builder->expects($this->any())
            ->method('create')
            ->willReturnSelf();

        $cleanSubscriber    = new CleanFormSubscriber([]);
        $formExitSubscriber = new FormExitSubscriber('api.client', $options);

        $this->builder->expects($this->exactly(2))
            ->method('addEventSubscriber')
            ->withConsecutive(
                [$cleanSubscriber],
                [$formExitSubscriber]
            );

        $this->clientType->buildForm($this->builder, $options);
    }
}
