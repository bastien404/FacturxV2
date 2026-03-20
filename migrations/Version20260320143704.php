<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320143704 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client CHANGE siren siren VARCHAR(20) NOT NULL, CHANGE adresse adresse VARCHAR(255) NOT NULL, CHANGE ville ville VARCHAR(100) NOT NULL, CHANGE code_postal code_postal VARCHAR(20) NOT NULL, CHANGE code_pays code_pays VARCHAR(2) NOT NULL');
        $this->addSql('ALTER TABLE facture CHANGE nature_operation nature_operation VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE facture_ligne CHANGE unite unite VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client CHANGE siren siren VARCHAR(20) DEFAULT NULL, CHANGE adresse adresse VARCHAR(255) DEFAULT NULL, CHANGE ville ville VARCHAR(100) DEFAULT NULL, CHANGE code_postal code_postal VARCHAR(20) DEFAULT NULL, CHANGE code_pays code_pays VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE facture_ligne CHANGE unite unite VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE facture CHANGE nature_operation nature_operation VARCHAR(20) DEFAULT NULL');
    }
}
