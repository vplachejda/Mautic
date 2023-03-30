<?php

namespace Mautic\LeadBundle\Tests\Helper;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\CoreBundle\Twig\Helper\AssetsHelper;
use Mautic\CoreBundle\Twig\Helper\GravatarHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Twig\Helper\AvatarHelper;
use Mautic\LeadBundle\Twig\Helper\DefaultAvatarHelper;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;

class AvatarHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var AssetsHelper
     */
    private $assetsHelperMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|PathsHelper
     */
    private $pathsHelperMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|GravatarHelper
     */
    private $gravatarHelperMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|DefaultAvatarHelper
     */
    private $defaultAvatarHelperMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Lead
     */
    private $leadMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|AvatarHelper
     */
    private $avatarHelper;

    protected function setUp(): void
    {
        $packagesMock = $this->getMockBuilder(Packages::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->assetsHelperMock        = new AssetsHelper($packagesMock);
        $this->pathsHelperMock         = $this->createMock(PathsHelper::class);
        $this->pathsHelperMock->method('getSystemPath')
        ->willReturn('http://localhost');
        $this->assetsHelperMock->setPathsHelper($this->pathsHelperMock);
        $this->defaultAvatarHelperMock = new DefaultAvatarHelper($this->pathsHelperMock, $this->assetsHelperMock);
        $this->gravatarHelperMock      = new GravatarHelper($this->defaultAvatarHelperMock, $this->createMock(CoreParametersHelper::class), $this->createMock(RequestStack::class));
        $this->leadMock                = $this->createMock(Lead::class);
        $this->avatarHelper            = new AvatarHelper($this->assetsHelperMock, $this->pathsHelperMock, $this->gravatarHelperMock, $this->defaultAvatarHelperMock);
    }

    /**
     * Test to get gravatar.
     */
    public function testGetAvatarWhenGravatar()
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SERVER_PORT']     = '80';
        $_SERVER['SERVER_NAME']     = 'localhost';
        $_SERVER['REQUEST_URI']     = 'localhost';

        $this->leadMock->method('getPreferredProfileImage')
            ->willReturn('gravatar');
        $this->leadMock->method('getSocialCache')
            ->willReturn([]);
        $this->leadMock->method('getEmail')
            ->willReturn('mautic@acquia.com');
        $avatar = $this->avatarHelper->getAvatar($this->leadMock);
        $this->assertSame('https://www.gravatar.com/avatar/96f1b78c73c1ee806cf6a4168fe9bf77?s=250&d=http%3A%2F%2Flocalhost%2Fimages%2Favatar.png', $avatar, 'Gravatar image should be returned');

        $_SERVER['SERVER_PROTOCOL'] = null;
        $_SERVER['SERVER_PORT']     = null;
        $_SERVER['SERVER_NAME']     = null;
        $_SERVER['REQUEST_URI']     = null;
    }

    /**
     * Test to get default image.
     */
    public function testGetAvatarWhenDefault()
    {
        $this->leadMock->method('getPreferredProfileImage')
            ->willReturn('gravatar');
        $this->leadMock->method('getSocialCache')
            ->willReturn([]);
        $this->leadMock->method('getEmail')
            ->willReturn('');
        $avatar = $this->avatarHelper->getAvatar($this->leadMock);

        $this->assertSame('http://localhost/images/avatar.png', $avatar, 'Default image image should be returned');
    }
}
