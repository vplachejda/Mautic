<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\PluginBundle\Model;

use Mautic\CoreBundle\Model\FormModel;
use Mautic\PluginBundle\Integration\IntegrationObject;

/**
 * Class IntegrationEntityModel.
 */
class IntegrationEntityModel extends FormModel
{
    public function getIntegrationEntityRepository()
    {
        return $this->em->getRepository('MauticPluginBundle:IntegrationEntity');
    }

    public function logDataSync(IntegrationObject $integrationObject)
    {
    }

    public function getSyncedRecords(IntegrationObject $integrationObject, $integrationName, $recordList)
    {
        if (!$formattedRecords = $this->formatListOfContacts($recordList)) {
            return [];
        }

        $integrationEntityRepo = $this->getIntegrationEntityRepository();

        $syncedRecords = $integrationEntityRepo->getIntegrationsEntityId(
            $integrationName,
            $integrationObject->getType(),
            $integrationObject->getInternalType(),
            null,
            null,
            null,
            false,
            0,
            0,
            $formattedRecords
        );

        return $syncedRecords;
    }

    public function getRecordList($integrationObject)
    {
        $recordList = [];

        foreach ($integrationObject->getRecords() as $record) {
            $recordList[$record['Id']] = [
                'id' => $record['Id'],
            ];
        }

        return $recordList;
    }

    public function formatListOfContacts($recordList)
    {
        if (empty($recordList)) {
            return null;
        }

        $csList = is_array($recordList) ? implode('", "', array_keys($recordList)) : $recordList;
        $csList = '"'.$csList.'"';

        return $csList;
    }

    public function getMauticContactsById($mauticContactIds, $integrationName, $internalObject)
    {
        if (!$formattedRecords = $this->formatListOfContacts($mauticContactIds)) {
            return [];
        }
        $integrationEntityRepo = $this->getIntegrationEntityRepository();
        $mauticContacts        = $integrationEntityRepo->getIntegrationsEntityId(
            $integrationName,
            null,
            $internalObject,
            null,
            null,
            null,
            false,
            0,
            0,
            $formattedRecords
        );

        return $mauticContacts;
    }
}
