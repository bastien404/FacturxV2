<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\FactureAllowanceCharge;
use App\Entity\FactureLigne;
use App\Entity\PaymentMeans;
use App\Service\FacturxService;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

/**
 * Tests unitaires et d'intégration pour FacturxService.
 *
 * Scénario principal : des données fictives (simulant la BDD) sont injectées
 * dans le service pour générer un XML EN16931, puis l'embarquer dans un PDF
 * fourni par l'utilisateur.
 */
class FacturxServiceTest extends TestCase
{
    private FacturxService $service;
    private string $tmpDir;

    protected function setUp(): void
    {
        $twig = $this->createMock(Environment::class);
        $this->tmpDir = sys_get_temp_dir() . '/facturx_test_' . uniqid();
        mkdir($this->tmpDir);
        $this->service = new FacturxService($twig, $this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Crée une facture complète simulant des données issues de la BDD,
     * identique aux fixtures du projet (Oroya SARL → ClientTest).
     */
    private function createFixtureFacture(): Facture
    {
        $fournisseur = (new Client())
            ->setNom('Oroya SARL')
            ->setSiren('123456789')
            ->setSiret('12345678900025')
            ->setNumeroTva('FR00123456789')
            ->setAdresse('5 rue de la République')
            ->setVille('Tours')
            ->setCodePostal('37000')
            ->setCodePays('FR')
            ->setEmail('contact@oroya.fr');

        $acheteur = (new Client())
            ->setNom('Société ClientTest')
            ->setSiren('987654321')
            ->setNumeroTva('FR00987654321')
            ->setAdresse('27 avenue du Général')
            ->setVille('Poitiers')
            ->setCodePostal('86000')
            ->setCodePays('FR')
            ->setEmail('client@demo.fr');

        $facture = new Facture();
        $facture
            ->setNumeroFacture('F2025-001')
            ->setDateFacture(new \DateTime('2025-10-07'))
            ->setDevise('EUR')
            ->setTypeFacture('380')
            ->setNetApayer(1420.00)
            ->setFournisseur($fournisseur)
            ->setAcheteur($acheteur)
            ->setCommentaire('Merci de votre confiance.')
            ->setDateEcheance(new \DateTime('2025-11-06'))
            ->setDateLivraison(new \DateTime('2025-10-08'))
            ->setCommandeAcheteur('PO-45678');

        // Ligne 1 : 2 x 500 EUR @ 20% TVA
        $ligne1 = (new FactureLigne())
            ->setDesignation('Licence Logiciel Pro')
            ->setReference('LIC-2025')
            ->setQuantite(2)
            ->setUnite('H87')
            ->setPrixUnitaireHt(500.00)
            ->setTauxTva(20.00)
            ->setMontantHt(1000.00)
            ->setMontantTva(200.00)
            ->setMontantTtc(1200.00);
        $facture->addLigne($ligne1);

        // Ligne 2 : 1 x 200 EUR @ 10% TVA
        $ligne2 = (new FactureLigne())
            ->setDesignation('Prestation installation')
            ->setReference('INST-01')
            ->setQuantite(1)
            ->setUnite('H87')
            ->setPrixUnitaireHt(200.00)
            ->setTauxTva(10.00)
            ->setMontantHt(200.00)
            ->setMontantTva(20.00)
            ->setMontantTtc(220.00);
        $facture->addLigne($ligne2);

        return $facture;
    }

    // ──────────────────────────────────────────────────────────────
    // buildXml() — génération XML
    // ──────────────────────────────────────────────────────────────

    public function testBuildXmlGeneratesFile(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);

        $this->assertFileExists($xmlFile);
        $this->assertStringContainsString('facture_F2025-001_fx.xml', $xmlFile);
    }

    public function testBuildXmlIsValidXml(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);

        $dom = new \DOMDocument();
        $loaded = $dom->load($xmlFile);
        $this->assertTrue($loaded, 'Le fichier XML doit être un document XML valide');
    }

    public function testBuildXmlContainsEN16931Guideline(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('CrossIndustryInvoice', $xml);
        $this->assertStringContainsString('urn:cen.eu:en16931:2017', $xml);
    }

    public function testBuildXmlDocumentHeader(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('<ram:ID>F2025-001</ram:ID>', $xml);
        $this->assertStringContainsString('<ram:TypeCode>380</ram:TypeCode>', $xml);
        // Date au format 102 (YYYYMMDD)
        $this->assertStringContainsString('20251007', $xml);
        $this->assertStringContainsString('Merci de votre confiance.', $xml);
    }

    public function testBuildXmlTypeCodeMapsInternalCodes(): void
    {
        $facture = $this->createFixtureFacture();
        // Simule le type interne 'FA' tel que stocké en BDD
        $facture->setTypeFacture('FA');

        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        // Doit mapper FA → 380 (code UNTDID 1001)
        $this->assertStringContainsString('<ram:TypeCode>380</ram:TypeCode>', $xml);
        $this->assertStringNotContainsString('<ram:TypeCode>FA</ram:TypeCode>', $xml);
    }

    public function testBuildXmlTypeCodeCreditNote(): void
    {
        $facture = $this->createFixtureFacture();
        $facture->setTypeFacture('FC');

        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('<ram:TypeCode>381</ram:TypeCode>', $xml);
    }

    public function testBuildXmlSellerDetails(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('Oroya SARL', $xml);
        $this->assertStringContainsString('12345678900025', $xml); // SIRET
        $this->assertStringContainsString('FR00123456789', $xml);  // TVA
        $this->assertStringContainsString('contact@oroya.fr', $xml);
        $this->assertStringContainsString('37000', $xml);
        $this->assertStringContainsString('Tours', $xml);
    }

    public function testBuildXmlBuyerDetails(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('Société ClientTest', $xml);
        $this->assertStringContainsString('Poitiers', $xml);
        $this->assertStringContainsString('86000', $xml);
    }

    public function testBuildXmlBuyerOrderReference(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('PO-45678', $xml);
        $this->assertStringContainsString('BuyerOrderReferencedDocument', $xml);
    }

    public function testBuildXmlLineItems(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('Licence Logiciel Pro', $xml);
        $this->assertStringContainsString('Prestation installation', $xml);
        $this->assertStringContainsString('500.00', $xml); // prix unitaire
        $this->assertStringContainsString('1000.00', $xml); // montant HT ligne 1
    }

    public function testBuildXmlMultiRateVatGrouping(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);

        $dom = new \DOMDocument();
        $dom->load($xmlFile);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        // Deux groupes de TVA au niveau settlement : 20% et 10%
        $taxNodes = $xpath->query('//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax');
        $this->assertEquals(2, $taxNodes->length, 'Doit avoir 2 groupes de TVA (20% et 10%)');
    }

    public function testBuildXmlMonetaryTotals(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);

        $dom = new \DOMDocument();
        $dom->load($xmlFile);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $summation = '//ram:SpecifiedTradeSettlementHeaderMonetarySummation';

        // LineTotalAmount = 1000 + 200 = 1200
        $lineTotal = $xpath->query("$summation/ram:LineTotalAmount")->item(0);
        $this->assertEquals('1200.00', $lineTotal->textContent);

        // TaxBasisTotalAmount = 1200 (pas de remises/charges)
        $taxBasis = $xpath->query("$summation/ram:TaxBasisTotalAmount")->item(0);
        $this->assertEquals('1200.00', $taxBasis->textContent);

        // TaxTotalAmount = 1000*0.20 + 200*0.10 = 200 + 20 = 220
        $taxTotal = $xpath->query("$summation/ram:TaxTotalAmount")->item(0);
        $this->assertEquals('220.00', $taxTotal->textContent);
        $this->assertEquals('EUR', $taxTotal->getAttribute('currencyID'));

        // GrandTotalAmount = 1200 + 220 = 1420
        $grandTotal = $xpath->query("$summation/ram:GrandTotalAmount")->item(0);
        $this->assertEquals('1420.00', $grandTotal->textContent);

        // DuePayableAmount = GrandTotal
        $duePayable = $xpath->query("$summation/ram:DuePayableAmount")->item(0);
        $this->assertEquals('1420.00', $duePayable->textContent);
    }

    public function testBuildXmlWithAllowancesAndCharges(): void
    {
        $facture = $this->createFixtureFacture();

        // Remise fidélité : -50 EUR @ 20%
        $discount = (new FactureAllowanceCharge())
            ->setAmount(50.00)
            ->setTaxRate(20.00)
            ->setIsCharge(false)
            ->setReason('Remise fidélité');
        $facture->addAllowanceCharge($discount);

        // Frais de port : +30 EUR @ 20%
        $charge = (new FactureAllowanceCharge())
            ->setAmount(30.00)
            ->setTaxRate(20.00)
            ->setIsCharge(true)
            ->setReason('Frais de port');
        $facture->addAllowanceCharge($charge);

        $xmlFile = $this->service->buildXml($facture);

        $dom = new \DOMDocument();
        $dom->load($xmlFile);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $summation = '//ram:SpecifiedTradeSettlementHeaderMonetarySummation';

        // AllowanceTotalAmount = 50
        $allowTotal = $xpath->query("$summation/ram:AllowanceTotalAmount")->item(0);
        $this->assertEquals('50.00', $allowTotal->textContent);

        // ChargeTotalAmount = 30
        $chargeTotal = $xpath->query("$summation/ram:ChargeTotalAmount")->item(0);
        $this->assertEquals('30.00', $chargeTotal->textContent);

        // TaxBasisTotalAmount = 1200 - 50 + 30 = 1180
        $taxBasis = $xpath->query("$summation/ram:TaxBasisTotalAmount")->item(0);
        $this->assertEquals('1180.00', $taxBasis->textContent);

        // Les raisons sont dans le XML
        $xml = file_get_contents($xmlFile);
        $this->assertStringContainsString('Remise fidélité', $xml);
        $this->assertStringContainsString('Frais de port', $xml);
    }

    public function testBuildXmlWithPaymentMeans(): void
    {
        $facture = $this->createFixtureFacture();

        $pm = (new PaymentMeans())
            ->setCode('58')
            ->setInformation('FR7612345678901234567890123');
        $facture->addPaymentMeans($pm);

        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('<ram:TypeCode>58</ram:TypeCode>', $xml);
    }

    public function testBuildXmlDefaultPaymentMeansWhenNone(): void
    {
        $facture = $this->createFixtureFacture();
        // Pas de PaymentMeans ajouté → doit utiliser le défaut (virement bancaire, code 42)

        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('SpecifiedTradeSettlementPaymentMeans', $xml);
        $this->assertStringContainsString('<ram:TypeCode>42</ram:TypeCode>', $xml);
    }

    public function testBuildXmlDeliveryDateFallsBackToInvoiceDate(): void
    {
        $facture = $this->createFixtureFacture();
        $facture->setDateLivraison(null);

        $xmlFile = $this->service->buildXml($facture);

        $dom = new \DOMDocument();
        $dom->load($xmlFile);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        // Doit utiliser la date de facture (2025-10-07) comme date de livraison
        $deliveryDate = $xpath->query('//ram:ActualDeliverySupplyChainEvent//udt:DateTimeString')->item(0);
        $this->assertEquals('20251007', $deliveryDate->textContent);
    }

    public function testBuildXmlDueDateInPaymentTerms(): void
    {
        $facture = $this->createFixtureFacture();
        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        // Date d'échéance 2025-11-06
        $this->assertStringContainsString('20251106', $xml);
    }

    public function testBuildXmlDefaultPaymentTermsWhenNoDueDate(): void
    {
        $facture = $this->createFixtureFacture();
        $facture->setDateEcheance(null);

        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('Paiement à 30 jours', $xml);
    }

    public function testBuildXmlCurrencyCode(): void
    {
        $facture = $this->createFixtureFacture();
        $facture->setDevise('USD');

        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringContainsString('<ram:InvoiceCurrencyCode>USD</ram:InvoiceCurrencyCode>', $xml);
        $this->assertStringContainsString('currencyID="USD"', $xml);
    }

    public function testBuildXmlWithoutComment(): void
    {
        $facture = $this->createFixtureFacture();
        $facture->setCommentaire(null);

        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringNotContainsString('IncludedNote', $xml);
    }

    public function testBuildXmlWithoutBuyerOrderReference(): void
    {
        $facture = $this->createFixtureFacture();
        $facture->setCommandeAcheteur(null);

        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        $this->assertStringNotContainsString('BuyerOrderReferencedDocument', $xml);
    }

    public function testBuildXmlSirenDerivedFromSiret(): void
    {
        $facture = $this->createFixtureFacture();
        // Fournisseur sans SIREN explicite, mais avec SIRET
        $facture->getFournisseur()->setSiren(null);
        $facture->getFournisseur()->setSiret('12345678900025');

        $xmlFile = $this->service->buildXml($facture);
        $xml = file_get_contents($xmlFile);

        // Le SIREN (9 premiers chiffres du SIRET) doit être dérivé
        $this->assertStringContainsString('123456789', $xml);
        $this->assertStringContainsString('schemeID="FC"', $xml);
    }

    // ──────────────────────────────────────────────────────────────
    // embedXmlInExistingPdf() — intégration XML dans PDF utilisateur
    // ──────────────────────────────────────────────────────────────

    public function testEmbedXmlInExistingPdfReturnsPdf(): void
    {
        $facture = $this->createFixtureFacture();

        // Génère un PDF minimal simulant un template utilisateur
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<html><body><h1>Facture Test</h1><p>Contenu utilisateur</p></body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        $result = $this->service->embedXmlInExistingPdf($pdfContent, $facture);

        // Le résultat est un PDF valide (signature %PDF)
        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testEmbedXmlInExistingPdfIsLargerThanInput(): void
    {
        $facture = $this->createFixtureFacture();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<html><body><h1>Test</h1></body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();

        $result = $this->service->embedXmlInExistingPdf($pdfContent, $facture);

        // Le PDF résultant est plus gros (XML + métadonnées Factur-X ajoutés)
        $this->assertGreaterThan(strlen($pdfContent), strlen($result));
    }

    public function testEmbedXmlInExistingPdfGeneratesXmlFile(): void
    {
        $facture = $this->createFixtureFacture();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<html><body><h1>Test</h1></body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->service->embedXmlInExistingPdf($dompdf->output(), $facture);

        // Le fichier XML intermédiaire a été créé
        $xmlDir = $this->tmpDir . '/public/factures/xml';
        $this->assertDirectoryExists($xmlDir);
        $this->assertFileExists($xmlDir . '/facture_F2025-001_fx.xml');
    }

    public function testEmbedXmlFullPipelineWithAllFeatures(): void
    {
        $facture = $this->createFixtureFacture();

        // Ajouter des remises, charges et moyens de paiement
        $facture->addAllowanceCharge(
            (new FactureAllowanceCharge())
                ->setAmount(50.00)
                ->setTaxRate(20.00)
                ->setIsCharge(false)
                ->setReason('Remise fidélité')
        );
        $facture->addPaymentMeans(
            (new PaymentMeans())
                ->setCode('58')
                ->setInformation('FR7630006000011234567890189')
        );

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<html><body><h1>Facture complète</h1></body></html>');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $result = $this->service->embedXmlInExistingPdf($dompdf->output(), $facture);

        $this->assertStringStartsWith('%PDF', $result);
        $this->assertGreaterThan(1000, strlen($result));

        // Vérifie que le XML généré contient les bonnes données
        $xmlContent = file_get_contents($this->tmpDir . '/public/factures/xml/facture_F2025-001_fx.xml');
        $this->assertStringContainsString('Remise fidélité', $xmlContent);
        $this->assertStringContainsString('<ram:TypeCode>58</ram:TypeCode>', $xmlContent);
    }
}
