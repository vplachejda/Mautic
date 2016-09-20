<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\Migrations;

use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Migrations\SkipMigrationException;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160920195943 extends AbstractMauticMigration
{
    /**
     * @param Schema $schema
     *
     * @throws SkipMigrationException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function preUp(Schema $schema)
    {
        $columns = $schema->getTable($this->prefix.'emails')->getColumns();
        
        if (array_key_exists('dynamic_content', $columns)) {

            throw new SkipMigrationException('Schema includes this migration');
        }
    }

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql("ALTER TABLE {$this->prefix}emails ADD dynamic_content LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)';");
        $this->addSql("UPDATE {$this->prefix}emails SET dynamic_content = 'a:1:{i:0;a:3:{s:9:\"tokenName\";N;s:7:\"content\";N;s:7:\"filters\";a:1:{i:0;a:2:{s:7:\"content\";N;s:7:\"filters\";a:1:{i:0;a:1:{s:4:\"glue\";N;}}}}}}' WHERE dynamic_content IS NULL;");
    }
}
