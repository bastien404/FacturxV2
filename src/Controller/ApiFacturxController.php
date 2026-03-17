<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\FactureAllowanceCharge;
use App\Entity\FactureLigne;
use App\Entity\PaymentMeans;
use App\Service\FacturxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/facturx')]
class ApiFacturxController extends AbstractController
{
    public function __construct(private readonly FacturxService $fxService) {}

    /**
     * POST /api/facturx/parse
     *
     * Extrait les données d'un PDF de facture et retourne un JSON pré-rempli.
     * Accepte multipart/form-data avec un champ "pdf".
     */
    #[Route('/parse', name: 'api_facturx_parse', methods: ['POST'])]
    public function parse(Request $request): JsonResponse
    {
        $pdfFile = $request->files->get('pdf');
        if (!$pdfFile) {
            return new JsonResponse(['error' => 'Champ "pdf" manquant.'], 400);
        }

        try {
            $config = new \Smalot\PdfParser\Config();
            $config->setRetainImageContent(false);
            $parser = new \Smalot\PdfParser\Parser([], $config);
            $pdf    = $parser->parseFile($pdfFile->getPathname());
            $text   = $pdf->getText();

            return new JsonResponse($this->extractInvoiceData($text));
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Impossible de lire le PDF : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Extrait les données structurées d'un texte brut de facture PDF.
     * Retourne un tableau compatible avec buildFactureFromData() + une clé _detected
     * listant les champs identifiés automatiquement.
     */
    private function extractInvoiceData(string $rawText): array
    {
        $text     = str_replace(["\r\n", "\r"], "\n", $rawText);
        $detected = [];

        $result = [
            'numeroFacture' => null,
            'dateFacture'   => null,
            'devise'        => 'EUR',
            'fournisseur'   => ['codePays' => 'FR'],
            'acheteur'      => ['codePays' => 'FR'],
            'lignes'        => [],
            'paymentMeans'  => [],
            '_detected'     => [],
        ];

        // ── Numéro de facture ──────────────────────────────────────────────
        if (preg_match('/N[°o]?\s*([A-Z][A-Z0-9\-\/]{3,30})/u', $text, $m)) {
            $result['numeroFacture'] = trim($m[1]);
            $detected[] = 'numeroFacture';
        }

        // ── Date (le DD/MM/YYYY) ───────────────────────────────────────────
        if (preg_match('/le\s+(\d{2})\/(\d{2})\/(\d{4})/u', $text, $m)) {
            $result['dateFacture'] = "{$m[3]}-{$m[2]}-{$m[1]}";
            $detected[] = 'dateFacture';
        }

        // ── Acheteur (bloc après "CLIENT :") ──────────────────────────────
        if (preg_match('/CLIENT\s*:\s*\n\s*(.+?)\s*\n\s*(.+?)\s*\n\s*(\d{5})\s+(.+?)\s*\n/su', $text, $m)) {
            $result['acheteur'] = [
                'nom'        => trim($m[1]),
                'adresse'    => trim($m[2]),
                'codePostal' => trim($m[3]),
                'ville'      => trim($m[4]),
                'codePays'   => 'FR',
            ];
            $detected[] = 'acheteur';
        }

        // ── Fournisseur (ligne pied de page : NOM - adresse, CP VILLE - email) ──
        if (preg_match(
            '/^([^-\n]+?)\s*-\s*(.+?),\s*(\d{5})\s+([^-\n]+?)\s*-\s*([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/mu',
            $text, $m
        )) {
            $result['fournisseur']['nom']        = trim($m[1]);
            $result['fournisseur']['adresse']    = trim($m[2]);
            $result['fournisseur']['codePostal'] = trim($m[3]);
            $result['fournisseur']['ville']      = trim($m[4]);
            $result['fournisseur']['email']      = trim($m[5]);
            $detected[] = 'fournisseur';
        }

        // ── SIRET ──────────────────────────────────────────────────────────
        if (preg_match('/SIRET\s+([\d\s]{14,19})/u', $text, $m)) {
            $result['fournisseur']['siret'] = preg_replace('/\s/', '', trim($m[1]));
            $detected[] = 'fournisseur.siret';
        }

        // ── N° TVA intracommunautaire fournisseur ─────────────────────────
        // L'espace entre le code pays (ex: FR) et les chiffres est optionnel dans les PDF extraits
        if (preg_match('/TVA\s+Intracommunautaire\s+([A-Z]{2})\s*([\d]+)/u', $text, $m)) {
            $result['fournisseur']['numeroTva'] = $m[1] . $m[2];
            $detected[] = 'fournisseur.tva';
        }

        // ── IBAN + BIC → moyen de paiement ────────────────────────────────
        if (preg_match('/IBAN\s*:\s*([A-Z]{2}[\d\s]{10,30})/u', $text, $ibanM)) {
            $iban = preg_replace('/\s/', '', trim($ibanM[1]));
            $bic  = null;
            if (preg_match('/(?:BIC|SWIFT)\s*[\/:\s]+([A-Z0-9]{8,11})/u', $text, $bicM)) {
                $bic = trim($bicM[1]);
            }
            $result['paymentMeans'][] = [
                'code'        => '30',
                'information' => $bic ? $iban . ' / ' . $bic : $iban,
            ];
            $detected[] = 'paymentMeans';
        }

        // ── Taux TVA ──────────────────────────────────────────────────────
        $tauxTva = 20.0;
        if (preg_match('/TVA\s+([\d]+(?:[.,]\d+)?)\s*%/u', $text, $m)) {
            $tauxTva = (float) str_replace(',', '.', $m[1]);
        }

        // ── Lignes de facturation ──────────────────────────────────────────
        // Stratégie A : "Solde AMOUNT €\tAMOUNT €" dans la section tableau
        $lignes = [];
        if (preg_match('/Libellés.+?\n(.+?)SOUS-TOTAL HT/su', $text, $section)) {
            $sectionText = $section[1];

            preg_match_all(
                '/^Solde\s+([\d\s]+[.,]\d{2})\s*€\s+\t?\s*([\d\s]+[.,]\d{2})/mu',
                $sectionText,
                $soldeMatches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            );

            foreach ($soldeMatches as $match) {
                $lineAmount = (float) str_replace([' ', ','], ['', '.'], $match[2][0]);
                $preceding  = substr($sectionText, 0, $match[0][1]);
                $prevLines  = array_reverse(array_values(array_filter(
                    array_map('trim', explode("\n", $preceding)),
                    fn($l) => $l !== '' && !preg_match('/^(Description générale|Montant Total|Libellés|Ref\.)/iu', $l)
                )));
                $designation = !empty($prevLines) ? $prevLines[0] : 'Prestation';

                if ($lineAmount > 0) {
                    $lignes[] = ['designation' => $designation, 'quantite' => 1,
                        'prixUnitaireHT' => $lineAmount, 'tauxTVA' => $tauxTva, 'unite' => 'H87'];
                }
            }
        }

        // Stratégie B : "Montant Total HT du devis: AMOUNT"
        if (empty($lignes) && preg_match('/Montant Total HT du devis\s*:\s*([\d\s,]+[.,]\d{2})/u', $text, $m)) {
            $totalHT     = (float) str_replace([' ', ',', '€'], ['', '.', ''], $m[1]);
            $designation = 'Prestation';
            if (preg_match('/Libellés.+?\n(.+?)Montant Total HT/su', $text, $ds)) {
                $descLines = array_filter(
                    array_map('trim', explode("\n", $ds[1])),
                    fn($l) => $l !== '' && !preg_match('/^(Description générale|Libellés)/iu', $l)
                );
                if (!empty($descLines)) {
                    $designation = implode(' – ', $descLines);
                }
            }
            if ($totalHT > 0) {
                $lignes[] = ['designation' => $designation, 'quantite' => 1,
                    'prixUnitaireHT' => $totalHT, 'tauxTVA' => $tauxTva, 'unite' => 'H87'];
            }
        }

        // Stratégie C : SOUS-TOTAL HT comme fallback
        if (empty($lignes) && preg_match('/SOUS-TOTAL HT\s+([\d\s,]+[.,]\d{2})/u', $text, $m)) {
            $totalHT = (float) str_replace([' ', ',', '€'], ['', '.', ''], $m[1]);
            if ($totalHT > 0) {
                $lignes[] = ['designation' => 'Prestation', 'quantite' => 1,
                    'prixUnitaireHT' => $totalHT, 'tauxTVA' => $tauxTva, 'unite' => 'H87'];
            }
        }

        if (!empty($lignes)) {
            $result['lignes'] = $lignes;
            $detected[]       = 'lignes';
        }

        $result['_detected'] = $detected;
        return $result;
    }

    /**
     * GET /api/facturx/ui
     *
     * Interface web pour utiliser l'API depuis le navigateur.
     */
    #[Route('/ui', name: 'api_facturx_ui', methods: ['GET'])]
    public function ui(): Response
    {
        return $this->render('api/embed.html.twig', [
            'countries' => [
                'FR' => 'France', 'BE' => 'Belgique', 'CH' => 'Suisse', 'LU' => 'Luxembourg',
                'DE' => 'Allemagne', 'ES' => 'Espagne', 'IT' => 'Italie', 'GB' => 'Royaume-Uni',
                'NL' => 'Pays-Bas', 'PT' => 'Portugal', 'US' => 'États-Unis',
            ],
            'currencies' => [
                'EUR' => 'Euro (EUR)', 'USD' => 'Dollar US (USD)', 'GBP' => 'Livre sterling (GBP)',
                'CHF' => 'Franc suisse (CHF)',
            ],
            'payment_means' => [
                '30' => 'Virement', '42' => 'Virement bancaire', '48' => 'Carte bancaire',
                '49' => 'Prélèvement', '57' => 'Chèque', '58' => 'Virement SEPA',
            ],
        ]);
    }

    /**
     * POST /api/facturx/embed
     *
     * Prend un PDF existant et les données de facturation, retourne le PDF Factur-X.
     * Requête multipart/form-data :
     *   - pdf     : fichier PDF source (champ fichier)
     *   - invoice : chaîne JSON décrivant la facture
     */
    #[Route('/embed', name: 'api_facturx_embed', methods: ['POST'])]
    public function embed(Request $request): Response
    {
        $pdfFile = $request->files->get('pdf');
        if (!$pdfFile) {
            return new JsonResponse(['error' => 'Champ "pdf" manquant dans la requête.'], 400);
        }

        $invoiceJson = $request->request->get('invoice');
        if (!$invoiceJson) {
            return new JsonResponse(['error' => 'Champ "invoice" (JSON) manquant dans la requête.'], 400);
        }

        $data = json_decode($invoiceJson, true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'JSON invalide dans le champ "invoice".'], 400);
        }

        try {
            $facture = $this->buildFactureFromData($data);
            $pdfContent = file_get_contents($pdfFile->getPathname());
            $result = $this->fxService->embedXmlInExistingPdf($pdfContent, $facture);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }

        $filename = 'facture_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['numeroFacture']) . '_facturx.pdf';

        return new Response($result, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($result),
        ]);
    }

    /**
     * POST /api/facturx/xml
     *
     * Prend les données de facturation en JSON, retourne le XML Factur-X EN16931.
     * Corps de la requête : application/json
     */
    #[Route('/xml', name: 'api_facturx_xml', methods: ['POST'])]
    public function xml(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Corps JSON invalide ou manquant.'], 400);
        }

        try {
            $facture = $this->buildFactureFromData($data);
            $xmlFile = $this->fxService->buildXml($facture);
            $xmlContent = file_get_contents($xmlFile);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }

        $filename = 'facture_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['numeroFacture']) . '_fx.xml';

        return new Response($xmlContent, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Construit une entité Facture en mémoire à partir d'un tableau de données.
     * Aucune persistance Doctrine — usage purement transactionnel/API.
     *
     * @throws \InvalidArgumentException si un champ obligatoire est absent
     */
    /**
     * Valide les données de facture et lève une InvalidArgumentException
     * avec un message lisible listant tous les champs manquants/invalides.
     */
    private function validateInvoiceData(array $data): void
    {
        $errors = [];

        if (empty($data['numeroFacture']))  $errors[] = 'Numéro de facture manquant.';
        if (empty($data['dateFacture']))    $errors[] = 'Date de facture manquante.';
        if (empty($data['fournisseur']))    $errors[] = 'Bloc fournisseur manquant.';
        if (empty($data['acheteur']))       $errors[] = 'Bloc acheteur manquant.';
        if (empty($data['lignes']))         $errors[] = 'Au moins une ligne de facturation est requise.';

        if (!empty($data['fournisseur'])) {
            $f = $data['fournisseur'];
            if (empty($f['nom'])) $errors[] = 'Nom du fournisseur manquant.';
            if (empty($f['numeroTva']) && empty($f['siren']) && empty($f['siret']))
                $errors[] = 'Fournisseur : N° TVA, SIREN ou SIRET requis (BR-CO-26 / BR-S-02).';
        }

        if (!empty($data['acheteur']) && empty($data['acheteur']['nom']))
            $errors[] = "Nom de l'acheteur manquant.";

        foreach (($data['lignes'] ?? []) as $i => $ligne) {
            $n = $i + 1;
            if (empty($ligne['designation']))                                    $errors[] = "Ligne $n : désignation manquante.";
            if (!isset($ligne['quantite']) || (float)$ligne['quantite'] <= 0)   $errors[] = "Ligne $n : quantité invalide.";
            if (!isset($ligne['prixUnitaireHT']))                                $errors[] = "Ligne $n : prix unitaire HT manquant.";
            if (!isset($ligne['tauxTVA']))                                       $errors[] = "Ligne $n : taux TVA manquant.";
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
    }

    private function buildFactureFromData(array $data): Facture
    {
        $this->validateInvoiceData($data);

        $facture = new Facture();
        $facture
            ->setNumeroFacture($data['numeroFacture'])
            ->setDateFacture(new \DateTime($data['dateFacture']))
            ->setDevise($data['devise'] ?? 'EUR')
            ->setTypeFacture($data['typeFacture'] ?? 'FA')
            ->setNetApayer(0.0);

        if (!empty($data['dateEcheance']))    $facture->setDateEcheance(new \DateTime($data['dateEcheance']));
        if (!empty($data['dateLivraison']))   $facture->setDateLivraison(new \DateTime($data['dateLivraison']));
        if (!empty($data['commentaire']))     $facture->setCommentaire($data['commentaire']);
        if (!empty($data['commandeAcheteur'])) $facture->setCommandeAcheteur($data['commandeAcheteur']);

        $fData = $data['fournisseur'];
        $fournisseur = (new Client())
            ->setNom($fData['nom'])
            ->setSiren($fData['siren'] ?? null)
            ->setSiret($fData['siret'] ?? null)
            ->setNumeroTva($fData['numeroTva'] ?? null)
            ->setAdresse($fData['adresse'] ?? null)
            ->setVille($fData['ville'] ?? null)
            ->setCodePostal($fData['codePostal'] ?? null)
            ->setCodePays($fData['codePays'] ?? 'FR')
            ->setEmail($fData['email'] ?? null);
        $facture->setFournisseur($fournisseur);

        $aData = $data['acheteur'];
        $acheteur = (new Client())
            ->setNom($aData['nom'])
            ->setSiren($aData['siren'] ?? null)
            ->setNumeroTva($aData['numeroTva'] ?? null)
            ->setAdresse($aData['adresse'] ?? null)
            ->setVille($aData['ville'] ?? null)
            ->setCodePostal($aData['codePostal'] ?? null)
            ->setCodePays($aData['codePays'] ?? 'FR')
            ->setEmail($aData['email'] ?? null);
        $facture->setAcheteur($acheteur);

        foreach ($data['lignes'] as $ligneData) {
            foreach (['designation', 'quantite', 'prixUnitaireHT', 'tauxTVA'] as $f) {
                if (!isset($ligneData[$f])) {
                    throw new \InvalidArgumentException("Champ obligatoire manquant dans une ligne : $f");
                }
            }
            $montantHT = (float) $ligneData['quantite'] * (float) $ligneData['prixUnitaireHT'];
            $montantTVA = $montantHT * ((float) $ligneData['tauxTVA'] / 100);
            $ligne = (new FactureLigne())
                ->setDesignation($ligneData['designation'])
                ->setQuantite((float) $ligneData['quantite'])
                ->setPrixUnitaireHt((float) $ligneData['prixUnitaireHT'])
                ->setTauxTva((float) $ligneData['tauxTVA'])
                ->setUnite($ligneData['unite'] ?? 'H87')
                ->setMontantHt($montantHT)
                ->setMontantTva($montantTVA)
                ->setMontantTtc($montantHT + $montantTVA);
            $facture->addLigne($ligne);
        }

        // Allowances / Charges (remises et frais au niveau document)
        foreach ($data['allowances'] ?? [] as $allowData) {
            $allow = (new FactureAllowanceCharge())
                ->setAmount((float) $allowData['amount'])
                ->setTaxRate(isset($allowData['taxRate']) ? (float) $allowData['taxRate'] : null)
                ->setIsCharge((bool) ($allowData['isCharge'] ?? false))
                ->setReason($allowData['reason'] ?? null);
            $facture->addAllowanceCharge($allow);
        }

        // Moyens de paiement
        foreach ($data['paymentMeans'] ?? [] as $payData) {
            if (empty($payData['code'])) {
                throw new \InvalidArgumentException('Champ obligatoire manquant dans paymentMeans : code');
            }
            $payment = (new PaymentMeans())
                ->setCode($payData['code'])
                ->setInformation($payData['information'] ?? null);
            $facture->addPaymentMeans($payment);
        }

        // Calcul du net à payer
        $netAPayer = 0.0;
        foreach ($facture->getLignes() as $ligne) {
            $netAPayer += $ligne->getMontantTtc();
        }
        $facture->setNetApayer($netAPayer);

        return $facture;
    }
}
