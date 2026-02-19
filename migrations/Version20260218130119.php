<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218130119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planning ADD nom VARCHAR(255) DEFAULT NULL, ADD pause_debut TIME DEFAULT NULL, ADD pause_fin TIME DEFAULT NULL, ADD created_at VARCHAR(255) NOT NULL, ADD updated_at VARCHAR(255) DEFAULT NULL, CHANGE heure_debut heure_debut TIME NOT NULL, CHANGE heure_fin heure_fin TIME NOT NULL, CHANGE pause_obligatoire pause_obligatoire TINYINT NOT NULL, CHANGE duree_pause_min duree_pause_min INT DEFAULT NULL, CHANGE actif actif TINYINT NOT NULL');
        $this->addSql('ALTER TABLE pointage ADD est_heure_supp TINYINT DEFAULT NULL, ADD est_en_pause TINYINT DEFAULT NULL, ADD pause_debut VARCHAR(255) DEFAULT NULL, ADD pause_fin VARCHAR(255) DEFAULT NULL, ADD planning_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pointage ADD CONSTRAINT FK_7591B203D865311 FOREIGN KEY (planning_id) REFERENCES planning (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7591B203D865311 ON pointage (planning_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planning DROP nom, DROP pause_debut, DROP pause_fin, DROP created_at, DROP updated_at, CHANGE heure_debut heure_debut TIME DEFAULT NULL, CHANGE heure_fin heure_fin TIME DEFAULT NULL, CHANGE pause_obligatoire pause_obligatoire TINYINT DEFAULT 1, CHANGE duree_pause_min duree_pause_min INT DEFAULT 60, CHANGE actif actif TINYINT DEFAULT 1');
        $this->addSql('ALTER TABLE pointage DROP FOREIGN KEY FK_7591B203D865311');
        $this->addSql('DROP INDEX IDX_7591B203D865311 ON pointage');
        $this->addSql('ALTER TABLE pointage DROP est_heure_supp, DROP est_en_pause, DROP pause_debut, DROP pause_fin, DROP planning_id');
    }
}
