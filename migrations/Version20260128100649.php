<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260128100649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, motif LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, justificatif VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, employe_id INT NOT NULL, INDEX IDX_765AE0C91B65292 (employe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE employe (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, matricule VARCHAR(50) NOT NULL, photo VARCHAR(255) DEFAULT NULL, descripteurs_visage LONGTEXT DEFAULT NULL, qr_code VARCHAR(255) NOT NULL, date_embauche DATETIME NOT NULL, poste VARCHAR(255) NOT NULL, telephone VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F804D3B9E7927C74 (email), UNIQUE INDEX UNIQ_F804D3B912B2DC9C (matricule), UNIQUE INDEX UNIQ_F804D3B97D8B1FB5 (qr_code), UNIQUE INDEX UNIQ_F804D3B9A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE face_encodings (id INT AUTO_INCREMENT NOT NULL, encoding LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, employe_id INT NOT NULL, UNIQUE INDEX UNIQ_5B9D28991B65292 (employe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE parametre (id INT AUTO_INCREMENT NOT NULL, cle VARCHAR(100) NOT NULL, valeur LONGTEXT DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, type VARCHAR(50) DEFAULT NULL, categorie VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pointage (id INT AUTO_INCREMENT NOT NULL, date_heure DATETIME NOT NULL, type VARCHAR(10) NOT NULL, confidence DOUBLE PRECISION DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, device_info VARCHAR(255) DEFAULT NULL, photo_capture VARCHAR(255) DEFAULT NULL, latitude NUMERIC(10, 8) DEFAULT NULL, longitude NUMERIC(11, 8) DEFAULT NULL, created_at DATETIME NOT NULL, employe_id INT NOT NULL, INDEX IDX_7591B201B65292 (employe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) DEFAULT NULL, prenom VARCHAR(255) DEFAULT NULL, telephone VARCHAR(20) DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, last_login DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C91B65292 FOREIGN KEY (employe_id) REFERENCES employe (id)');
        $this->addSql('ALTER TABLE employe ADD CONSTRAINT FK_F804D3B9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE face_encodings ADD CONSTRAINT FK_5B9D28991B65292 FOREIGN KEY (employe_id) REFERENCES employe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B201B65292 FOREIGN KEY (employe_id) REFERENCES employe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C91B65292');
        $this->addSql('ALTER TABLE employe DROP FOREIGN KEY FK_F804D3B9A76ED395');
        $this->addSql('ALTER TABLE face_encodings DROP FOREIGN KEY FK_5B9D28991B65292');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B201B65292');
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE employe');
        $this->addSql('DROP TABLE face_encodings');
        $this->addSql('DROP TABLE parametre');
        $this->addSql('DROP TABLE pointage');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
