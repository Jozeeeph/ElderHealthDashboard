<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE on rendez_vous.type_rendez_vous_id foreign key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0AC72C573E');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0AC72C573E FOREIGN KEY (type_rendez_vous_id) REFERENCES type_rendez_vous (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0AC72C573E');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0AC72C573E FOREIGN KEY (type_rendez_vous_id) REFERENCES type_rendez_vous (id)');
    }
}

