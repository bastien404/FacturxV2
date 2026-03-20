<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251003130625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE86641019EB6921');
        $this->addSql('DROP INDEX IDX_FE86641019EB6921 ON facture');
        $this->addSql('ALTER TABLE facture DROP client_id, DROP nom_fournisseur, DROP siren_fournisseur, DROP siret_fournisseur, DROP tva_fournisseur, DROP code_pays_fournisseur, DROP email_fournisseur, DROP adresse_fournisseur, DROP ville_fournisseur, DROP code_postal_fournisseur, DROP total_ht, DROP total_tva, DROP total_ttc');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture ADD client_id INT NOT NULL, ADD nom_fournisseur VARCHAR(255) NOT NULL, ADD siren_fournisseur VARCHAR(20) NOT NULL, ADD siret_fournisseur VARCHAR(20) DEFAULT NULL, ADD tva_fournisseur VARCHAR(20) DEFAULT NULL, ADD code_pays_fournisseur VARCHAR(2) NOT NULL, ADD email_fournisseur VARCHAR(255) NOT NULL, ADD adresse_fournisseur VARCHAR(255) NOT NULL, ADD ville_fournisseur VARCHAR(255) NOT NULL, ADD code_postal_fournisseur VARCHAR(255) NOT NULL, ADD total_ht NUMERIC(10, 2) NOT NULL, ADD total_tva NUMERIC(10, 2) NOT NULL, ADD total_ttc NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641019EB6921 FOREIGN KEY (client_id) REFERENCES client (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_FE86641019EB6921 ON facture (client_id)');
    }
}
