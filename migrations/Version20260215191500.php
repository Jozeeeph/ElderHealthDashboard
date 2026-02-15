<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215191500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add prescription dose acknowledgment table for patient medication reminders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE prescription_dose_ack (id INT AUTO_INCREMENT NOT NULL, prescription_id INT NOT NULL, scheduled_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', done_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_95DA14A65A868D76 (prescription_id), UNIQUE INDEX uniq_prescription_slot (prescription_id, scheduled_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE prescription_dose_ack ADD CONSTRAINT FK_95DA14A65A868D76 FOREIGN KEY (prescription_id) REFERENCES prescription (id_prescription) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prescription_dose_ack DROP FOREIGN KEY FK_95DA14A65A868D76');
        $this->addSql('DROP TABLE prescription_dose_ack');
    }
}

