<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251011143358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change image column from BLOB to VARCHAR(255)';
    }

    public function up(Schema $schema): void
    {
        // Drop and recreate the image column correctly
        $this->addSql('ALTER TABLE product DROP COLUMN image');
        $this->addSql('ALTER TABLE product ADD COLUMN image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Revert change if needed (restore old blob column)
        $this->addSql('ALTER TABLE product DROP COLUMN image');
        $this->addSql('ALTER TABLE product ADD COLUMN image LONGBLOB DEFAULT NULL');
    }
}
