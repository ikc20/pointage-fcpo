<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251222133552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE employe (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, matricule VARCHAR(50) NOT NULL, photo VARCHAR(255) DEFAULT NULL, descripteurs_visage LONGTEXT DEFAULT NULL, qr_code VARCHAR(255) NOT NULL, date_embauche DATETIME NOT NULL, poste VARCHAR(255) NOT NULL, telephone VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_F804D3B9E7927C74 (email), UNIQUE INDEX UNIQ_F804D3B912B2DC9C (matricule), UNIQUE INDEX UNIQ_F804D3B97D8B1FB5 (qr_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pointage (id INT AUTO_INCREMENT NOT NULL, date_heure DATETIME NOT NULL, type VARCHAR(10) NOT NULL, confidence DOUBLE PRECISION DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, device_info VARCHAR(255) DEFAULT NULL, photo_capture VARCHAR(255) DEFAULT NULL, latitude NUMERIC(10, 8) DEFAULT NULL, longitude NUMERIC(11, 8) DEFAULT NULL, created_at DATETIME NOT NULL, employe_id INT NOT NULL, INDEX IDX_7591B201B65292 (employe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B201B65292 FOREIGN KEY (employe_id) REFERENCES employe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B201B65292');
        $this->addSql('DROP TABLE employe');
        $this->addSql('DROP TABLE pointage');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
