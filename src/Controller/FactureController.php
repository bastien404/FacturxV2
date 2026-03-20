<?php

namespace App\Controller;

use App\Entity\Facture;
use App\Entity\FactureLigne;
use App\Repository\FactureRepository;
use App\Repository\ClientRepository;
use App\Entity\Client;
use App\Entity\FactureAllowanceCharge;
use App\Entity\PaymentMeans;
use App\Service\FacturxService;
use App\Form\FactureType;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FactureController extends AbstractController
{
    #[Route('/new', name: 'facture_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em, ClientRepository $clientRepository): Response
    {
        $facture = new Facture();

        if ($request->isMethod('POST')) {
            $facture
                ->setNumeroFacture($request->request->get('numeroFacture'))
                ->setDateFacture(new \DateTime($request->request->get('dateFacture')))
                ->setDevise($request->request->get('devise'))
                ->setTypeFacture("FA") // FA: Facture, FC: Avoir, FN: Note de frais
                ->setCommandeAcheteur($request->request->get('commandeAcheteur'))
                ->setDateEcheance($request->request->get('dateEcheance') ? new \DateTime($request->request->get('dateEcheance')) : null)
                ->setDateLivraison($request->request->get('dateLivraison') ? new \DateTime($request->request->get('dateLivraison')) : null)
                ->setModePaiement($request->request->get('modePaiement'))
                ->setReferencePaiement($request->request->get('referencePaiement'))
                ->setCommentaire($request->request->get('commentaire'));

            // --- Clients ---
            if ($request->request->get('fournisseur_id') && $request->request->get('fournisseur_id') !== 'new') {
                $fournisseur = $clientRepository->find($request->request->get('fournisseur_id'));
            } else {
                $fournisseur = new Client();
                $fournisseur
                    ->setNom($request->request->get('nomFournisseur'))
                    ->setSiren($request->request->get('sirenFournisseur'))
                    ->setSiret($request->request->get('siretFournisseur'))
                    ->setNumeroTva($request->request->get('tvaFournisseur'))
                    ->setCodePays($request->request->get('codePaysFournisseur'))
                    ->setEmail($request->request->get('emailFournisseur'))
                    ->setAdresse($request->request->get('adresseFournisseur'))
                    ->setVille($request->request->get('villeFournisseur'))
                    ->setCodePostal($request->request->get('codePostalFournisseur'));
                $em->persist($fournisseur);
            }
            $facture->setFournisseur($fournisseur);

            // Acheteur
            if ($request->request->get('acheteur_id') && $request->request->get('acheteur_id') !== 'new') {
                $acheteur = $clientRepository->find($request->request->get('acheteur_id'));
            } else {
                $acheteur = new Client();
                $acheteur
                    ->setNom($request->request->get('nomAcheteur'))
                    ->setSiren($request->request->get('sirenAcheteur'))
                    ->setNumeroTva($request->request->get('tvaAcheteur'))
                    ->setCodePays($request->request->get('codePaysAcheteur'))
                    ->setEmail($request->request->get('emailAcheteur'))
                    ->setAdresse($request->request->get('adresseAcheteur'))
                    ->setVille($request->request->get('villeAcheteur'))
                    ->setCodePostal($request->request->get('codePostalAcheteur'));
                $em->persist($acheteur);
            }
            $facture->setAcheteur($acheteur);

            // --- Lignes de facture ---
            $lignes = $request->request->all('lignes');
            foreach ($lignes as $ligneData) {
                $ligne = new FactureLigne();
                $ligne
                    ->setDesignation($ligneData['designation'])
                    ->setQuantite((float) $ligneData['quantite'])
                    ->setUnite($ligneData['unite'] ?? null)
                    ->setPrixUnitaireHt((float) $ligneData['prixUnitaireHT'])
                    ->setTauxTva((float) $ligneData['tauxTVA']);

                $montantHT = $ligne->getQuantite() * $ligne->getPrixUnitaireHt();
                $montantTVA = $montantHT * ($ligne->getTauxTva() / 100);
                $ligne->setMontantHt($montantHT)
                    ->setMontantTva($montantTVA)
                    ->setMontantTtc($montantHT + $montantTVA);

                $ligne->setFacture($facture); // Association bidirectionnelle
                $facture->addLigne($ligne);
                $em->persist($ligne);

                // Ajouter la taxe dans le tableau taxes
                // Ici méthode à adapter : $facture->addTaxe(...) personnalisée à ton entité (à créer si besoin)
            }

            // --- Allowances / Charges ---
            $allowances = $request->request->all('allowances');
            foreach ($allowances as $allowData) {
                $allow = new FactureAllowanceCharge();
                $allow
                    ->setAmount((float) $allowData['amount'])
                    ->setTaxRate(isset($allowData['taxRate']) ? (float) $allowData['taxRate'] : null)
                    ->setIsCharge((bool) ($allowData['isCharge'] ?? false))
                    ->setReason($allowData['reason'] ?? null);

                $allow->setFacture($facture); // Association
                $facture->addAllowanceCharge($allow);
                $em->persist($allow);
            }

            // --- Payment Means ---
            $payments = $request->request->all('paymentMeans');
            foreach ($payments as $payData) {
                $payment = new PaymentMeans();
                $payment
                    ->setCode($payData['code'])
                    ->setInformation($payData['information'] ?? null);

                $payment->setFacture($facture); // Association
                $facture->addPaymentMeans($payment);
                $em->persist($payment);
            }

            // Calcul du net à payer après ajout de toutes les lignes
            $netAPayer = 0.0;
            foreach ($facture->getLignes() as $ligne) {
                $netAPayer += $ligne->getMontantTtc();
            }
            $facture->setNetApayer($netAPayer);

            $em->persist($facture);
            $em->flush();

            $this->addFlash('success', 'Facture créée avec succès ✅');
            return $this->redirectToRoute('facture_index');
        }

        $clients = $clientRepository->findAll();

        return $this->render('facture/new.html.twig', [
            'facture' => $facture,
            'clients' => $clients,
            'countries' => ['FR' => 'France', /* ... */],
            'currencies' => ['EUR' => 'Euro', /* ... */],
            'payment_means' => ['58' => 'Virement SEPA', /* ... */],
        ]);
    }

    #[Route('/', name: 'facture_index', methods: ['GET'])]
    public function index(FactureRepository $factureRepository): Response
    {
        return $this->render('facture/index.html.twig', [
            'factures' => $factureRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'facture_show', methods: ['GET'])]
    public function show(Facture $facture): Response
    {
        return $this->render('facture/pdf_template.html.twig', [
            'facture' => $facture,
        ]);
    }

    #[Route('/{id}/edit', name: 'facture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Facture $facture, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FactureType::class, $facture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($facture->getLignes() as $ligne) {
                $em->persist($ligne); // pour chaque ligne de facture
            }
            $em->persist($facture);
            $em->flush();
            $this->addFlash('success', 'Facture mise à jour ✅');
            return $this->redirectToRoute('facture_index');
        }

        return $this->render('facture/edit.html.twig', [
            'facture' => $facture,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'facture_delete', methods: ['POST'])]
    public function delete(Request $request, Facture $facture, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $facture->getId(), $request->request->get('_token'))) {
            $em->remove($facture);
            $em->flush();
            $this->addFlash('danger', 'Facture supprimée ❌');
        }

        return $this->redirectToRoute('facture_index');
    }

    #[Route('/{id}/download', name: 'facture_download_facturx', methods: ['GET'])]
    public function downloadFacturx(Facture $facture, FacturxService $fxService): Response
    {
        // Étape 1 — Rendu HTML → PDF en mémoire (aucun fichier temporaire sur disque)
        $html = $this->renderView('facture/pdfA3.html.twig', ['facture' => $facture]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output(); // binaire en mémoire

        // Étape 2 — Injection du XML Factur-X EN16931 dans le PDF binaire
        $facturxPdf = $fxService->embedXmlInExistingPdf($pdfContent, $facture);

        $filename = 'facture_' . $facture->getNumeroFacture() . '_facturx.pdf';

        return new Response($facturxPdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($facturxPdf),
        ]);
    }
}
