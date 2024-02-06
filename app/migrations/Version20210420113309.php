<?php

declare(strict_types=1);

/*
 * @copyright   2021 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        https://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20210420113309 extends PreUpAssertionMigration
{
    protected function preUpAssertions(): void
    {
        $this->skipAssertion(function (Schema $schema) {
            return $schema->getTable($this->getTableName())->hasIndex($this->getIndexName());
        }, sprintf('Index %s already exists', $this->getIndexName()));
    }

    public function up(Schema $schema): void
    {
        $this->addSql(sprintf('CREATE INDEX %s ON %s (alias)', $this->getIndexName(), $this->getTableName()));
    }

    private function getTableName(): string
    {
        return "{$this->prefix}lead_lists";
    }

    private function getIndexName(): string
    {
        return "{$this->prefix}lead_list_alias";
    }
}
