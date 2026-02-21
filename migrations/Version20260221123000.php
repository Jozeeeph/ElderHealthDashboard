<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rich text notes field to consultation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation ADD notes_consultation LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation DROP notes_consultation');
    }
}
