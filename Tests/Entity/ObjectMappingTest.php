<?php

namespace MauticPlugin\IntegrationsBundle\Tests\Entity;

use MauticPlugin\IntegrationsBundle\Entity\ObjectMapping;

class ObjectMappingTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \DateTime
     */
    private $dateCreated;

    public function setUp()
    {
        $this->dateCreated = new \DateTime();

        parent::setUp();
    }

    public function test__construct()
    {
        $objectMapping = new ObjectMapping($this->dateCreated);
        $this->assertInstanceOf(ObjectMapping::class, $objectMapping);
        $this->assertEquals($this->dateCreated, $objectMapping->getDateCreated());
    }

    public function testSetAndGetIntegrationReferenceId()
    {
        $objectMapping = new ObjectMapping($this->dateCreated);
        $objectMapping->setIntegrationReferenceId('ref');
        $this->assertEquals('ref', $objectMapping->getIntegrationReferenceId());
    }

    public function testLoadMetadata()
    {
        $metadata = new \Doctrine\ORM\Mapping\ClassMetadata(ObjectMapping::class);
        ObjectMapping::loadMetadata($metadata);

        $expectedFieldNames = [
            'id',
            'dateCreated',
            'integration',
            'internalObjectName',
            'internalObjectId',
            'integrationObjectName',
            'integrationObjectId',
            'lastSyncDate',
            'internalStorage',
            'isDeleted',
            'integrationReferenceId'
        ];
        $this->assertEquals($expectedFieldNames, $metadata->getFieldNames());

        $referenceIdMapping = $metadata->table['indexes']['integration_reference'];
        $this->assertEquals(
            [
                'integration',
                'integration_object_name',
                'integration_reference_id',
                'integration_object_id',
            ],
            $referenceIdMapping['columns'],
            'Required index is not being created.'
        );
    }
}
