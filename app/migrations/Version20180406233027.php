<?php

namespace Mautic\Migrations;

use Doctrine\DBAL\Migrations\SkipMigrationException;
use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180406233027 extends AbstractMauticMigration
{
    /**
     * @param Schema $schema
     *
     * @throws SkipMigrationException
     */
    public function preUp(Schema $schema)
    {
        if ($schema->hasTable($this->prefix.'user_tokens')) {
            throw new SkipMigrationException('Schema includes this migration');
        }
    }

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql("
            CREATE TABLE `{$this->prefix}user_tokens` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `authorizator` varchar(32) NOT NULL,
            `signature` varchar(120) NOT NULL,
            `expiration` datetime DEFAULT NULL,
            `one_time_only` tinyint(4) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            UNIQUE KEY `{$this->prefix}signature` (`signature`),
            CONSTRAINT `{$this->prefix}user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `{$this->prefix}users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }
}
