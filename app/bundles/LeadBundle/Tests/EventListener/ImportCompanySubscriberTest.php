<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\EventListener;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\Import;
use Mautic\LeadBundle\Entity\LeadEventLog;
use Mautic\LeadBundle\Event\ImportInitEvent;
use Mautic\LeadBundle\Event\ImportMappingEvent;
use Mautic\LeadBundle\Event\ImportProcessEvent;
use Mautic\LeadBundle\EventListener\ImportCompanySubscriber;
use Mautic\LeadBundle\Field\FieldList;
use Mautic\LeadBundle\Model\CompanyModel;
use PHPUnit\Framework\Assert;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ImportCompanySubscriberTest extends \PHPUnit\Framework\TestCase
{
    public function testOnImportInitForUknownObject(): void
    {
        $subscriber = new ImportCompanySubscriber(
            $this->getFieldListFake(),
            $this->getCorePermissionsFake(),
            $this->getCompanyModelFake()
        );
        $event = new ImportInitEvent('unicorn');
        $subscriber->onImportInit($event);
        Assert::assertFalse($event->objectSupported);
    }

    public function testOnImportInitForContactsObjectWithoutPermissions(): void
    {
        $subscriber = new ImportCompanySubscriber(
            $this->getFieldListFake(),
            new class() extends CorePermissions {
                public function __construct()
                {
                }

                public function isGranted($requestedPermission, $mode = 'MATCH_ALL', $userEntity = null, $allowUnknown = false)
                {
                    Assert::assertSame('lead:imports:create', $requestedPermission);

                    return false;
                }
            },
            $this->getCompanyModelFake()
        );
        $event = new ImportInitEvent('companies');
        $this->expectException(AccessDeniedException::class);
        $subscriber->onImportInit($event);
    }

    public function testOnImportInitForContactsObjectWithPermissions(): void
    {
        $subscriber = new ImportCompanySubscriber(
            $this->getFieldListFake(),
            new class() extends CorePermissions {
                public function __construct()
                {
                }

                public function isGranted($requestedPermission, $mode = 'MATCH_ALL', $userEntity = null, $allowUnknown = false)
                {
                    Assert::assertSame('lead:imports:create', $requestedPermission);

                    return true;
                }
            },
            $this->getCompanyModelFake()
        );
        $event = new ImportInitEvent('companies');
        $subscriber->onImportInit($event);
        Assert::assertTrue($event->objectSupported);
        Assert::assertSame('company', $event->objectSingular);
        Assert::assertSame('mautic.lead.lead.companies', $event->objectName);
        Assert::assertSame('#mautic_company_index', $event->activeLink);
        Assert::assertSame('mautic_company_index', $event->indexRoute);
    }

    public function testOnFieldMappingForUnknownObject(): void
    {
        $subscriber = new ImportCompanySubscriber(
            $this->getFieldListFake(),
            $this->getCorePermissionsFake(),
            $this->getCompanyModelFake()
        );
        $event = new ImportMappingEvent('unicorn');
        $subscriber->onFieldMapping($event);
        Assert::assertFalse($event->objectSupported);
    }

    public function testOnFieldMapping(): void
    {
        $subscriber = new ImportCompanySubscriber(
            new class() extends FieldList {
                public function __construct()
                {
                }

                public function getFieldList(bool $byGroup = true, bool $alphabetical = true, array $filters = ['isPublished' => true, 'object' => 'lead']): array
                {
                    return ['some fields'];
                }
            },
            $this->getCorePermissionsFake(),
            $this->getCompanyModelFake()
        );
        $event = new ImportMappingEvent('companies');
        $subscriber->onFieldMapping($event);
        Assert::assertTrue($event->objectSupported);
        Assert::assertSame(
            [
                'mautic.lead.company' => [
                    'some fields',
                ],
                'mautic.lead.special_fields' => [
                    'dateAdded'      => 'mautic.lead.import.label.dateAdded',
                    'createdByUser'  => 'mautic.lead.import.label.createdByUser',
                    'dateModified'   => 'mautic.lead.import.label.dateModified',
                    'modifiedByUser' => 'mautic.lead.import.label.modifiedByUser',
                ],
            ],
            $event->fields
        );
    }

    public function testOnImportProcessForUnknownObject(): void
    {
        $subscriber = new ImportCompanySubscriber(
            $this->getFieldListFake(),
            $this->getCorePermissionsFake(),
            $this->getCompanyModelFake()
        );
        $import = new Import();
        $import->setObject('unicorn');
        $event = new ImportProcessEvent($import, new LeadEventLog(), []);
        $subscriber->onImportProcess($event);
        $this->expectException(\UnexpectedValueException::class);
        $event->wasMerged();
    }

    public function testOnImportProcessForKnownObject(): void
    {
        $subscriber = new ImportCompanySubscriber(
            $this->getFieldListFake(),
            $this->getCorePermissionsFake(),
            new class() extends CompanyModel {
                public function __construct()
                {
                }

                public function import($fields, $data, $owner = null, $skipIfExists = false)
                {
                    return true;
                }
            }
        );
        $import = new Import();
        $import->setObject('company');
        $event = new ImportProcessEvent($import, new LeadEventLog(), []);
        $subscriber->onImportProcess($event);
        Assert::assertTrue($event->wasMerged());
    }

    private function getFieldListFake(): FieldList
    {
        return new class() extends FieldList {
            public function __construct()
            {
            }
        };
    }

    private function getCorePermissionsFake(): CorePermissions
    {
        return new class() extends CorePermissions {
            public function __construct()
            {
            }
        };
    }

    private function getCompanyModelFake(): CompanyModel
    {
        return new class() extends CompanyModel {
            public function __construct()
            {
            }
        };
    }
}
