<?php

namespace App\Controller;

use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    public const FACTURX_PROFILES = [
        'minimum'  => [
            'label'       => 'MINIMUM',
            'description' => 'Métadonnées minimales (numéro, date, montants). Pas de lignes de facture.',
            'guideline'   => 'urn:factur-x.eu:1p0:minimum',
        ],
        'basicwl'  => [
            'label'       => 'BASIC WL (Without Lines)',
            'description' => 'Comme BASIC mais sans les lignes de facture détaillées.',
            'guideline'   => 'urn:factur-x.eu:1p0:basicwl',
        ],
        'basic'    => [
            'label'       => 'BASIC',
            'description' => 'Lignes de facture, TVA ventilée, parties vendeur/acheteur. Profil recommandé.',
            'guideline'   => 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic',
        ],
        'en16931'  => [
            'label'       => 'EN16931 (COMFORT)',
            'description' => 'Profil complet norme européenne. Inclut allowances, charges, références commande.',
            'guideline'   => 'urn:cen.eu:en16931:2017',
        ],
        'extended' => [
            'label'       => 'EXTENDED',
            'description' => 'Profil étendu avec champs additionnels (informations bancaires détaillées, etc.).',
            'guideline'   => 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended',
        ],
    ];

    #[Route('/settings', name: 'settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, SettingRepository $settingRepo): Response
    {
        if ($request->isMethod('POST')) {
            $profile = $request->request->get('facturx_profile');

            if (array_key_exists($profile, self::FACTURX_PROFILES)) {
                $settingRepo->set('facturx_profile', $profile, 'Profil Factur-X pour la génération XML');
                $this->addFlash('success', 'Profil Factur-X mis à jour : ' . self::FACTURX_PROFILES[$profile]['label']);
            }

            return $this->redirectToRoute('admin_settings');
        }

        $currentProfile = $settingRepo->get('facturx_profile', 'basic');

        return $this->render('admin/settings.html.twig', [
            'profiles'       => self::FACTURX_PROFILES,
            'currentProfile' => $currentProfile,
        ]);
    }
}
