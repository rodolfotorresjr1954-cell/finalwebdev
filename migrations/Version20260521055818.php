<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Syncs full schema; safe on DBs where tables were created by earlier migrations.
 */
final class Version20260521055818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure core tables and foreign keys exist (idempotent for existing databases)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('activity_logs')) {
            $this->addSql('CREATE TABLE activity_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, username VARCHAR(180) NOT NULL, role VARCHAR(50) NOT NULL, action VARCHAR(50) NOT NULL, entity_type VARCHAR(255) DEFAULT NULL, entity_id INT DEFAULT NULL, affected_data LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_user_id (user_id), INDEX idx_action (action), INDEX idx_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->tableExists('category')) {
            $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->tableExists('customer')) {
            $this->addSql('CREATE TABLE customer (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(20) DEFAULT NULL, create_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_81398E09B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->tableExists('order')) {
            $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, customer_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, create_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(50) NOT NULL, total DOUBLE PRECISION NOT NULL, payment_method VARCHAR(20) DEFAULT NULL, INDEX IDX_F52993989395C3F3 (customer_id), INDEX IDX_F5299398B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->tableExists('order_product')) {
            $this->addSql('CREATE TABLE order_product (order_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_2530ADE68D9F6D38 (order_id), INDEX IDX_2530ADE64584665A (product_id), PRIMARY KEY(order_id, product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->tableExists('product')) {
            $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, category_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, price DOUBLE PRECISION NOT NULL, datetime DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', image VARCHAR(255) NOT NULL, INDEX IDX_D34A04AD12469DE2 (category_id), INDEX IDX_D34A04ADB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->tableExists('user')) {
            $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, is_verified TINYINT(1) NOT NULL, verification_token VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, is_active TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!$this->tableExists('messenger_messages')) {
            $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$this->foreignKeyExists('activity_logs', 'FK_F34B1DCEA76ED395')) {
            $this->addSql('ALTER TABLE activity_logs ADD CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        }
        if (!$this->foreignKeyExists('customer', 'FK_81398E09B03A8386')) {
            $this->addSql('ALTER TABLE customer ADD CONSTRAINT FK_81398E09B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        }
        if (!$this->foreignKeyExists('order', 'FK_F52993989395C3F3')) {
            $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) ON DELETE SET NULL');
        }
        if (!$this->foreignKeyExists('order', 'FK_F5299398B03A8386')) {
            $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        }
        if (!$this->foreignKeyExists('order_product', 'FK_2530ADE68D9F6D38')) {
            $this->addSql('ALTER TABLE order_product ADD CONSTRAINT FK_2530ADE68D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('order_product', 'FK_2530ADE64584665A')) {
            $this->addSql('ALTER TABLE order_product ADD CONSTRAINT FK_2530ADE64584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        }
        if (!$this->foreignKeyExists('product', 'FK_D34A04AD12469DE2')) {
            $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        }
        if (!$this->foreignKeyExists('product', 'FK_D34A04ADB03A8386')) {
            $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04ADB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->foreignKeyExists('activity_logs', 'FK_F34B1DCEA76ED395')) {
            $this->addSql('ALTER TABLE activity_logs DROP FOREIGN KEY FK_F34B1DCEA76ED395');
        }
        if ($this->foreignKeyExists('customer', 'FK_81398E09B03A8386')) {
            $this->addSql('ALTER TABLE customer DROP FOREIGN KEY FK_81398E09B03A8386');
        }
        if ($this->foreignKeyExists('order', 'FK_F52993989395C3F3')) {
            $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        }
        if ($this->foreignKeyExists('order', 'FK_F5299398B03A8386')) {
            $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398B03A8386');
        }
        if ($this->foreignKeyExists('order_product', 'FK_2530ADE68D9F6D38')) {
            $this->addSql('ALTER TABLE order_product DROP FOREIGN KEY FK_2530ADE68D9F6D38');
        }
        if ($this->foreignKeyExists('order_product', 'FK_2530ADE64584665A')) {
            $this->addSql('ALTER TABLE order_product DROP FOREIGN KEY FK_2530ADE64584665A');
        }
        if ($this->foreignKeyExists('product', 'FK_D34A04AD12469DE2')) {
            $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        }
        if ($this->foreignKeyExists('product', 'FK_D34A04ADB03A8386')) {
            $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADB03A8386');
        }

        if ($this->tableExists('activity_logs')) {
            $this->addSql('DROP TABLE activity_logs');
        }
        if ($this->tableExists('category')) {
            $this->addSql('DROP TABLE category');
        }
        if ($this->tableExists('customer')) {
            $this->addSql('DROP TABLE customer');
        }
        if ($this->tableExists('order')) {
            $this->addSql('DROP TABLE `order`');
        }
        if ($this->tableExists('order_product')) {
            $this->addSql('DROP TABLE order_product');
        }
        if ($this->tableExists('product')) {
            $this->addSql('DROP TABLE product');
        }
        if ($this->tableExists('user')) {
            $this->addSql('DROP TABLE user');
        }
        if ($this->tableExists('messenger_messages')) {
            $this->addSql('DROP TABLE messenger_messages');
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
