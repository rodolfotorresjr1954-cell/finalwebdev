<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251016072140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Version20251015120000 created FK_ORDER_CUSTOMER; Doctrine diff expects FK_F52993989395C3F3.
        if ($this->foreignKeyExists('FK_ORDER_CUSTOMER')) {
            $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_ORDER_CUSTOMER');
        } elseif ($this->foreignKeyExists('FK_F52993989395C3F3')) {
            $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        }

        if (!$this->foreignKeyExists('FK_F52993989395C3F3')) {
            $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->foreignKeyExists('FK_F52993989395C3F3')) {
            $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        }

        if (!$this->foreignKeyExists('FK_ORDER_CUSTOMER')) {
            $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_ORDER_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customer (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        }
    }

    private function foreignKeyExists(string $constraintName): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            ['order', $constraintName, 'FOREIGN KEY']
        );

        return (int) $count > 0;
    }
}
