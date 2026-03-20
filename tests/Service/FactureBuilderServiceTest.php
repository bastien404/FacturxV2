<?php

namespace App\Tests\Service;

use App\Service\FactureBuilderService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour FactureBuilderService.
 *
 * Vérifie la construction d'entités Facture en mémoire à partir de données
 * brutes (tableaux PHP), la validation métier, et le calcul des montants.
 */
class FactureBuilderServiceTest extends TestCase
{
    private FactureBuilderService $service;

    protected function setUp(): void
    {
        $this->service = new FactureBuilderService();
    }

    /**
     * Données de facture valides simulant un JSON reçu par l'API.
     */
    private function getValidData(): array
    {
        return [
            'numeroFacture' => 'F2025-001',
            'dateFacture' => '2025-10-07',
            'devise' => 'EUR',
            'typeFacture' => '380',
            'commentaire' => 'Merci de votre confiance.',
            'dateEcheance' => '2025-11-06',
            'dateLivraison' => '2025-10-08',
            'commandeAcheteur' => 'PO-45678',
            'fournisseur' => [
                'nom' => 'Oroya SARL',
                'siren' => '123456789',
                'siret' => '12345678900025',
                'numeroTva' => 'FR00123456789',
                'adresse' => '5 rue de la République',
                'ville' => 'Tours',
                'codePostal' => '37000',
                'codePays' => 'FR',
                'email' => 'contact@oroya.fr',
            ],
            'acheteur' => [
                'nom' => 'Société ClientTest',
                'adresse' => '27 avenue du Général',
                'ville' => 'Poitiers',
                'codePostal' => '86000',
                'codePays' => 'FR',
            ],
            'lignes' => [
                [
                    'designation' => 'Licence Logiciel Pro',
                    'quantite' => 2,
                    'prixUnitaireHT' => 500.00,
                    'tauxTVA' => 20.00,
                    'unite' => 'H87',
                ],
                [
                    'designation' => 'Prestation installation',
                    'quantite' => 1,
                    'prixUnitaireHT' => 200.00,
                    'tauxTVA' => 10.00,
                ],
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // buildFromArray() — construction d'entités
    // ──────────────────────────────────────────────────────────────

    public function testBuildFromArrayCreatesFacture(): void
    {
        $facture = $this->service->buildFromArray($this->getValidData());

        $this->assertEquals('F2025-001', $facture->getNumeroFacture());
        $this->assertEquals('EUR', $facture->getDevise());
        $this->assertEquals('380', $facture->getTypeFacture());
        $this->assertCount(2, $facture->getLignes());
    }

    public function testBuildFromArrayCalculatesLineAmounts(): void
    {
        $facture = $this->service->buildFromArray($this->getValidData());
        $lignes = $facture->getLignes()->toArray();

        // Ligne 1 : 2 x 500 = 1000 HT, TVA 200, TTC 1200
        $this->assertEquals(1000.00, $lignes[0]->getMontantHt());
        $this->assertEquals(200.00, $lignes[0]->getMontantTva());
        $this->assertEquals(1200.00, $lignes[0]->getMontantTtc());

        // Ligne 2 : 1 x 200 = 200 HT, TVA 20, TTC 220
        $this->assertEquals(200.00, $lignes[1]->getMontantHt());
        $this->assertEquals(20.00, $lignes[1]->getMontantTva());
        $this->assertEquals(220.00, $lignes[1]->getMontantTtc());
    }

    public function testBuildFromArrayCalculatesNetAPayer(): void
    {
        $facture = $this->service->buildFromArray($this->getValidData());

        // Net à payer = somme TTC = 1200 + 220 = 1420
        $this->assertEquals(1420.00, $facture->getNetApayer());
    }

    public function testBuildFromArraySetsSupplier(): void
    {
        $facture = $this->service->buildFromArray($this->getValidData());

        $fournisseur = $facture->getFournisseur();
        $this->assertEquals('Oroya SARL', $fournisseur->getNom());
        $this->assertEquals('12345678900025', $fournisseur->getSiret());
        $this->assertEquals('FR00123456789', $fournisseur->getNumeroTva());
        $this->assertEquals('contact@oroya.fr', $fournisseur->getEmail());
    }

    public function testBuildFromArraySetsBuyer(): void
    {
        $facture = $this->service->buildFromArray($this->getValidData());

        $acheteur = $facture->getAcheteur();
        $this->assertEquals('Société ClientTest', $acheteur->getNom());
        $this->assertEquals('Poitiers', $acheteur->getVille());
        $this->assertEquals('FR', $acheteur->getCodePays());
    }

    public function testBuildFromArraySetsOptionalDates(): void
    {
        $facture = $this->service->buildFromArray($this->getValidData());

        $this->assertEquals('2025-11-06', $facture->getDateEcheance()->format('Y-m-d'));
        $this->assertEquals('2025-10-08', $facture->getDateLivraison()->format('Y-m-d'));
    }

    public function testBuildFromArraySetsDefaultValues(): void
    {
        $data = $this->getValidData();
        unset($data['devise']);
        unset($data['typeFacture']);

        $facture = $this->service->buildFromArray($data);

        $this->assertEquals('EUR', $facture->getDevise());
        $this->assertEquals('FA', $facture->getTypeFacture());
    }

    public function testBuildFromArrayWithAllowances(): void
    {
        $data = $this->getValidData();
        $data['allowances'] = [
            [
                'amount' => 50,
                'taxRate' => 20,
                'isCharge' => false,
                'reason' => 'Remise fidélité',
            ],
            [
                'amount' => 30,
                'taxRate' => 20,
                'isCharge' => true,
                'reason' => 'Frais de port',
            ],
        ];

        $facture = $this->service->buildFromArray($data);

        $this->assertCount(2, $facture->getAllowanceCharges());

        $items = $facture->getAllowanceCharges()->toArray();
        $this->assertEquals(50.0, $items[0]->getAmount());
        $this->assertFalse($items[0]->getIsCharge());
        $this->assertEquals(30.0, $items[1]->getAmount());
        $this->assertTrue($items[1]->getIsCharge());
    }

    public function testBuildFromArrayWithPaymentMeans(): void
    {
        $data = $this->getValidData();
        $data['paymentMeans'] = [
            ['code' => '58', 'information' => 'FR7612345678901234567890123'],
            ['code' => '30', 'information' => 'Chèque'],
        ];

        $facture = $this->service->buildFromArray($data);

        $this->assertCount(2, $facture->getPaymentMeans());
        $means = $facture->getPaymentMeans()->toArray();
        $this->assertEquals('58', $means[0]->getCode());
        $this->assertEquals('30', $means[1]->getCode());
    }

    // ──────────────────────────────────────────────────────────────
    // validate() — validation métier
    // ──────────────────────────────────────────────────────────────

    public function testValidatePassesWithValidData(): void
    {
        // Ne doit pas lancer d'exception
        $this->service->validate($this->getValidData());
        $this->assertTrue(true);
    }

    public function testValidateThrowsOnMissingInvoiceNumber(): void
    {
        $data = $this->getValidData();
        unset($data['numeroFacture']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Numéro de facture manquant');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnMissingDate(): void
    {
        $data = $this->getValidData();
        unset($data['dateFacture']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Date de facture manquante');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnMissingSupplier(): void
    {
        $data = $this->getValidData();
        unset($data['fournisseur']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fournisseur');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnMissingBuyer(): void
    {
        $data = $this->getValidData();
        unset($data['acheteur']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('acheteur');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnEmptyLines(): void
    {
        $data = $this->getValidData();
        $data['lignes'] = [];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ligne');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnMissingSupplierName(): void
    {
        $data = $this->getValidData();
        unset($data['fournisseur']['nom']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nom du fournisseur');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnMissingSupplierIdentification(): void
    {
        $data = $this->getValidData();
        unset($data['fournisseur']['numeroTva']);
        unset($data['fournisseur']['siren']);
        unset($data['fournisseur']['siret']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BR-CO-26');
        $this->service->validate($data);
    }

    public function testValidateAcceptsSupplierWithOnlySiren(): void
    {
        $data = $this->getValidData();
        unset($data['fournisseur']['numeroTva']);
        unset($data['fournisseur']['siret']);
        // Seul le SIREN est renseigné → doit passer

        $this->service->validate($data);
        $this->assertTrue(true);
    }

    public function testValidateThrowsOnMissingBuyerName(): void
    {
        $data = $this->getValidData();
        unset($data['acheteur']['nom']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('acheteur');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnMissingLineDesignation(): void
    {
        $data = $this->getValidData();
        unset($data['lignes'][0]['designation']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('désignation manquante');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnZeroQuantity(): void
    {
        $data = $this->getValidData();
        $data['lignes'][0]['quantite'] = 0;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('quantité invalide');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnMissingPrice(): void
    {
        $data = $this->getValidData();
        unset($data['lignes'][0]['prixUnitaireHT']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('prix unitaire HT');
        $this->service->validate($data);
    }

    public function testValidateThrowsOnMissingTaxRate(): void
    {
        $data = $this->getValidData();
        unset($data['lignes'][0]['tauxTVA']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('taux TVA');
        $this->service->validate($data);
    }

    public function testValidateCollectsMultipleErrors(): void
    {
        $data = $this->getValidData();
        unset($data['numeroFacture']);
        unset($data['dateFacture']);

        try {
            $this->service->validate($data);
            $this->fail('Should have thrown InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            // Les deux erreurs doivent être présentes (séparées par |)
            $this->assertStringContainsString('Numéro de facture', $e->getMessage());
            $this->assertStringContainsString('Date de facture', $e->getMessage());
            $this->assertStringContainsString('|', $e->getMessage());
        }
    }

    public function testBuildFromArrayPaymentMeansRequiresCode(): void
    {
        $data = $this->getValidData();
        $data['paymentMeans'] = [
            ['information' => 'FR7612345678901234567890123'],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('code');
        $this->service->buildFromArray($data);
    }

    // ──────────────────────────────────────────────────────────────
    // Intégration : buildFromArray → FacturxService::buildXml
    // ──────────────────────────────────────────────────────────────

    public function testBuiltFactureIsCompatibleWithFacturxService(): void
    {
        $facture = $this->service->buildFromArray($this->getValidData());

        // Vérifie que l'entité construite a tout ce dont FacturxService a besoin
        $this->assertNotNull($facture->getFournisseur());
        $this->assertNotNull($facture->getAcheteur());
        $this->assertNotNull($facture->getNumeroFacture());
        $this->assertNotNull($facture->getDateFacture());
        $this->assertNotNull($facture->getDevise());
        $this->assertGreaterThan(0, $facture->getLignes()->count());

        foreach ($facture->getLignes() as $ligne) {
            $this->assertNotEmpty($ligne->getDesignation());
            $this->assertGreaterThan(0, $ligne->getQuantite());
            $this->assertGreaterThanOrEqual(0, $ligne->getMontantHt());
        }
    }
}
