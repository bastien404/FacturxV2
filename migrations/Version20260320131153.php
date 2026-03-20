<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320131153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture ADD nature_operation VARCHAR(20) DEFAULT NULL, ADD tva_debits TINYINT(1) DEFAULT 0 NOT NULL, ADD livraison_adresse VARCHAR(255) DEFAULT NULL, ADD livraison_ville VARCHAR(100) DEFAULT NULL, ADD livraison_code_postal VARCHAR(20) DEFAULT NULL, ADD livraison_code_pays VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE facture_ligne ADD categorie_tva VARCHAR(5) DEFAULT \'S\' NOT NULL, ADD motif_exoneration VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment_means ADD iban VARCHAR(34) DEFAULT NULL, ADD bic VARCHAR(11) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture_ligne DROP categorie_tva, DROP motif_exoneration');
        $this->addSql('ALTER TABLE facture DROP nature_operation, DROP tva_debits, DROP livraison_adresse, DROP livraison_ville, DROP livraison_code_postal, DROP livraison_code_pays');
        $this->addSql('ALTER TABLE payment_means DROP iban, DROP bic');
    }
}
