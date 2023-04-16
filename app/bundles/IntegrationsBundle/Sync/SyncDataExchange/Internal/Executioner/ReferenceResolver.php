<?php

declare(strict_types=1);

namespace Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Executioner;

use Doctrine\DBAL\Connection;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Order\FieldDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Sync\Order\ObjectChangeDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Value\NormalizedValueDAO;
use Mautic\IntegrationsBundle\Sync\DAO\Value\ReferenceValueDAO;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Executioner\Exception\ReferenceNotFoundException;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Contact;
use Mautic\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;

final class ReferenceResolver implements ReferenceResolverInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param ObjectChangeDAO[] $changedObjects
     */
    public function resolveReferences(string $objectName, array $changedObjects): void
    {
        if (Contact::NAME !== $objectName) {
            // references are currently resolved only for contacts
            return;
        }

        foreach ($changedObjects as $changedObject) {
            foreach ($changedObject->getFields() as $field) {
                $value           = $field->getValue();
                $normalizedValue = $value->getNormalizedValue();

                if (!$normalizedValue instanceof ReferenceValueDAO) {
                    continue;
                }

                try {
                    $resolvedReference = $this->resolveReference($normalizedValue);
                } catch (ReferenceNotFoundException $e) {
                    $resolvedReference = null;
                }

                $resolvedValue = new NormalizedValueDAO($value->getType(), $resolvedReference, $resolvedReference);
                $changedObject->addField(new FieldDAO($field->getName(), $resolvedValue));
            }
        }
    }

    /**
     * @return mixed
     *
     * @throws ReferenceNotFoundException
     */
    private function resolveReference(ReferenceValueDAO $value)
    {
        if (MauticSyncDataExchange::OBJECT_COMPANY === $value->getType() && 0 < $value->getValue()) {
            return $this->getCompanyNameById($value->getValue());
        }

        return null;
    }

    /**
     * @throws ReferenceNotFoundException
     */
    private function getCompanyNameById(int $id): string
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('c.companyname');
        $qb->from(MAUTIC_TABLE_PREFIX.'companies', 'c');
        $qb->where('c.id = :id');
        $qb->setParameter('id', $id);

        $name = $qb->execute()->fetchColumn();

        if (false === $name) {
            throw new ReferenceNotFoundException(sprintf('Company reference for ID "%d" not found', $id));
        }

        return $name;
    }
}
