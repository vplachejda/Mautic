<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Entity;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Import.
 */
class Import extends FormEntity
{
    // Statuses:
    const CREATED     = 1;
    const IN_PROGRESS = 2;
    const IMPORTED    = 3;
    const FAILED      = 4;
    const STOPPED     = 5;

    // Priorities
    const LOW    = 512;
    const NORMAL = 64;
    const HIGH   = 1;

    /**
     * @var int
     */
    private $id;

    /**
     * Base directory of the import.
     *
     * @var string
     */
    private $dir;

    /**
     * File name of the CSV file which is in the $dir.
     *
     * @var string
     */
    private $file = 'import.csv';

    /**
     * Name of the original uploaded file.
     *
     * @var string
     */
    private $originalFile;

    /**
     * Tolal line count of the CSV file.
     *
     * @var int
     */
    private $lineCount = 0;

    /**
     * Count of entities which were newly created.
     *
     * @var int
     */
    private $insertedCount = 0;

    /**
     * Count of entities which were updated.
     *
     * @var int
     */
    private $updatedCount = 0;

    /**
     * Count of ignored items.
     *
     * @var int
     */
    private $ignoredCount = 0;

    /**
     * @var bool
     */
    private $priority;

    /**
     * @var int
     */
    private $status;

    /**
     * @var DateTime
     */
    private $dateStarted;

    /**
     * @var DateTime
     */
    private $dateEnded;

    /**
     * @var string
     */
    private $object = 'lead';

    /**
     * @var array
     */
    private $properties = [];

    /**
     * An array to store failed rows.
     *
     * @var array
     */
    private $failures = [];

    public function __clone()
    {
        $this->id = null;

        parent::__clone();
    }

    public function __construct()
    {
        $this->status   = self::CREATED;
        $this->priority = self::LOW;
    }

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('imports')
            ->setCustomRepositoryClass(ImportRepository::class)
            ->addIndex(['object'], 'import_object')
            ->addIndex(['status'], 'import_status')
            ->addIndex(['priority'], 'import_priority')
            ->addId()
            ->addField('dir', Type::STRING)
            ->addField('file', Type::STRING)
            ->addNullableField('originalFile', Type::STRING, 'original_file')
            ->addNamedField('lineCount', Type::INTEGER, 'line_count')
            ->addNamedField('insertedCount', Type::INTEGER, 'inserted_count')
            ->addNamedField('updatedCount', Type::INTEGER, 'updated_count')
            ->addNamedField('ignoredCount', Type::INTEGER, 'ignored_count')
            ->addField('priority', Type::INTEGER)
            ->addField('status', Type::INTEGER)
            ->addNullableField('dateStarted', Type::DATETIME, 'date_started')
            ->addNullableField('dateEnded', Type::DATETIME, 'date_ended')
            ->addField('object', Type::STRING)
            ->addNullableField('properties', Type::JSON_ARRAY);
    }

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('dir', new Assert\NotBlank(
            ['message' => 'mautic.lead.import.dir.notblank']
        ));

        $metadata->addPropertyConstraint('file', new Assert\NotBlank(
            ['message' => 'mautic.lead.import.file.notblank']
        ));
    }

    /**
     * Prepares the metadata for API usage.
     *
     * @param $metadata
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata)
    {
        $metadata->setGroupPrefix('import')
            ->addListProperties(
                [
                    'id',
                    'dir',
                    'file',
                    'originalFile',
                    'lineCount',
                    'insertedCount',
                    'updatedCount',
                    'ignoredCount',
                    'priority',
                    'status',
                    'dateStarted',
                    'dateEnded',
                    'object',
                    'properties',
                ]
            )
            ->build();
    }

    /**
     * Populates Import stats from the array.
     *
     * @param array $stats
     *
     * @return Import
     */
    public function populateStats(array $stats)
    {
        if (isset($stats['merged'])) {
            $this->setUpdatedCount($stats['merged']);
        }

        if (isset($stats['created'])) {
            $this->setInsertedCount($stats['created']);
        }

        if (isset($stats['ignored'])) {
            $this->setIgnoredCount($stats['ignored']);
        }

        if (isset($stats['failures'])) {
            $this->failures = $stats['failures'];
        }

        return $this;
    }

    /**
     * Returns the array of stats.
     *
     * @return array
     */
    public function getStats()
    {
        return [
            'merged'   => $this->getUpdatedCount(),
            'created'  => $this->getInsertedCount(),
            'ignored'  => $this->getIgnoredCount(),
            'failures' => $this->failures,
        ];
    }

    /**
     * Add a new failure to the array.
     *
     * @param int    $key
     * @param string $msg
     *
     * @return Import
     */
    public function addFailure($key, $msg)
    {
        $this->failures[$key] = $msg;

        return $this;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $dir
     *
     * @return Import
     */
    public function setDir($dir)
    {
        $this->isChanged('dir', $dir);
        $this->dir = $dir;

        return $this;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }

    /**
     * @param string $file
     *
     * @return Import
     */
    public function setFile($file)
    {
        $this->isChanged('file', $file);
        $this->file = $file;

        return $this;
    }

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Get import file path.
     *
     * @return string
     */
    public function getFilePath()
    {
        return $this->getDir().'/'.$this->getFile();
    }

    /**
     * Removes the file if exists.
     * It won't throw any exception if the file is not readable.
     * Not removing the CSV file is not consodered a big trouble.
     * It will be removed on the next cache:clear.
     */
    public function removeFile()
    {
        $file = $this->getFilePath();

        if (file_exists($file) && is_writable($file)) {
            unlink($file);
        }
    }

    /**
     * @param string $originalFile
     *
     * @return Import
     */
    public function setOriginalFile($originalFile)
    {
        $this->isChanged('originalFile', $originalFile);
        $this->originalFile = $originalFile;

        return $this;
    }

    /**
     * @return string
     */
    public function getOriginalFile()
    {
        return $this->originalFile;
    }

    /**
     * @param int $lineCount
     *
     * @return Import
     */
    public function setLineCount($lineCount)
    {
        $this->isChanged('lineCount', $lineCount);
        $this->lineCount = $lineCount;

        return $this;
    }

    /**
     * @return int
     */
    public function getLineCount()
    {
        return $this->lineCount;
    }

    /**
     * @param int $insertedCount
     *
     * @return Import
     */
    public function setInsertedCount($insertedCount)
    {
        $this->isChanged('insertedCount', $insertedCount);
        $this->insertedCount = $insertedCount;

        return $this;
    }

    /**
     * @return Import
     */
    public function increaseInsertedCount()
    {
        return $this->setInsertedCount($this->insertedCount + 1);
    }

    /**
     * @return int
     */
    public function getInsertedCount()
    {
        return $this->insertedCount;
    }

    /**
     * @param int $updatedCount
     *
     * @return Import
     */
    public function setUpdatedCount($updatedCount)
    {
        $this->isChanged('updatedCount', $updatedCount);
        $this->updatedCount = $updatedCount;

        return $this;
    }

    /**
     * @return Import
     */
    public function increaseUpdatedCount()
    {
        return $this->setUpdatedCount($this->updatedCount + 1);
    }

    /**
     * @return int
     */
    public function getUpdatedCount()
    {
        return $this->updatedCount;
    }

    /**
     * @param int $ignoredCount
     *
     * @return Import
     */
    public function setIgnoredCount($ignoredCount)
    {
        $this->isChanged('ignoredCount', $ignoredCount);
        $this->ignoredCount = $ignoredCount;

        return $this;
    }

    /**
     * @return Import
     */
    public function increaseIgnoredCount()
    {
        return $this->setIgnoredCount($this->ignoredCount + 1);
    }

    /**
     * @return int
     */
    public function getIgnoredCount()
    {
        return $this->ignoredCount;
    }

    /**
     * @param int $priority
     *
     * @return Import
     */
    public function setPriority($priority)
    {
        $this->isChanged('priority', $priority);
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $status
     *
     * @return Import
     */
    public function setStatus($status)
    {
        $this->isChanged('status', $status);
        $this->status = $status;

        return $this;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $dateStarted
     *
     * @return Import
     */
    public function setDateStarted(\DateTime $dateStarted)
    {
        $this->isChanged('dateStarted', $dateStarted);
        $this->dateStarted = $dateStarted;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDateStarted()
    {
        return $this->dateStarted;
    }

    /**
     * @param int $dateEnded
     *
     * @return Import
     */
    public function setDateEnded(\DateTime $dateEnded)
    {
        $this->isChanged('dateEnded', $dateEnded);
        $this->dateEnded = $dateEnded;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDateEnded()
    {
        return $this->dateEnded;
    }

    /**
     * @param string $object
     *
     * @return Import
     */
    public function setObject($object)
    {
        $this->isChanged('object', $object);
        $this->object = $object;

        return $this;
    }

    /**
     * @return string
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param array $fields
     *
     * @return Import
     */
    public function setMatchedFields(array $fields)
    {
        $properties           = $this->properties;
        $properties['fields'] = $fields;

        return $this->setProperties($properties);
    }

    /**
     * @return array
     */
    public function getMatchedFields()
    {
        return empty($this->properties['fields']) ? [] : $this->properties['fields'];
    }

    /**
     * @param string $properties
     *
     * @return Import
     */
    public function setProperties($properties)
    {
        $this->isChanged('properties', $properties);
        $this->properties = $properties;

        return $this;
    }

    /**
     * @param string $properties
     *
     * @return Import
     */
    public function mergeToProperties($properties)
    {
        return $this->setProperties(array_merge($this->properties, $properties));
    }

    /**
     * Get array of default values.
     *
     * @return array
     */
    public function getDefaults()
    {
        if (isset($this->properties['defaults'])) {
            return $this->properties['defaults'];
        }

        return [];
    }

    /**
     * Set a default value to the defaults array.
     *
     * @param string $key
     * @param string $value
     *
     * @return Import
     */
    public function setDefault($key, $value)
    {
        return $this->mergeToProperties([
            'defaults' => array_merge($this->getDefaults(), [$key => $value]),
        ]);
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function getDefault($key)
    {
        return empty($this->properties['defaults'][$key]) ? null : $this->properties['defaults'][$key];
    }

    /**
     * Set headers array to the properties.
     *
     * @param array $headers
     *
     * @return Import
     */
    public function setHeaders(array $headers)
    {
        $properties            = $this->properties;
        $properties['headers'] = $headers;

        return $this->setProperties($properties);
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return empty($this->properties['headers']) ? [] : $this->properties['headers'];
    }

    /**
     * Set parser config array to the properties.
     *
     * @param array $parser
     *
     * @return Import
     */
    public function setParserConfig(array $parser)
    {
        $properties           = $this->properties;
        $properties['parser'] = $parser;

        return $this->setProperties($properties);
    }

    /**
     * @return array
     */
    public function getParserConfig()
    {
        return empty($this->properties['parser']) ? [] : $this->properties['parser'];
    }

    /**
     * @return string
     */
    public function getProperties()
    {
        return $this->properties;
    }
}
