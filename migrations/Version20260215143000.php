<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute poids et tension arterielle dans consultation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation ADD poids_kg NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE consultation ADD tension_systolique SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE consultation ADD tension_diastolique SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consultation DROP poids_kg');
        $this->addSql('ALTER TABLE consultation DROP tension_systolique');
        $this->addSql('ALTER TABLE consultation DROP tension_diastolique');
    }
}

