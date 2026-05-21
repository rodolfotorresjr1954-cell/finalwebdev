<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211062144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, action VARCHAR(50) NOT NULL, entity_type VARCHAR(255) DEFAULT NULL, entity_id INT DEFAULT NULL, affected_data LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_user_id (user_id), INDEX idx_action (action), INDEX idx_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_logs ADD CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        
        // Add columns with default values first
        $this->addSql('ALTER TABLE user ADD email VARCHAR(255) DEFAULT NULL, ADD created_at DATETIME DEFAULT NULL, ADD is_active TINYINT(1) DEFAULT 1');
        
        // Update existing users with default values
        $this->addSql("UPDATE user SET email = CONCAT(username, '@example.com'), created_at = NOW(), is_active = 1 WHERE email IS NULL");
        
        // Now make them NOT NULL
        $this->addSql('ALTER TABLE user MODIFY email VARCHAR(255) NOT NULL, MODIFY created_at DATETIME NOT NULL, MODIFY is_active TINYINT(1) NOT NULL');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON user (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_logs DROP FOREIGN KEY FK_F34B1DCEA76ED395');
        $this->addSql('DROP TABLE activity_logs');
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_EMAIL ON user');
        $this->addSql('ALTER TABLE user DROP email, DROP created_at, DROP is_active');
    }
}
