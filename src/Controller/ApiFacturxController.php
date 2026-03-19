<?php

namespace App\Controller;

use App\Service\FacturxService;
use App\Service\FactureBuilderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/facturx')]
class ApiFacturxController extends AbstractController
{
    public function __construct(
        private readonly FacturxService $fxService,
        private readonly FactureBuilderService $invoiceBuilder,
    ) {}

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

            return new JsonResponse($this->invoiceBuilder->extractFromPdfText($pdf->getText()));
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Impossible de lire le PDF : ' . $e->getMessage()], 500);
        }
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
            $facture    = $this->invoiceBuilder->buildFromArray($data);
            $pdfContent = file_get_contents($pdfFile->getPathname());
            $result     = $this->fxService->embedXmlInExistingPdf($pdfContent, $facture);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }

        $filename = 'facture_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['numeroFacture']) . '_facturx.pdf';

        return new Response($result, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => strlen($result),
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
            $facture    = $this->invoiceBuilder->buildFromArray($data);
            $xmlFile    = $this->fxService->buildXml($facture);
            $xmlContent = file_get_contents($xmlFile);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur serveur : ' . $e->getMessage()], 500);
        }

        $filename = 'facture_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $data['numeroFacture']) . '_fx.xml';

        return new Response($xmlContent, 200, [
            'Content-Type'        => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
