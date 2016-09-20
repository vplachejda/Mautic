<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\Migrations;

use Doctrine\DBAL\Migrations\SkipMigrationException;
use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\AbstractMauticMigration;

/**
 * Class Version20160926000000
 */
class Version20160926000000 extends AbstractMauticMigration
{
    /**
     * @param Schema $schema
     *
     * @throws SkipMigrationException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function preUp(Schema $schema)
    {
        if ($schema->hasTable($this->prefix.'companies')) {
            throw new SkipMigrationException('Schema includes this migration');
        }
    }

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $sql        = <<<SQL
CREATE TABLE {$this->prefix}companies (
  `id` int(11) NOT NULL,
  `companynumber` varchar(255) DEFAULT NULL,
  `companysource` varchar(255) DEFAULT NULL,
  `companyname` varchar(255) NOT NULL,
  `companydescription` text,
  `companyaddress1` varchar(255) DEFAULT NULL,
  `companyaddress2` varchar(255) DEFAULT NULL,
  `companycity` varchar(255) DEFAULT NULL,
  `companystate` varchar(255) DEFAULT NULL,
  `companyzipcode` varchar(11) DEFAULT NULL,
  `companycountry` varchar(255) DEFAULT NULL,
  `companyemail` varchar(255) DEFAULT NULL,
  `companyphone` varchar(50) DEFAULT NULL,
  `companyfax` varchar(50) DEFAULT NULL,
  `companyannual_revenue` float DEFAULT NULL,
  `companynumber_of_employees` int(11) DEFAULT NULL,
  `companyscore` int(11) DEFAULT NULL,
  `companywebsite` varchar(255) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `date_added` DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime)',
  `created_by` int(11) DEFAULT NULL,
  `created_by_user` varchar(255) DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `modified_by_user` varchar(255) DEFAULT NULL,
  `checked_out` datetime DEFAULT NULL COMMENT '(DC2Type:datetime)',
  `checked_out_by` int(11) DEFAULT NULL,
  `checked_out_by_user` varchar(255) DEFAULT NULL,
  `date_modified` datetime DEFAULT NULL COMMENT '(DC2Type:datetime)',
  `publish_up` datetime DEFAULT NULL COMMENT '(DC2Type:datetime)',
  `publish_down` datetime DEFAULT NULL COMMENT '(DC2Type:datetime)',
  `is_published` tinyint(4) DEFAULT NULL,
  PRIMARY KEY(id),
  INDEX {$this->prefix}companyname (`companyname`, `companyemail`)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;
SQL;
        $this->addSql($sql);
        $lead_index = $this->generatePropertyName('company_leads_xref', 'idx', array('lead_id'));
        $company_index =  $this->generatePropertyName('company_leads_xref', 'idx', array('company_id'));
        $sql = <<<SQL
CREATE TABLE {$this->prefix}company_leads_xref (
        lead_id INT NOT NULL, 
        company_id INT NOT NULL, 
        INDEX {$lead_index} (lead_id), 
        INDEX {$company_index} (company_id), 
        PRIMARY KEY(lead_id, company_id)
        ) 
        DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB
SQL;

        $this->addSql($sql);

        $this->addSql("ALTER TABLE {$this->prefix}lead_fields ADD object VARCHAR(255) DEFAULT 'lead'");

        $sql = <<<SQL
INSERT INTO `{$this->prefix}lead_fields` (`is_published`, `label`, `alias`, `type`, `field_group`, `default_value`, `is_required`, `is_fixed`, `is_visible`, `is_short_visible`, `is_listable`, `is_publicly_updatable`, `is_unique_identifer`, `field_order`, `object`,`properties`) 
VALUES 
 (1, 'name', 'companyname', 'text', 'core', NULL, 1, 0, 1, 1, 1, 0, 0, 18, 'company', 'a:0:{}'),
(1,  'description', 'description', 'textarea', 'professional', NULL, 0, 0, 1, 1, 1, 0, 0, 17, 'company', 'a:0:{}'),
(1, 'Company Number', 'companynumber', 'text', 'professional', NULL, 0, 0, 1, 1, 1, 0, 0, 16, 'company', 'a:0:{}'),
(1,  'Company Source', 'companysource', 'text', 'professional', NULL, 0, 0, 1, 1, 1, 0, 0, 15, 'company', 'a:0:{}'),
(1, 'Industry', 'industry', 'lookup', 'core', NULL, 0, 0, 1, 1, 1, 0, 0, 14, 'company', 'a:1:{s:4:"list";s:55:"Construction|Manufacturing|Wholesale|Finance|Healthcare";}'),
(1, 'Address 1', 'companyaddress1', 'text', 'core', NULL, 0, 0, 1, 1, 1, 0, 0, 13, 'company', 'a:0:{}'),
(1,  'Address 2', 'companyaddress2', 'text', 'core', NULL, 0, 0, 1, 1, 1, 0, 0, 12, 'company', 'a:0:{}'),
(1, 'Email', 'companyemail', 'email', 'core', NULL, 0, 0, 1, 1, 1, 0, 1, 11, 'company', 'a:0:{}'),
(1, 'Phone', 'companyphone', 'tel', 'core', NULL, 0, 0, 1, 1, 1, 0, 0, 10, 'company', 'a:0:{}'),
(1,  'city', 'companycity', 'text', 'core', NULL, 0, 0, 1, 1, 1, 0, 1, 9, 'company', 'a:0:{}'),
(1, 'state', 'companystate', 'text', 'core', NULL, 0, 0, 1, 1, 1, 0, 0, 8, 'company', 'a:0:{}'),
(1, 'zipcode', 'companyzipcode', 'text', 'core', NULL, 0, 0, 1, 1, 1, 0, 0, 7, 'company', 'a:0:{}'),
(1, 'Country', 'companycountry', 'country', 'core', NULL, 0, 0, 1, 1, 1, 0, 1, 6, 'company', 'a:0:{}'),
(1,  'Number of Emplopyees', 'numberoofemployees', 'number', 'professional', NULL, 0, 0, 1, 1, 1, 0, 0, 5, 'company', 'a:2:{s:9:"roundmode";s:1:"3";s:9:"precision";s:1:"0";}'),
(1,  'Fax', 'fax1', 'tel', 'professional', NULL, 0, 0, 1, 1, 1, 0, 0, 4, 'company', 'a:0:{}'),
(1,  'score', 'score', 'number', 'core', NULL, 0, 0, 1, 1, 1, 0, 0, 3, 'company', 'a:2:{s:9:"roundmode";s:1:"3";s:9:"precision";s:1:"0";}'),
(1, 'Annual Revenue', 'annual_revenue', 'number', 'professional', NULL, 0, 0, 1, 1, 1, 0, 1, 2, 'company', 'a:2:{s:9:"roundmode";s:1:"3";s:9:"precision";s:1:"2";}'),
(1, 'Marianela Queme', 'Website', 'companywebsite', 'url', 'core', NULL, 0, 0, 1, 1, 1, 0, 0, 1, 'company', 'a:0:{}');
SQL;

        $this->addSql($sql);
    }
}