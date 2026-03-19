<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Facture;
use App\Entity\FactureAllowanceCharge;
use App\Entity\FactureLigne;
use App\Entity\PaymentMeans;

/**
 * Service de construction d'entités Facture à partir de données brutes.
 *
 * Ce service est indépendant du transport HTTP et peut être utilisé depuis :
 *   - un contrôleur Symfony (API REST)
 *   - une commande CLI (bin/console)
 *   - un batch de traitement de fichiers
 *   - des tests unitaires
 *
 * Aucune persistance Doctrine — les entités construites sont purement en mémoire.
 */
class FactureBuilderService
{
    /**
     * Extrait les données structurées d'un texte brut issu d'un PDF de facture.
     *
     * Retourne un tableau compatible avec buildFromArray(), avec une clé '_detected'
     * listant les champs identifiés automatiquement.
     *
     * @param string $rawText Texte brut extrait du PDF (via smalot/pdfparser ou équivalent)
     * @return array Données pré-remplies prêtes à passer à buildFromArray()
     */
    public function extractFromPdfText(string $rawText): array
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
     * Valide les données de facture et lève une \InvalidArgumentException
     * avec un message lisible listant tous les champs manquants/invalides.
     *
     * @throws \InvalidArgumentException si un ou plusieurs champs sont invalides
     */
    public function validate(array $data): void
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

    /**
     * Construit une entité Facture en mémoire à partir d'un tableau de données.
     *
     * Appelle validate() en amont — aucune persistance Doctrine.
     * Compatible avec FacturxService::buildXml() et embedXmlInExistingPdf().
     *
     * Exemple d'utilisation hors contexte HTTP :
     *
     *   $facture = $builder->buildFromArray([
     *       'numeroFacture' => 'FA-2024-001',
     *       'dateFacture'   => '2024-01-15',
     *       'fournisseur'   => ['nom' => 'ACME SAS', 'siret' => '12345678900014', ...],
     *       'acheteur'      => ['nom' => 'Client SARL', ...],
     *       'lignes'        => [['designation' => 'Prestation', 'quantite' => 1, ...]],
     *   ]);
     *   $xmlPath = $fxService->buildXml($facture);
     *
     * @throws \InvalidArgumentException si un champ obligatoire est absent ou invalide
     */
    public function buildFromArray(array $data): Facture
    {
        $this->validate($data);

        $facture = new Facture();
        $facture
            ->setNumeroFacture($data['numeroFacture'])
            ->setDateFacture(new \DateTime($data['dateFacture']))
            ->setDevise($data['devise'] ?? 'EUR')
            ->setTypeFacture($data['typeFacture'] ?? 'FA')
            ->setNetApayer(0.0);

        if (!empty($data['dateEcheance']))     $facture->setDateEcheance(new \DateTime($data['dateEcheance']));
        if (!empty($data['dateLivraison']))    $facture->setDateLivraison(new \DateTime($data['dateLivraison']));
        if (!empty($data['commentaire']))      $facture->setCommentaire($data['commentaire']);
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
            $montantHT  = (float) $ligneData['quantite'] * (float) $ligneData['prixUnitaireHT'];
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

        foreach ($data['allowances'] ?? [] as $allowData) {
            $allow = (new FactureAllowanceCharge())
                ->setAmount((float) $allowData['amount'])
                ->setTaxRate(isset($allowData['taxRate']) ? (float) $allowData['taxRate'] : null)
                ->setIsCharge((bool) ($allowData['isCharge'] ?? false))
                ->setReason($allowData['reason'] ?? null);
            $facture->addAllowanceCharge($allow);
        }

        foreach ($data['paymentMeans'] ?? [] as $payData) {
            if (empty($payData['code'])) {
                throw new \InvalidArgumentException('Champ obligatoire manquant dans paymentMeans : code');
            }
            $payment = (new PaymentMeans())
                ->setCode($payData['code'])
                ->setInformation($payData['information'] ?? null);
            $facture->addPaymentMeans($payment);
        }

        $netAPayer = array_sum(array_map(fn($l) => $l->getMontantTtc(), $facture->getLignes()->toArray()));
        $facture->setNetApayer($netAPayer);

        return $facture;
    }
}
