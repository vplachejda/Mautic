<?php

namespace Mautic\LeadBundle\Tests\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Doctrine\Helper\ColumnSchemaHelper;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Field\CustomFieldColumn;
use Mautic\LeadBundle\Field\Dispatcher\FieldSaveDispatcher;
use Mautic\LeadBundle\Field\FieldList;
use Mautic\LeadBundle\Field\FieldsWithUniqueIdentifier;
use Mautic\LeadBundle\Field\LeadFieldSaver;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\ListModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FieldModelTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    public function testSingleContactFieldIsCreatedAndDeleted(): void
    {
        $fieldModel = self::$container->get('mautic.lead.model.field');

        $field = new LeadField();
        $field->setName('Test Field')
            ->setAlias('test_field')
            ->setType('string')
            ->setObject('lead');

        $fieldModel->saveEntity($field);
        $fieldModel->deleteEntity($field);

        $this->assertCount(0, $this->getColumns('leads', $field->getAlias()));
    }

    public function testSingleCompanyFieldIsCreatedAndDeleted(): void
    {
        $fieldModel = self::$container->get('mautic.lead.model.field');

        $field = new LeadField();
        $field->setName('Test Field')
            ->setAlias('test_field')
            ->setType('string')
            ->setObject('company');

        $fieldModel->saveEntity($field);
        $fieldModel->deleteEntity($field);

        $this->assertCount(0, $this->getColumns('companies', $field->getAlias()));
    }

    public function testMultipleFieldsAreCreatedAndDeleted(): void
    {
        $fieldModel = self::$container->get('mautic.lead.model.field');

        $leadField = new LeadField();
        $leadField->setName('Test Field')
            ->setAlias('test_field')
            ->setType('string')
            ->setObject('lead');

        $leadField2 = new LeadField();
        $leadField2->setName('Test Field')
            ->setAlias('test_field2')
            ->setType('string')
            ->setObject('lead');

        $companyField = new LeadField();
        $companyField->setName('Test Field')
            ->setAlias('test_field')
            ->setType('string')
            ->setObject('company');

        $companyField2 = new LeadField();
        $companyField2->setName('Test Field')
            ->setAlias('test_field2')
            ->setType('string')
            ->setObject('company');

        $fieldModel->saveEntities([$leadField, $leadField2, $companyField, $companyField2]);

        $this->assertCount(1, $this->getColumns('leads', $leadField->getAlias()));
        $this->assertCount(1, $this->getColumns('leads', $leadField2->getAlias()));
        $this->assertCount(1, $this->getColumns('companies', $companyField->getAlias()));
        $this->assertCount(1, $this->getColumns('companies', $companyField2->getAlias()));

        $fieldModel->deleteEntities([$leadField->getId(), $leadField2->getId(), $companyField->getId(), $companyField2->getId()]);

        $this->assertCount(0, $this->getColumns('leads', $leadField->getAlias()));
        $this->assertCount(0, $this->getColumns('leads', $leadField2->getAlias()));
        $this->assertCount(0, $this->getColumns('companies', $companyField->getAlias()));
        $this->assertCount(0, $this->getColumns('companies', $companyField2->getAlias()));
    }

    public function testIsUsedField(): void
    {
        $leadField = new LeadField();

        $columnSchemaHelper         = $this->createMock(ColumnSchemaHelper::class);
        $leadListModel              = $this->createMock(ListModel::class);
        $customFieldColumn          = $this->createMock(CustomFieldColumn::class);
        $fieldSaveDispatcher        = $this->createMock(FieldSaveDispatcher::class);
        $leadFieldRepository        = $this->createMock(LeadFieldRepository::class);
        $fieldsWithUniqueIdentifier = $this->createMock(FieldsWithUniqueIdentifier::class);
        $fieldList                  = $this->createMock(FieldList::class);
        $leadFieldSaver             = $this->createMock(LeadFieldSaver::class);
        $leadListModel->expects($this->once())
            ->method('isFieldUsed')
            ->with($leadField)
            ->willReturn(true);

        $model = new FieldModel(
            $columnSchemaHelper,
            $leadListModel,
            $customFieldColumn,
            $fieldSaveDispatcher,
            $leadFieldRepository,
            $fieldsWithUniqueIdentifier,
            $fieldList,
            $leadFieldSaver,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(CorePermissions::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(Translator::class),
            $this->createMock(UserHelper::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(CoreParametersHelper::class)
        );
        $this->assertTrue($model->isUsedField($leadField));
    }

    /**
     * @return array
     */
    private function getColumns($table, $column)
    {
        $stmt       = $this->connection->executeQuery(
            "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$this->connection->getParams()['dbname']}' AND TABLE_NAME = '".MAUTIC_TABLE_PREFIX
            ."$table' AND COLUMN_NAME = '$column'"
        );

        return $stmt->fetchAllAssociative();
    }
}
