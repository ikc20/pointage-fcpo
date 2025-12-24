<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251224095232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE absence (
              id INT AUTO_INCREMENT NOT NULL,
              type VARCHAR(50) NOT NULL,
              date_debut DATE NOT NULL,
              date_fin DATE NOT NULL,
              motif LONGTEXT DEFAULT NULL,
              statut VARCHAR(20) NOT NULL,
              justificatif VARCHAR(255) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              employe_id INT NOT NULL,
              INDEX IDX_765AE0C91B65292 (employe_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE parametre (
              id INT AUTO_INCREMENT NOT NULL,
              cle VARCHAR(100) NOT NULL,
              valeur LONGTEXT DEFAULT NULL,
              description VARCHAR(255) DEFAULT NULL,
              type VARCHAR(50) DEFAULT NULL,
              categorie VARCHAR(100) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user (
              id INT AUTO_INCREMENT NOT NULL,
              email VARCHAR(180) NOT NULL,
              roles JSON NOT NULL,
              password VARCHAR(255) NOT NULL,
              nom VARCHAR(255) DEFAULT NULL,
              prenom VARCHAR(255) DEFAULT NULL,
              telephone VARCHAR(20) DEFAULT NULL,
              is_active TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              last_login DATETIME DEFAULT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              absence
            ADD
              CONSTRAINT FK_765AE0C91B65292 FOREIGN KEY (employe_id) REFERENCES employe (id)
        SQL);
        $this->addSql('ALTER TABLE employe ADD user_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              employe
            ADD
              CONSTRAINT FK_F804D3B9A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F804D3B9A76ED395 ON employe (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C91B65292');
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE parametre');
        $this->addSql('DROP TABLE user');
        $this->addSql('ALTER TABLE employe DROP FOREIGN KEY FK_F804D3B9A76ED395');
        $this->addSql('DROP INDEX UNIQ_F804D3B9A76ED395 ON employe');
        $this->addSql('ALTER TABLE employe DROP user_id');
    }
}
