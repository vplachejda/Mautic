<?php

/*
 * @copyright   2019 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\Tests\Security\Permissions;

use Mautic\ApiBundle\Security\Permissions\ApiPermissions;
use Mautic\AssetBundle\Security\Permissions\AssetPermissions;
use Mautic\CampaignBundle\Security\Permissions\CampaignPermissions;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use MauticPlugin\MauticFocusBundle\Security\Permissions\FocusPermissions;
use Symfony\Component\Translation\TranslatorInterface;

class CorePermissionsTest extends \PHPUnit_Framework_TestCase
{
    private $userHelper;
    private $corePermissions;
    private $translator;
    private $permissions;

    protected function setUp()
    {
        parent::setUp();
        $this->userHelper      = $this->createMock(UserHelper::class);
        $this->translator      = $this->createMock(TranslatorInterface::class);
        $this->permissions     = ['parameter_a' => 'value_a'];
        $this->corePermissions = new CorePermissions(
            $this->userHelper,
            $this->translator,
            $this->permissions,
            [
                $this->mockBundleArray(ApiPermissions::class),
                $this->mockBundleArray(AssetPermissions::class),
                $this->mockBundleArray(CampaignPermissions::class),
            ],
            [
                $this->mockBundleArray(FocusPermissions::class),
            ]
        );
    }

    public function testSettingPermissionObject()
    {
        $assetPermissions = new AssetPermissions($this->permissions);
        $this->corePermissions->setPermissionObject($assetPermissions);
        $permissionObjects = $this->corePermissions->getPermissionObjects();

        // Even though the AssetPermissions object was set upfront there are
        // still 4 objects available.
        // The other three were instantiated to keep BC.
        $this->assertCount(4, $permissionObjects);

        $this->assertSame($assetPermissions, $this->corePermissions->getPermissionObject('asset'));
        $this->assertSame($assetPermissions, $this->corePermissions->getPermissionObject(AssetPermissions::class));
        $this->assertSame($assetPermissions, $this->corePermissions->getPermissionObject('\Mautic\AssetBundle\Security\Permissions\AssetPermissions'));
        $this->assertSame($permissionObjects['campaign'], $this->corePermissions->getPermissionObject(CampaignPermissions::class));
    }

    /**
     * @param string $permissionClass
     *
     * @return array
     */
    private function mockBundleArray($permissionClass)
    {
        return ['permissionClasses' => [$permissionClass => $permissionClass]];
    }
}
