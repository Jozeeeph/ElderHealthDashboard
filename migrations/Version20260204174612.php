<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260204174612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE personnel_medical (cv VARCHAR(255) DEFAULT NULL, certification VARCHAR(255) DEFAULT NULL, attestation VARCHAR(255) DEFAULT NULL, hopital_affectation VARCHAR(255) NOT NULL, nb_annee_experience INT NOT NULL, specialite VARCHAR(100) NOT NULL, disponibilite VARCHAR(50) NOT NULL, fonction VARCHAR(100) NOT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE personnel_medical ADD CONSTRAINT FK_D7FD1B75BF396750 FOREIGN KEY (id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE utilisateur ADD type VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE personnel_medical DROP FOREIGN KEY FK_D7FD1B75BF396750');
        $this->addSql('DROP TABLE personnel_medical');
        $this->addSql('ALTER TABLE utilisateur DROP type');
    }
}
