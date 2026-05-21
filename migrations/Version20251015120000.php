<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251015120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer_id to order table and foreign key to customer(id)';
    }

    public function up(Schema $schema): void
    {
        // Add customer_id column to order table
        $this->addSql('ALTER TABLE `order` ADD customer_id INT DEFAULT NULL');
        // Create index for FK
        $this->addSql('CREATE INDEX IDX_ORDER_CUSTOMER ON `order` (customer_id)');
        // Add foreign key constraint
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_ORDER_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key and column
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_ORDER_CUSTOMER');
        $this->addSql('DROP INDEX IDX_ORDER_CUSTOMER ON `order`');
        $this->addSql('ALTER TABLE `order` DROP customer_id');
    }
}


