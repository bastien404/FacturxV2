<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320111105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, siren VARCHAR(20) DEFAULT NULL, siret VARCHAR(20) DEFAULT NULL, tva VARCHAR(50) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, code_postal VARCHAR(20) DEFAULT NULL, code_pays VARCHAR(2) DEFAULT NULL, telephone VARCHAR(50) DEFAULT NULL, numero_tva VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE facture (id INT AUTO_INCREMENT NOT NULL, fournisseur_id INT DEFAULT NULL, acheteur_id INT DEFAULT NULL, numero_facture VARCHAR(50) NOT NULL, date_facture DATETIME NOT NULL, type_facture VARCHAR(10) NOT NULL, devise VARCHAR(3) NOT NULL, net_apayer NUMERIC(10, 2) NOT NULL, date_echeance DATE DEFAULT NULL, date_livraison DATE DEFAULT NULL, mode_paiement VARCHAR(10) DEFAULT NULL, reference_paiement VARCHAR(100) DEFAULT NULL, taxes JSON DEFAULT NULL, commentaire VARCHAR(255) DEFAULT NULL, charges DOUBLE PRECISION NOT NULL, commande_acheteur VARCHAR(50) DEFAULT NULL, INDEX IDX_FE866410670C757F (fournisseur_id), INDEX IDX_FE86641096A7BB5F (acheteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE facture_allowance_charge (id INT AUTO_INCREMENT NOT NULL, facture_id INT NOT NULL, amount DOUBLE PRECISION NOT NULL, tax_rate DOUBLE PRECISION DEFAULT NULL, is_charge TINYINT(1) NOT NULL, reason VARCHAR(255) DEFAULT NULL, INDEX IDX_AC9F30BB7F2DEE08 (facture_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE facture_ligne (id INT AUTO_INCREMENT NOT NULL, facture_id INT NOT NULL, designation VARCHAR(255) NOT NULL, reference VARCHAR(255) DEFAULT NULL, quantite NUMERIC(10, 2) NOT NULL, unite VARCHAR(20) DEFAULT NULL, prix_unitaire_ht NUMERIC(10, 2) NOT NULL, taux_tva NUMERIC(5, 2) NOT NULL, montant_ht NUMERIC(10, 2) NOT NULL, montant_tva NUMERIC(10, 2) NOT NULL, montant_ttc NUMERIC(10, 2) NOT NULL, INDEX IDX_C5C453347F2DEE08 (facture_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment_means (id INT AUTO_INCREMENT NOT NULL, facture_id INT NOT NULL, code VARCHAR(10) NOT NULL, information VARCHAR(255) DEFAULT NULL, INDEX IDX_8756B2DA7F2DEE08 (facture_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE866410670C757F FOREIGN KEY (fournisseur_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641096A7BB5F FOREIGN KEY (acheteur_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE facture_allowance_charge ADD CONSTRAINT FK_AC9F30BB7F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id)');
        $this->addSql('ALTER TABLE facture_ligne ADD CONSTRAINT FK_C5C453347F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id)');
        $this->addSql('ALTER TABLE payment_means ADD CONSTRAINT FK_8756B2DA7F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE866410670C757F');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE86641096A7BB5F');
        $this->addSql('ALTER TABLE facture_allowance_charge DROP FOREIGN KEY FK_AC9F30BB7F2DEE08');
        $this->addSql('ALTER TABLE facture_ligne DROP FOREIGN KEY FK_C5C453347F2DEE08');
        $this->addSql('ALTER TABLE payment_means DROP FOREIGN KEY FK_8756B2DA7F2DEE08');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE facture');
        $this->addSql('DROP TABLE facture_allowance_charge');
        $this->addSql('DROP TABLE facture_ligne');
        $this->addSql('DROP TABLE payment_means');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
