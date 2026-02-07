<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207201926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commentaire (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, date DATETIME NOT NULL, status VARCHAR(30) NOT NULL, post_id INT NOT NULL, INDEX IDX_67F068BC4B89032C (post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE consultation (id INT AUTO_INCREMENT NOT NULL, type_consultation VARCHAR(255) NOT NULL, date_consultation DATE NOT NULL, heure_consultation TIME NOT NULL, lieu VARCHAR(255) NOT NULL, etat_consultation VARCHAR(50) NOT NULL, created_role VARCHAR(50) DEFAULT NULL, created_at DATETIME DEFAULT NULL, created_by_id INT DEFAULT NULL, patient_id INT NOT NULL, personnel_medical_id INT NOT NULL, INDEX IDX_964685A6B03A8386 (created_by_id), INDEX IDX_964685A66B899279 (patient_id), INDEX IDX_964685A65E412A67 (personnel_medical_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME DEFAULT NULL, lieu VARCHAR(255) DEFAULT NULL, capacite_max INT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, statut VARCHAR(30) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, type_id INT DEFAULT NULL, INDEX IDX_3BAE0AA7C54C8C93 (type_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE participation (id INT AUTO_INCREMENT NOT NULL, date_inscription DATETIME NOT NULL, statut VARCHAR(30) NOT NULL, presence TINYINT DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, event_id INT DEFAULT NULL, utilisateur_id INT DEFAULT NULL, INDEX IDX_AB55E24F71F7E88B (event_id), INDEX IDX_AB55E24FFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, date_de_creation DATETIME NOT NULL, status VARCHAR(255) NOT NULL, image_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prescription (id_prescription INT AUTO_INCREMENT NOT NULL, medicaments LONGTEXT NOT NULL, frequence VARCHAR(50) NOT NULL, dosage VARCHAR(50) NOT NULL, duree_traitement VARCHAR(50) NOT NULL, consignes LONGTEXT DEFAULT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, consultation_id INT NOT NULL, UNIQUE INDEX UNIQ_1FBFB8D962FF6CDF (consultation_id), PRIMARY KEY (id_prescription)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rapport_medical (id_rapport INT AUTO_INCREMENT NOT NULL, diagnostic LONGTEXT NOT NULL, recommandations LONGTEXT NOT NULL, niveau_gravite VARCHAR(10) NOT NULL, date_rapport DATETIME NOT NULL, consultation_id INT NOT NULL, UNIQUE INDEX UNIQ_C0B673962FF6CDF (consultation_id), PRIMARY KEY (id_rapport)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rendez_vous (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, heure TIME NOT NULL, lieu VARCHAR(255) NOT NULL, patient_id INT DEFAULT NULL, personnel_medical_id INT DEFAULT NULL, type_rendez_vous_id INT DEFAULT NULL, admin_id INT DEFAULT NULL, INDEX IDX_65E8AA0A6B899279 (patient_id), INDEX IDX_65E8AA0A5E412A67 (personnel_medical_id), INDEX IDX_65E8AA0AC72C573E (type_rendez_vous_id), INDEX IDX_65E8AA0A642B8210 (admin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_event (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, couleur VARCHAR(20) DEFAULT NULL, is_active TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE type_rendez_vous (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(200) NOT NULL, tarif DOUBLE PRECISION NOT NULL, duree VARCHAR(200) DEFAULT NULL, admin_id INT DEFAULT NULL, INDEX IDX_2EF17D9B642B8210 (admin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, adresse VARCHAR(255) NOT NULL, age INT NOT NULL, date_naissance DATE NOT NULL, numero_telephone VARCHAR(20) NOT NULL, cin VARCHAR(20) NOT NULL, status VARCHAR(50) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, adresse VARCHAR(255) NOT NULL, age INT NOT NULL, date_naissance DATE NOT NULL, numero_telephone VARCHAR(20) NOT NULL, cin VARCHAR(20) NOT NULL, status VARCHAR(50) NOT NULL, role VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, dossier_medical_path VARCHAR(255) DEFAULT NULL, cv VARCHAR(255) DEFAULT NULL, certification VARCHAR(255) DEFAULT NULL, attestation VARCHAR(255) DEFAULT NULL, hopital_affectation VARCHAR(255) DEFAULT NULL, nb_annee_experience INT DEFAULT NULL, specialite VARCHAR(100) DEFAULT NULL, disponibilite VARCHAR(50) DEFAULT NULL, fonction VARCHAR(100) DEFAULT NULL, patante VARCHAR(100) DEFAULT NULL, numero_fix VARCHAR(20) DEFAULT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC4B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A6B03A8386 FOREIGN KEY (created_by_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A66B899279 FOREIGN KEY (patient_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A65E412A67 FOREIGN KEY (personnel_medical_id) REFERENCES utilisateur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7C54C8C93 FOREIGN KEY (type_id) REFERENCES type_event (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE prescription ADD CONSTRAINT FK_1FBFB8D962FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id)');
        $this->addSql('ALTER TABLE rapport_medical ADD CONSTRAINT FK_C0B673962FF6CDF FOREIGN KEY (consultation_id) REFERENCES consultation (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A6B899279 FOREIGN KEY (patient_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A5E412A67 FOREIGN KEY (personnel_medical_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0AC72C573E FOREIGN KEY (type_rendez_vous_id) REFERENCES type_rendez_vous (id)');
        $this->addSql('ALTER TABLE rendez_vous ADD CONSTRAINT FK_65E8AA0A642B8210 FOREIGN KEY (admin_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE type_rendez_vous ADD CONSTRAINT FK_2EF17D9B642B8210 FOREIGN KEY (admin_id) REFERENCES utilisateur (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC4B89032C');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A6B03A8386');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A66B899279');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A65E412A67');
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7C54C8C93');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F71F7E88B');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FFB88E14F');
        $this->addSql('ALTER TABLE prescription DROP FOREIGN KEY FK_1FBFB8D962FF6CDF');
        $this->addSql('ALTER TABLE rapport_medical DROP FOREIGN KEY FK_C0B673962FF6CDF');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A6B899279');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A5E412A67');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0AC72C573E');
        $this->addSql('ALTER TABLE rendez_vous DROP FOREIGN KEY FK_65E8AA0A642B8210');
        $this->addSql('ALTER TABLE type_rendez_vous DROP FOREIGN KEY FK_2EF17D9B642B8210');
        $this->addSql('DROP TABLE commentaire');
        $this->addSql('DROP TABLE consultation');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE prescription');
        $this->addSql('DROP TABLE rapport_medical');
        $this->addSql('DROP TABLE rendez_vous');
        $this->addSql('DROP TABLE type_event');
        $this->addSql('DROP TABLE type_rendez_vous');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE utilisateur');
    }
}
