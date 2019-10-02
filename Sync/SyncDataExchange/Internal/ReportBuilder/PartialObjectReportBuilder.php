<?php

declare(strict_types=1);

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ReportBuilder;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\IntegrationsBundle\Entity\FieldChangeRepository;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\ObjectDAO as ReportObjectDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\ReportDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Request\ObjectDAO as RequestObjectDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Request\RequestDAO;
use MauticPlugin\IntegrationsBundle\Sync\Exception\FieldNotFoundException;
use MauticPlugin\IntegrationsBundle\Sync\Exception\ObjectNotFoundException;
use MauticPlugin\IntegrationsBundle\Sync\Exception\ObjectNotSupportedException;
use MauticPlugin\IntegrationsBundle\Sync\Logger\DebugLogger;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Helper\FieldHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ObjectHelper\CompanyObjectHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ObjectHelper\ContactObjectHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;

class PartialObjectReportBuilder
{
    /**
     * @var FieldChangeRepository
     */
    private $fieldChangeRepository;

    /**
     * @var FieldHelper
     */
    private $fieldHelper;

    /**
     * @var ContactObjectHelper
     */
    private $contactObjectHelper;

    /**
     * @var CompanyObjectHelper
     */
    private $companyObjectHelper;

    /**
     * @var FieldBuilder
     */
    private $fieldBuilder;

    /**
     * @var array
     */
    private $reportObjects = [];

    /**
     * @var array
     */
    private $lastProcessedTrackedId = [];

    /**
     * @var array
     */
    private $objectsWithMissingFields = [];

    /**
     * @var ReportDAO
     */
    private $syncReport;

    /**
     * @param FieldChangeRepository $fieldChangeRepository
     * @param FieldHelper           $fieldHelper
     * @param ContactObjectHelper   $contactObjectHelper
     * @param CompanyObjectHelper   $companyObjectHelper
     * @param FieldBuilder          $fieldBuilder
     */
    public function __construct(
        FieldChangeRepository $fieldChangeRepository,
        FieldHelper $fieldHelper,
        ContactObjectHelper $contactObjectHelper,
        CompanyObjectHelper $companyObjectHelper,
        FieldBuilder $fieldBuilder
    ) {
        $this->fieldChangeRepository = $fieldChangeRepository;
        $this->fieldHelper           = $fieldHelper;
        $this->contactObjectHelper   = $contactObjectHelper;
        $this->companyObjectHelper   = $companyObjectHelper;
        $this->fieldBuilder          = $fieldBuilder;
    }

    /**
     * @param RequestDAO $requestDAO
     *
     * @return ReportDAO
     */
    public function buildReport(RequestDAO $requestDAO): ReportDAO
    {
        $this->syncReport = new ReportDAO(MauticSyncDataExchange::NAME);
        $requestedObjects = $requestDAO->getObjects();

        foreach ($requestedObjects as $objectDAO) {
            try {
                if (!isset($this->lastProcessedTrackedId[$objectDAO->getObject()])) {
                    $this->lastProcessedTrackedId[$objectDAO->getObject()] = 0;
                }

                $fieldsChanges = $this->fieldChangeRepository->findChangesBefore(
                    $requestDAO->getSyncToIntegration(),
                    $this->fieldHelper->getFieldObjectName($objectDAO->getObject()),
                    $objectDAO->getToDateTime(),
                    $this->lastProcessedTrackedId[$objectDAO->getObject()]
                );

                $this->reportObjects = [];
                foreach ($fieldsChanges as $fieldChange) {
                    $this->processFieldChange($fieldChange, $objectDAO);
                }

                try {
                    $incompleteObjects = $this->findObjectsWithMissingFields($objectDAO);
                    $this->completeObjectsWithMissingFields($incompleteObjects, $objectDAO);
                } catch (ObjectNotFoundException $exception) {
                    // Process the others
                    DebugLogger::log(
                        MauticSyncDataExchange::NAME,
                        $exception->getMessage(),
                        __CLASS__.':'.__FUNCTION__
                    );
                }
            } catch (ObjectNotSupportedException $exception) {
                DebugLogger::log(
                    MauticSyncDataExchange::NAME,
                    $exception->getMessage(),
                    __CLASS__.':'.__FUNCTION__
                );
            }
        }

        return $this->syncReport;
    }

    /**
     * @param array            $fieldChange
     * @param RequestObjectDAO $objectDAO
     *
     * @throws ObjectNotSupportedException
     */
    private function processFieldChange(array $fieldChange, RequestObjectDAO $objectDAO): void
    {
        $objectId = (int) $fieldChange['object_id'];

        // Track the last processed ID to prevent loops for objects that were set to be retried later
        if ($objectId > $this->lastProcessedTrackedId[$objectDAO->getObject()]) {
            $this->lastProcessedTrackedId[$objectDAO->getObject()] = $objectId;
        }

        $object           = $this->getObjectNameFromEntityName($fieldChange['object_type']);
        $objectId         = (int) $fieldChange['object_id'];
        $modifiedDateTime = new \DateTime($fieldChange['modified_at'], new \DateTimeZone('UTC'));

        if (!array_key_exists($object, $this->reportObjects)) {
            $this->reportObjects[$object] = [];
        }

        if (!array_key_exists($objectId, $this->reportObjects[$object])) {
            /** @var ReportObjectDAO $reportObjectDAO */
            $this->reportObjects[$object][$objectId] = $reportObjectDAO = new ReportObjectDAO($object, $objectId);
            $this->syncReport->addObject($reportObjectDAO);
            $reportObjectDAO->setChangeDateTime($modifiedDateTime);
        }

        /** @var ReportObjectDAO $reportObjectDAO */
        $reportObjectDAO = $this->reportObjects[$object][$objectId];

        $reportObjectDAO->addField(
            $this->fieldHelper->getFieldChangeObject($fieldChange)
        );

        // Track the latest change as the object's change date/time
        if ($reportObjectDAO->getChangeDateTime() > $modifiedDateTime) {
            $reportObjectDAO->setChangeDateTime($modifiedDateTime);
        }
    }

    /**
     * @param RequestObjectDAO $requestObjectDAO
     *
     * @return array
     *
     * @throws ObjectNotFoundException
     */
    private function findObjectsWithMissingFields(RequestObjectDAO $requestObjectDAO): array
    {
        $objectName                     = $requestObjectDAO->getObject();
        $fields                         = $requestObjectDAO->getFields();
        $syncObjects                    = $this->syncReport->getObjects($objectName);
        $this->objectsWithMissingFields = [];

        foreach ($syncObjects as $syncObject) {
            $missingFields = [];
            foreach ($fields as $field) {
                try {
                    $syncObject->getField($field);
                } catch (FieldNotFoundException $exception) {
                    $missingFields[] = $field;
                }
            }

            if ($missingFields) {
                $this->objectsWithMissingFields[$syncObject->getObjectId()] = $missingFields;
            }
        }

        if (!$this->objectsWithMissingFields) {
            return [];
        }

        switch ($objectName) {
            case MauticSyncDataExchange::OBJECT_CONTACT:
                $mauticObjects = $this->contactObjectHelper->findObjectsByIds(array_keys($this->objectsWithMissingFields));

                break;
            case MauticSyncDataExchange::OBJECT_COMPANY:
                $mauticObjects = $this->companyObjectHelper->findObjectsByIds(array_keys($this->objectsWithMissingFields));

                break;
            default:
                throw new ObjectNotFoundException($objectName);
        }

        return $mauticObjects;
    }

    /**
     * @param array            $incompleteObjects
     * @param RequestObjectDAO $requestObjectDAO
     */
    private function completeObjectsWithMissingFields(array $incompleteObjects, RequestObjectDAO $requestObjectDAO): void
    {
        foreach ($incompleteObjects as $incompleteObject) {
            $missingFields   = $this->objectsWithMissingFields[$incompleteObject['id']];
            $reportObjectDAO = $this->syncReport->getObject($requestObjectDAO->getObject(), $incompleteObject['id']);

            foreach ($missingFields as $field) {
                try {
                    $reportFieldDAO = $this->fieldBuilder->buildObjectField(
                        $field,
                        $incompleteObject,
                        $requestObjectDAO,
                        $this->syncReport->getIntegration()
                    );
                    $reportObjectDAO->addField($reportFieldDAO);
                } catch (FieldNotFoundException $exception) {
                    // Field is not supported so keep going
                    DebugLogger::log(
                        MauticSyncDataExchange::NAME,
                        $exception->getMessage(),
                        __CLASS__.':'.__FUNCTION__
                    );
                }
            }
        }
    }

    /**
     * @param string $entityName
     *
     * @return string
     *
     * @throws ObjectNotSupportedException
     */
    private function getObjectNameFromEntityName(string $entityName)
    {
        switch ($entityName) {
            case Lead::class:
                return MauticSyncDataExchange::OBJECT_CONTACT;
            case Company::class:
                return MauticSyncDataExchange::OBJECT_COMPANY;
            default:
                throw new ObjectNotSupportedException(MauticSyncDataExchange::NAME, $entityName);
        }
    }
}
