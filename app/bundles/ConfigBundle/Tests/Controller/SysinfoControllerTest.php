<?php

declare(strict_types=1);

namespace Mautic\ConfigBundle\Tests\Controller;

use Mautic\ConfigBundle\Model\SysinfoModel;
use Mautic\CoreBundle\Test\AbstractMauticTestCase;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class SysinfoControllerTest extends AbstractMauticTestCase
{
    public function testDbInfoIsShown(): void
    {
        /** @var SysinfoModel */
        $sysinfoModel = self::$container->get('mautic.config.model.sysinfo');
        $dbInfo       = $sysinfoModel->getDbInfo();

        // Request sysinfo page
        $crawler = $this->client->request(Request::METHOD_GET, '/s/sysinfo');
        Assert::assertTrue($this->client->getResponse()->isOk());

        $dbVersion  = $crawler->filterXPath("//td[@id='dbinfo-version']")->text();
        $dbDriver   = $crawler->filterXPath("//td[@id='dbinfo-driver']")->text();
        $dbPlatform = $crawler->filterXPath("//td[@id='dbinfo-platform']")->text();

        Assert::assertSame($dbInfo['version'], $dbVersion);
        Assert::assertSame($dbInfo['driver'], $dbDriver);
        Assert::assertSame($dbInfo['platform'], $dbPlatform);
    }
}
