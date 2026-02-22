<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218110242 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE prescription_dose_ack (id INT AUTO_INCREMENT NOT NULL, scheduled_at DATETIME NOT NULL, done_at DATETIME NOT NULL, prescription_id INT NOT NULL, INDEX IDX_6918043693DB413D (prescription_id), UNIQUE INDEX uniq_prescription_slot (prescription_id, scheduled_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE prescription_dose_ack ADD CONSTRAINT FK_6918043693DB413D FOREIGN KEY (prescription_id) REFERENCES prescription (id_prescription) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commentaire ADD is_ai TINYINT NOT NULL');
        $this->addSql('ALTER TABLE consultation ADD poids_kg NUMERIC(5, 2) DEFAULT NULL, ADD tension_systolique SMALLINT DEFAULT NULL, ADD tension_diastolique SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD reminder_sent TINYINT NOT NULL');
        $this->addSql('ALTER TABLE rendez_vous ADD is_paid TINYINT DEFAULT 0 NOT NULL, ADD paid_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE prescription_dose_ack DROP FOREIGN KEY FK_6918043693DB413D');
        $this->addSql('DROP TABLE prescription_dose_ack');
        $this->addSql('ALTER TABLE commentaire DROP is_ai');
        $this->addSql('ALTER TABLE consultation DROP poids_kg, DROP tension_systolique, DROP tension_diastolique');
        $this->addSql('ALTER TABLE event DROP reminder_sent');
        $this->addSql('ALTER TABLE rendez_vous DROP is_paid, DROP paid_at');
    }
}
