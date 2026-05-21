<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop legacy stock table and align order customer index naming.
 */
final class Version20260521073113 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop stock table and rename order customer index to Doctrine naming';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('stock')) {
            $this->addSql('DROP TABLE stock');
        }

        if ($this->foreignKeyExists('order', 'FK_ORDER_CUSTOMER')) {
            $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_ORDER_CUSTOMER');
        }

        if ($this->indexExists('order', 'idx_order_customer') && !$this->indexExists('order', 'IDX_F52993989395C3F3')) {
            $this->addSql('ALTER TABLE `order` RENAME INDEX idx_order_customer TO IDX_F52993989395C3F3');
        }

        if ($this->columnExists('product', 'stock')) {
            $this->addSql('ALTER TABLE product CHANGE stock stock INT DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('stock')) {
            $this->addSql('CREATE TABLE stock (id INT AUTO_INCREMENT NOT NULL, item_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, quantity INT NOT NULL, unit VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        }

        if ($this->indexExists('order', 'IDX_F52993989395C3F3') && !$this->indexExists('order', 'idx_order_customer')) {
            $this->addSql('ALTER TABLE `order` RENAME INDEX IDX_F52993989395C3F3 TO idx_order_customer');
        }

        if ($this->columnExists('product', 'stock')) {
            $this->addSql('ALTER TABLE product CHANGE stock stock INT NOT NULL');
        }
    }

    private function tableExists(string $tableName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?',
            [$tableName]
        );

        return (int) $count > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$tableName, $columnName]
        );

        return (int) $count > 0;
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?',
            [$tableName, $indexName]
        );

        return (int) $count > 0;
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            [$tableName, $constraintName, 'FOREIGN KEY']
        );

        return (int) $count > 0;
    }
}
