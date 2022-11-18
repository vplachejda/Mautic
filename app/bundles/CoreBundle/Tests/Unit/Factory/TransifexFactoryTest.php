<?php

namespace Mautic\CoreBundle\Tests\Unit\Factory;

use Mautic\CoreBundle\Factory\TransifexFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\Transifex\Exception\InvalidConfigurationException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Client\ClientInterface;

class TransifexFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ClientInterface|MockObject
     */
    private $client;

    /**
     * @var CoreParametersHelper|MockObject
     */
    private $coreParametersHelper;

    /**
     * @var TransifexFactory
     */
    private $transifexFactory;

    protected function setUp(): void
    {
        $this->client               = $this->createMock(ClientInterface::class);
        $this->coreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $this->transifexFactory     = new TransifexFactory($this->client, $this->coreParametersHelper);
    }

    public function testCreatingTransifexWithoutCredentials(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->transifexFactory->getTransifex();
    }

    public function testCreatingTransifexWithCredentials(): void
    {
        $this->coreParametersHelper->expects($this->once())
            ->method('get')
            ->with('transifex_api_token')
            ->willReturn('the_api_key');

        $transifex = $this->transifexFactory->getTransifex();

        $this->assertSame('the_api_key', $transifex->getConfig()->getApiToken());
        $this->assertSame('https://rest.api.transifex.com', $transifex->getConfig()->getBaseUri());
        $this->assertSame('mautic', $transifex->getConfig()->getOrganization());
        $this->assertSame('mautic', $transifex->getConfig()->getProject());
    }
}
