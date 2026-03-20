<?php

namespace App\Service;

use App\Entity\Facture;
use App\Repository\SettingRepository;
use Atgp\FacturX\Writer;
use Atgp\FacturX\Utils\ProfileHandler;
use Twig\Environment;

class FacturxService
{
    private Environment $twig;
    private string $projectDir;
    private SettingRepository $settingRepo;

    /**
     * Mapping profil → GuidelineID XML et constante Writer.
     */
    private const PROFILE_MAP = [
        'minimum'  => [
            'guideline' => 'urn:factur-x.eu:1p0:minimum',
            'writer'    => ProfileHandler::PROFILE_FACTURX_MINIMUM,
        ],
        'basicwl'  => [
            'guideline' => 'urn:factur-x.eu:1p0:basicwl',
            'writer'    => ProfileHandler::PROFILE_FACTURX_BASICWL,
        ],
        'basic'    => [
            'guideline' => 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic',
            'writer'    => ProfileHandler::PROFILE_FACTURX_BASIC,
        ],
        'en16931'  => [
            'guideline' => 'urn:cen.eu:en16931:2017',
            'writer'    => ProfileHandler::PROFILE_FACTURX_EN16931,
        ],
        'extended' => [
            'guideline' => 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended',
            'writer'    => ProfileHandler::PROFILE_FACTURX_EXTENDED,
        ],
    ];

    public function __construct(Environment $twig, string $projectDir, SettingRepository $settingRepo)
    {
        $this->twig = $twig;
        $this->projectDir = rtrim($projectDir, '/');
        $this->settingRepo = $settingRepo;
    }

    private function getActiveProfile(): string
    {
        return $this->settingRepo->get('facturx_profile', 'basic');
    }

    private function getGuidelineId(): string
    {
        $profile = $this->getActiveProfile();
        return self::PROFILE_MAP[$profile]['guideline'] ?? self::PROFILE_MAP['basic']['guideline'];
    }

    private function getWriterProfile(): string
    {
        $profile = $this->getActiveProfile();
        return self::PROFILE_MAP[$profile]['writer'] ?? self::PROFILE_MAP['basic']['writer'];
    }

    /**
     * Génère un XML Factur-X BASIC valide (EN16931)
     */
    public function buildXml(Facture $facture): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Profil actif — conditionne les éléments XML autorisés par le XSD
        $activeProfile = $this->getActiveProfile();
        $isExtendedProfile = in_array($activeProfile, ['en16931', 'extended'], true);

        // Root + namespaces
        $root = $dom->createElement('rsm:CrossIndustryInvoice');
        $root->setAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttribute('xmlns:qdt', 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $root->setAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $dom->appendChild($root);

        // Context
        $context = $dom->createElement('rsm:ExchangedDocumentContext');
        $guideline = $dom->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guideline->appendChild($dom->createElement('ram:ID', $this->getGuidelineId()));
        $context->appendChild($guideline);
        $root->appendChild($context);

        // Document header
        $document = $dom->createElement('rsm:ExchangedDocument');
        $document->appendChild($dom->createElement('ram:ID', $facture->getNumeroFacture()));
        // BT-3 : type de document UNTDID 1001 (380 = facture, 381 = avoir, 386 = acompte)
        $typeCodeMap = ['FA' => '380', 'FC' => '381', 'FN' => '386'];
        $rawType = $facture->getTypeFacture();
        $typeCode = $typeCodeMap[$rawType] ?? (is_numeric($rawType) ? $rawType : '380');
        $document->appendChild($dom->createElement('ram:TypeCode', $typeCode));

        $issueDate = $dom->createElement('ram:IssueDateTime');
        $dateStr = $dom->createElement('udt:DateTimeString', $facture->getDateFacture()->format('Ymd'));
        $dateStr->setAttribute('format', '102');
        $issueDate->appendChild($dateStr);
        $document->appendChild($issueDate);

        if ($facture->getCommentaire()) {
            $note = $dom->createElement('ram:IncludedNote');
            $note->appendChild($dom->createElement('ram:Content', $facture->getCommentaire()));
            $document->appendChild($note);
        }

        // Nature de l'opération (OBL sept 2026) — subjectCode ABL = note commerciale
        if ($facture->getNatureOperation()) {
            $noteOp = $dom->createElement('ram:IncludedNote');
            $noteOp->appendChild($dom->createElement('ram:Content', 'Nature de l\'opération : ' . $facture->getNatureOperation()));
            $noteOp->appendChild($dom->createElement('ram:SubjectCode', 'ABL'));
            $document->appendChild($noteOp);
        }

        // TVA sur les débits (OBL si applicable)
        if ($facture->isTvaDebits()) {
            $noteDebits = $dom->createElement('ram:IncludedNote');
            $noteDebits->appendChild($dom->createElement('ram:Content', 'TVA acquittée sur les débits'));
            $noteDebits->appendChild($dom->createElement('ram:SubjectCode', 'AAK'));
            $document->appendChild($noteDebits);
        }

        $root->appendChild($document);

        // SupplyChainTradeTransaction

        $tradeTransaction = $dom->createElement('rsm:SupplyChainTradeTransaction');
        $root->appendChild($tradeTransaction);

        // === (1) Toutes les lignes d'abord ===
        foreach ($facture->getLignes() as $idx => $ligne) {
            $line = $dom->createElement('ram:IncludedSupplyChainTradeLineItem');
            $lineDoc = $dom->createElement('ram:AssociatedDocumentLineDocument');
            $lineDoc->appendChild($dom->createElement('ram:LineID', $idx + 1));
            $line->appendChild($lineDoc);

            $product = $dom->createElement('ram:SpecifiedTradeProduct');
            $product->appendChild($dom->createElement('ram:Name', $ligne->getDesignation()));
            $line->appendChild($product);

            $price = $dom->createElement('ram:SpecifiedLineTradeAgreement');
            $netPrice = $dom->createElement('ram:NetPriceProductTradePrice');
            $netPrice->appendChild($dom->createElement('ram:ChargeAmount', number_format($ligne->getPrixUnitaireHt(), 2, '.', '')));
            $price->appendChild($netPrice);
            $line->appendChild($price);

            $deliveryL = $dom->createElement('ram:SpecifiedLineTradeDelivery');
            $qty = $dom->createElement('ram:BilledQuantity', number_format($ligne->getQuantite(), 4, '.', ''));
            // BT-130 : unité de mesure réelle (H87 = pièce, HUR = heure, DAY = jour, C62 = unité…)
            $qty->setAttribute('unitCode', $ligne->getUnite() ?: 'H87');
            $deliveryL->appendChild($qty);
            $line->appendChild($deliveryL);

            $settlementLine = $dom->createElement('ram:SpecifiedLineTradeSettlement');
            $tax = $dom->createElement('ram:ApplicableTradeTax');
            $tax->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            // BT-151 : catégorie TVA dynamique (S = standard, E = exonéré, Z = taux zéro…)
            $lineCategoryCode = $ligne->getCategorieTva() ?: 'S';
            $tax->appendChild($dom->createElement('ram:CategoryCode', $lineCategoryCode));
            $tax->appendChild($dom->createElement('ram:RateApplicablePercent', number_format($ligne->getTauxTva(), 2, '.', '')));
            $settlementLine->appendChild($tax);

            $sum = $dom->createElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
            $sum->appendChild($dom->createElement('ram:LineTotalAmount', number_format($ligne->getMontantHt(), 2, '.', '')));
            $settlementLine->appendChild($sum);

            $line->appendChild($settlementLine);

            $tradeTransaction->appendChild($line);
        }

        // === (2) Ensuite l'entête parties/accord ===
        $agreement = $dom->createElement('ram:ApplicableHeaderTradeAgreement');
        // Seller
        $seller = $dom->createElement('ram:SellerTradeParty');
        $fournisseur = $facture->getFournisseur();
        // Ordre XSD BASIC : Name → SpecifiedLegalOrganization → PostalTradeAddress
        //                 → URIUniversalCommunication → SpecifiedTaxRegistration
        // Ordre XSD EN16931 : idem + DefinedTradeContact entre LegalOrg et PostalAddress
        $seller->appendChild($dom->createElement('ram:Name', $fournisseur->getNom()));

        // SIRET → SpecifiedLegalOrganization (ISO 6523 schemeID 0009 = SIRET)
        if ($fournisseur->getSiret()) {
            $legalOrg = $dom->createElement('ram:SpecifiedLegalOrganization');
            $legalId  = $dom->createElement('ram:ID', $fournisseur->getSiret());
            $legalId->setAttribute('schemeID', '0009');
            $legalOrg->appendChild($legalId);
            $seller->appendChild($legalOrg);
        }

        // BT-43 : DefinedTradeContact (email) — EN16931+ uniquement
        if ($isExtendedProfile && $fournisseur->getEmail()) {
            $contact = $dom->createElement('ram:DefinedTradeContact');
            $emailNode = $dom->createElement('ram:EmailURIUniversalCommunication');
            $emailNode->appendChild($dom->createElement('ram:URIID', $fournisseur->getEmail()));
            $contact->appendChild($emailNode);
            $seller->appendChild($contact);
        }

        $sellerAddr = $dom->createElement('ram:PostalTradeAddress');
        if ($fournisseur->getCodePostal()) $sellerAddr->appendChild($dom->createElement('ram:PostcodeCode', $fournisseur->getCodePostal()));
        if ($fournisseur->getAdresse()) $sellerAddr->appendChild($dom->createElement('ram:LineOne', $fournisseur->getAdresse()));
        if ($fournisseur->getVille()) $sellerAddr->appendChild($dom->createElement('ram:CityName', $fournisseur->getVille()));
        $sellerAddr->appendChild($dom->createElement('ram:CountryID', $fournisseur->getCodePays() ?: 'FR'));
        $seller->appendChild($sellerAddr);

        // BT-31 : N° TVA intracommunautaire (schemeID='VA')
        if ($fournisseur->getNumeroTva()) {
            $taxReg = $dom->createElement('ram:SpecifiedTaxRegistration');
            $id = $dom->createElement('ram:ID', $fournisseur->getNumeroTva());
            $id->setAttribute('schemeID', 'VA');
            $taxReg->appendChild($id);
            $seller->appendChild($taxReg);
        }

        // BT-32 : SIREN (schemeID='FC') — satisfait BR-S-02 même sans N° TVA
        $siren = $fournisseur->getSiren()
            ?: ($fournisseur->getSiret() ? substr(preg_replace('/\D/', '', $fournisseur->getSiret()), 0, 9) : null);
        if ($siren) {
            $taxReg2 = $dom->createElement('ram:SpecifiedTaxRegistration');
            $id2 = $dom->createElement('ram:ID', $siren);
            $id2->setAttribute('schemeID', 'FC');
            $taxReg2->appendChild($id2);
            $seller->appendChild($taxReg2);
        }

        $agreement->appendChild($seller);

        // Buyer
        $acheteur = $facture->getAcheteur();
        $buyer = $dom->createElement('ram:BuyerTradeParty');
        // Ordre XSD CII : Name → SpecifiedLegalOrganization → PostalTradeAddress → SpecifiedTaxRegistration
        $buyer->appendChild($dom->createElement('ram:Name', $acheteur->getNom()));

        // BT-47 : SIREN acheteur (OBL sept 2026) — ISO 6523 schemeID 0002 = SIREN
        $buyerSiren = $acheteur->getSiren()
            ?: ($acheteur->getSiret() ? substr(preg_replace('/\D/', '', $acheteur->getSiret()), 0, 9) : null);
        if ($buyerSiren) {
            $buyerLegalOrg = $dom->createElement('ram:SpecifiedLegalOrganization');
            $buyerLegalId  = $dom->createElement('ram:ID', $buyerSiren);
            $buyerLegalId->setAttribute('schemeID', '0002');
            $buyerLegalOrg->appendChild($buyerLegalId);
            $buyer->appendChild($buyerLegalOrg);
        }

        $buyerAddr = $dom->createElement('ram:PostalTradeAddress');
        if ($acheteur->getCodePostal()) $buyerAddr->appendChild($dom->createElement('ram:PostcodeCode', $acheteur->getCodePostal()));
        if ($acheteur->getAdresse()) $buyerAddr->appendChild($dom->createElement('ram:LineOne', $acheteur->getAdresse()));
        if ($acheteur->getVille()) $buyerAddr->appendChild($dom->createElement('ram:CityName', $acheteur->getVille()));
        $buyerAddr->appendChild($dom->createElement('ram:CountryID', $acheteur->getCodePays() ?: 'FR'));
        $buyer->appendChild($buyerAddr);

        // BT-48 : N° TVA intracommunautaire acheteur (Recommandé)
        if ($acheteur->getNumeroTva()) {
            $buyerTaxReg = $dom->createElement('ram:SpecifiedTaxRegistration');
            $buyerTaxId  = $dom->createElement('ram:ID', $acheteur->getNumeroTva());
            $buyerTaxId->setAttribute('schemeID', 'VA');
            $buyerTaxReg->appendChild($buyerTaxId);
            $buyer->appendChild($buyerTaxReg);
        }

        $agreement->appendChild($buyer);

        // BT-13 : référence de commande acheteur
        if ($facture->getCommandeAcheteur()) {
            $buyerOrderRef = $dom->createElement('ram:BuyerOrderReferencedDocument');
            $buyerOrderRef->appendChild($dom->createElement('ram:IssuerAssignedID', $facture->getCommandeAcheteur()));
            $agreement->appendChild($buyerOrderRef);
        }

        $tradeTransaction->appendChild($agreement);

        // === (3) Livraison — toujours rempli (PEPPOL-R008 interdit les éléments vides)
        //         Si aucune date de livraison, on utilise la date de facture (pratique standard)
        $delivery         = $dom->createElement('ram:ApplicableHeaderTradeDelivery');

        // BG-13 : Adresse de livraison (OBL sept 2026 si différente de l'adresse acheteur)
        if ($facture->getLivraisonAdresse() || $facture->getLivraisonVille()) {
            $shipTo = $dom->createElement('ram:ShipToTradeParty');
            $shipToAddr = $dom->createElement('ram:PostalTradeAddress');
            if ($facture->getLivraisonCodePostal()) {
                $shipToAddr->appendChild($dom->createElement('ram:PostcodeCode', $facture->getLivraisonCodePostal()));
            }
            if ($facture->getLivraisonAdresse()) {
                $shipToAddr->appendChild($dom->createElement('ram:LineOne', $facture->getLivraisonAdresse()));
            }
            if ($facture->getLivraisonVille()) {
                $shipToAddr->appendChild($dom->createElement('ram:CityName', $facture->getLivraisonVille()));
            }
            $shipToAddr->appendChild($dom->createElement('ram:CountryID', $facture->getLivraisonCodePays() ?: 'FR'));
            $shipTo->appendChild($shipToAddr);
            $delivery->appendChild($shipTo);
        }

        $effectiveDelivery = $facture->getDateLivraison() instanceof \DateTimeInterface
            ? $facture->getDateLivraison()
            : $facture->getDateFacture();
        $deliveryEvent = $dom->createElement('ram:ActualDeliverySupplyChainEvent');
        $occ           = $dom->createElement('ram:OccurrenceDateTime');
        $occDate       = $dom->createElement('udt:DateTimeString', $effectiveDelivery->format('Ymd'));
        $occDate->setAttribute('format', '102');
        $occ->appendChild($occDate);
        $deliveryEvent->appendChild($occ);
        $delivery->appendChild($deliveryEvent);
        $tradeTransaction->appendChild($delivery);


        // === (4) Enfin settlement ===
        $settlement = $dom->createElement('ram:ApplicableHeaderTradeSettlement');
        $devise = $facture->getDevise() ?: 'EUR';
        $settlement->appendChild($dom->createElement('ram:InvoiceCurrencyCode', $devise));

        // === Moyens de paiement ===
        // BR-CO-27 : chaque SpecifiedTradeSettlementPaymentMeans DOIT contenir
        //            soit un IBANID soit un ProprietaryID dans PayeePartyCreditorFinancialAccount
        // Ordre XSD strict : IBANID → AccountName → ProprietaryID
        // AccountName et BIC ne sont autorisés qu'à partir du profil EN16931+
        // Éléments conditionnels selon le profil XSD actif :
        // BASIC : TypeCode → (DebtorAccount) → (CreditorAccount[IBANID|ProprietaryID])
        // EN16931+ : TypeCode → Information → (FinancialCard) → (DebtorAccount)
        //          → (CreditorAccount[IBANID, AccountName, ProprietaryID]) → (CreditorInstitution[BICID])
        if ($facture->getPaymentMeans() && count($facture->getPaymentMeans()) > 0) {
            foreach ($facture->getPaymentMeans() as $paymentMean) {
                $pm = $dom->createElement('ram:SpecifiedTradeSettlementPaymentMeans');
                $pm->appendChild($dom->createElement('ram:TypeCode', $paymentMean->getCode() ?: '42'));
                // Information : EN16931+ uniquement, doit suivre TypeCode
                if ($isExtendedProfile) {
                    $pm->appendChild($dom->createElement('ram:Information', $paymentMean->getInformation() ?: 'Paiement'));
                }

                // Compte financier — ordre XSD : IBANID → AccountName → ProprietaryID
                $account = $dom->createElement('ram:PayeePartyCreditorFinancialAccount');
                if ($paymentMean->getIban()) {
                    $account->appendChild($dom->createElement('ram:IBANID', $paymentMean->getIban()));
                    if ($isExtendedProfile) {
                        $account->appendChild($dom->createElement('ram:AccountName', $facture->getFournisseur()->getNom()));
                    }
                } else {
                    if ($isExtendedProfile) {
                        $account->appendChild($dom->createElement('ram:AccountName', $facture->getFournisseur()->getNom()));
                    }
                    $account->appendChild($dom->createElement('ram:ProprietaryID', $paymentMean->getCode() ?: 'NA'));
                }
                $pm->appendChild($account);

                // BIC : EN16931+ uniquement
                if ($isExtendedProfile && $paymentMean->getBic()) {
                    $bank = $dom->createElement('ram:PayeeSpecifiedCreditorFinancialInstitution');
                    $bank->appendChild($dom->createElement('ram:BICID', $paymentMean->getBic()));
                    $pm->appendChild($bank);
                }

                $settlement->appendChild($pm);
            }
        } else {
            $pm = $dom->createElement('ram:SpecifiedTradeSettlementPaymentMeans');
            $pm->appendChild($dom->createElement('ram:TypeCode', '42'));
            if ($isExtendedProfile) {
                $pm->appendChild($dom->createElement('ram:Information', 'Paiement par virement bancaire'));
            }

            $account = $dom->createElement('ram:PayeePartyCreditorFinancialAccount');
            if ($isExtendedProfile) {
                $account->appendChild($dom->createElement('ram:AccountName', $facture->getFournisseur()->getNom()));
            }
            $account->appendChild($dom->createElement('ram:ProprietaryID', 'VIREMENT'));
            $pm->appendChild($account);

            $settlement->appendChild($pm);
        }



        // === Taxes document (groupées par taux + catégorie — BG-23) ===
        // Clé composite taux+catégorie pour séparer S@20% / E@0% / Z@0% etc.
        $taxGroups = [];
        foreach ($facture->getLignes() as $ligne) {
            $r = number_format(round($ligne->getTauxTva(), 2), 2, '.', '');
            $cat = $ligne->getCategorieTva() ?: 'S';
            $key = $cat . '@' . $r;
            if (!isset($taxGroups[$key])) {
                $taxGroups[$key] = [
                    'base' => 0.0,
                    'rate' => (float) $r,
                    'category' => $cat,
                    'exemptionReason' => $ligne->getMotifExoneration(),
                ];
            }
            $taxGroups[$key]['base'] += $ligne->getMontantHt();
        }
        foreach ($facture->getAllowanceCharges() as $ac) {
            $rate = number_format(round($ac->getTaxRate(), 2), 2, '.', '');
            $key = 'S@' . $rate;
            if (!isset($taxGroups[$key])) {
                $taxGroups[$key] = ['base' => 0.0, 'rate' => (float) $rate, 'category' => 'S', 'exemptionReason' => null];
            }
            $taxGroups[$key]['base'] += $ac->getIsCharge() ? $ac->getAmount() : -$ac->getAmount();
        }

        $totalTax = 0.0;
        foreach ($taxGroups as $data) {
            $base = round($data['base'], 2);
            $calc = round($base * $data['rate'] / 100.0, 2);
            $totalTax += $calc;

            $taxNode = $dom->createElement('ram:ApplicableTradeTax');
            $taxNode->appendChild($dom->createElement('ram:CalculatedAmount', number_format($calc, 2, '.', '')));
            $taxNode->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            // BT-120/121 : motif d'exonération (OBL si catégorie E)
            if ($data['category'] === 'E' && $data['exemptionReason']) {
                $taxNode->appendChild($dom->createElement('ram:ExemptionReason', $data['exemptionReason']));
            }
            $taxNode->appendChild($dom->createElement('ram:BasisAmount', number_format($base, 2, '.', '')));
            // BT-118 : code catégorie TVA dynamique
            $taxNode->appendChild($dom->createElement('ram:CategoryCode', $data['category']));
            $taxNode->appendChild($dom->createElement('ram:RateApplicablePercent', number_format($data['rate'], 2, '.', '')));
            $settlement->appendChild($taxNode);
        }

        // === BG-20/BG-21 : Remises et frais niveau document ===
        // Requis par BR-CO-11 : doivent apparaître explicitement même s'ils sont déjà dans les totaux
        foreach ($facture->getAllowanceCharges() as $ac) {
            $acNode = $dom->createElement('ram:SpecifiedTradeAllowanceCharge');
            // true = frais (charge), false = remise (allowance) — CII exige un sous-élément udt:Indicator
            $chargeInd = $dom->createElement('ram:ChargeIndicator');
            $chargeInd->appendChild($dom->createElement('udt:Indicator', $ac->getIsCharge() ? 'true' : 'false'));
            $acNode->appendChild($chargeInd);
            $acNode->appendChild($dom->createElement('ram:ActualAmount', number_format($ac->getAmount(), 2, '.', '')));
            if ($ac->getReason()) {
                $acNode->appendChild($dom->createElement('ram:Reason', $ac->getReason()));
            }
            // Lien TVA de la remise/frais (obligatoire EN16931 §10.5)
            $acTax = $dom->createElement('ram:CategoryTradeTax');
            $acTax->appendChild($dom->createElement('ram:TypeCode', 'VAT'));
            $acTax->appendChild($dom->createElement('ram:CategoryCode', 'S'));
            $acTax->appendChild($dom->createElement('ram:RateApplicablePercent',
                number_format($ac->getTaxRate() ?? 0.0, 2, '.', '')));
            $acNode->appendChild($acTax);
            $settlement->appendChild($acNode);
        }

        // === Conditions de paiement ===
        $terms   = $dom->createElement('ram:SpecifiedTradePaymentTerms');
        $dueDate = $facture->getDateEcheance();
        if ($dueDate instanceof \DateTimeInterface) {
            $dueDateNode = $dom->createElement('ram:DueDateDateTime');
            $dateString  = $dom->createElement('udt:DateTimeString', $dueDate->format('Ymd'));
            $dateString->setAttribute('format', '102');
            $dueDateNode->appendChild($dateString);
            $terms->appendChild($dueDateNode);
        } else {
            $terms->appendChild($dom->createElement('ram:Description', 'Paiement à 30 jours'));
        }
        $settlement->appendChild($terms);

        // === Totaux ===
        $totalHT = 0.0;
        $totalAllow = 0.0;
        $totalCharge = 0.0;
        foreach ($facture->getLignes() as $ligne) {
            $totalHT += $ligne->getMontantHt();
        }
        foreach ($facture->getAllowanceCharges() as $ac) {
            if ($ac->getIsCharge()) $totalCharge += $ac->getAmount();
            else $totalAllow += $ac->getAmount();
        }

        $taxBasis = round($totalHT - $totalAllow + $totalCharge, 2);
        $taxTotal = round($totalTax, 2);
        $ttc = round($taxBasis + $taxTotal, 2);

        // Ordre XSD strict : LineTotalAmount → ChargeTotalAmount → AllowanceTotalAmount
        //                  → TaxBasisTotalAmount → TaxTotalAmount → GrandTotalAmount → DuePayableAmount
        // @currencyID requis uniquement sur TaxTotalAmount (EN16931 §6.4)
        $monetary = $dom->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        foreach ([
            'LineTotalAmount'     => $totalHT,
            'ChargeTotalAmount'   => $totalCharge,
            'AllowanceTotalAmount'=> $totalAllow,
            'TaxBasisTotalAmount' => $taxBasis,
        ] as $tag => $value) {
            $monetary->appendChild($dom->createElement('ram:' . $tag, number_format($value, 2, '.', '')));
        }
        $taxTotalNode = $dom->createElement('ram:TaxTotalAmount', number_format($taxTotal, 2, '.', ''));
        $taxTotalNode->setAttribute('currencyID', $devise);
        $monetary->appendChild($taxTotalNode);
        foreach ([
            'GrandTotalAmount' => $ttc,
            'DuePayableAmount' => $ttc,
        ] as $tag => $value) {
            $monetary->appendChild($dom->createElement('ram:' . $tag, number_format($value, 2, '.', '')));
        }
        $settlement->appendChild($monetary);

        // rattacher settlement à la transaction
        $tradeTransaction->appendChild($settlement);



        // Écriture fichier XML
        $xmlDir = $this->projectDir . '/public/factures/xml';
        if (!is_dir($xmlDir)) {
            mkdir($xmlDir, 0777, true);
        }
        $invoiceNumber = $facture->getNumeroFacture();
        $fileName = sprintf('%s/facture_%s_fx.xml', $xmlDir, $invoiceNumber);
        $dom->save($fileName);

        return $fileName;
    }
    
    /**
     * Embarque le XML Factur-X dans un PDF existant fourni en contenu binaire.
     * Aucune persistance en base — usage purement API/stateless.
     *
     * @param string  $pdfContent Contenu binaire du PDF source
     * @param Facture $facture    Entité facture construite en mémoire
     * @return string             Contenu binaire du PDF Factur-X résultant
     */
    public function embedXmlInExistingPdf(string $pdfContent, Facture $facture): string
    {
        $xmlFile = $this->buildXml($facture);
        $xmlContent = file_get_contents($xmlFile);

        $writer = new Writer();
        return $writer->generate(
            $pdfContent,
            $xmlContent,
            $this->getWriterProfile(),
            true,
            [],
            true
        );
    }

}
