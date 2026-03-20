<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251006081236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, siren VARCHAR(20) DEFAULT NULL, siret VARCHAR(20) DEFAULT NULL, tva VARCHAR(50) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, code_postal VARCHAR(20) DEFAULT NULL, code_pays VARCHAR(2) DEFAULT NULL, telephone VARCHAR(50) DEFAULT NULL, numero_tva VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE facture (id INT AUTO_INCREMENT NOT NULL, fournisseur_id INT DEFAULT NULL, acheteur_id INT DEFAULT NULL, commande_acheteur VARCHAR(255) DEFAULT NULL, numero_facture VARCHAR(50) NOT NULL, date_facture DATE NOT NULL, type_facture VARCHAR(10) NOT NULL, devise VARCHAR(3) NOT NULL, net_apayer NUMERIC(10, 2) NOT NULL, date_echeance DATE DEFAULT NULL, date_livraison DATE DEFAULT NULL, mode_paiement VARCHAR(10) DEFAULT NULL, reference_paiement VARCHAR(100) DEFAULT NULL, tva_details JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', remise_pied NUMERIC(10, 2) DEFAULT NULL, charges_pied NUMERIC(10, 2) DEFAULT NULL, reference_contrat VARCHAR(255) DEFAULT NULL, reference_bon_livraison VARCHAR(255) DEFAULT NULL, profil_factur_x VARCHAR(20) NOT NULL, INDEX IDX_FE866410670C757F (fournisseur_id), INDEX IDX_FE86641096A7BB5F (acheteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE facture_ligne (id INT AUTO_INCREMENT NOT NULL, facture_id INT NOT NULL, designation VARCHAR(255) NOT NULL, reference VARCHAR(50) DEFAULT NULL, quantite NUMERIC(10, 2) NOT NULL, unite VARCHAR(20) DEFAULT NULL, prix_unitaire_ht NUMERIC(10, 2) NOT NULL, taux_tva NUMERIC(5, 2) NOT NULL, montant_ht NUMERIC(10, 2) NOT NULL, montant_tva NUMERIC(10, 2) NOT NULL, montant_ttc NUMERIC(10, 2) NOT NULL, numero_ligne INT DEFAULT NULL, INDEX IDX_C5C453347F2DEE08 (facture_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE866410670C757F FOREIGN KEY (fournisseur_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE facture ADD CONSTRAINT FK_FE86641096A7BB5F FOREIGN KEY (acheteur_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE facture_ligne ADD CONSTRAINT FK_C5C453347F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE866410670C757F');
        $this->addSql('ALTER TABLE facture DROP FOREIGN KEY FK_FE86641096A7BB5F');
        $this->addSql('ALTER TABLE facture_ligne DROP FOREIGN KEY FK_C5C453347F2DEE08');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE facture');
        $this->addSql('DROP TABLE facture_ligne');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
