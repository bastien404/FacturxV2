<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251003125628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client ADD numero_tva VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE facture ADD fournisseur_id INT DEFAULT NULL, ADD acheteur_id INT DEFAULT NULL, ADD commande_acheteur VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE866410670C757F FOREIGN KEY (fournisseur_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641096A7BB5F FOREIGN KEY (acheteur_id) REFERENCES client (id)');
        $this->addSql('CREATE INDEX IDX_FE866410670C757F ON facture (fournisseur_id)');
        $this->addSql('CREATE INDEX IDX_FE86641096A7BB5F ON facture (acheteur_id)');
        $this->addSql('ALTER TABLE facture_ligne ADD numero_ligne INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client DROP numero_tva');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE866410670C757F');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE86641096A7BB5F');
        $this->addSql('DROP INDEX IDX_FE866410670C757F ON facture');
        $this->addSql('DROP INDEX IDX_FE86641096A7BB5F ON facture');
        $this->addSql('ALTER TABLE facture DROP fournisseur_id, DROP acheteur_id, DROP commande_acheteur');
        $this->addSql('ALTER TABLE facture_ligne DROP numero_ligne');
    }
}
