<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208133129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, date_commande DATETIME NOT NULL, montant_total NUMERIC(10, 2) NOT NULL, remarques LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commande_equipement (commande_id INT NOT NULL, equipement_id INT NOT NULL, INDEX IDX_2076EA1582EA2E54 (commande_id), INDEX IDX_2076EA15806F0F5C (equipement_id), PRIMARY KEY (commande_id, equipement_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE equipement (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, prix NUMERIC(10, 2) NOT NULL, quantite_disponible INT NOT NULL, statut VARCHAR(255) NOT NULL, image VARCHAR(255) DEFAULT NULL, categorie VARCHAR(255) DEFAULT NULL, date_ajout DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commande_equipement ADD CONSTRAINT FK_2076EA1582EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commande_equipement ADD CONSTRAINT FK_2076EA15806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande_equipement DROP FOREIGN KEY FK_2076EA1582EA2E54');
        $this->addSql('ALTER TABLE commande_equipement DROP FOREIGN KEY FK_2076EA15806F0F5C');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE commande_equipement');
        $this->addSql('DROP TABLE equipement');
    }
}
