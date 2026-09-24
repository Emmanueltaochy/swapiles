<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Apple refuse (5.1.2(i)) qu'une app demande l'autorisation de suivi avec une
 * fenêtre maison : notre bandeau cookies en était une. Aucun traceur n'est donc
 * chargé dans l'application, et le bandeau y est retiré. Le site web est
 * inchangé.
 *
 * Ces règles vivent dans le JavaScript de la mise en page : ces tests évitent
 * qu'elles disparaissent d'une retouche.
 */
class NativeAppTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function layout(): string
    {
        return file_get_contents(resource_path('views/layouts/app.blade.php'));
    }

    public function test_les_traceurs_sont_neutralises_dans_l_application(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString('isNativePlatform', $layout);
        $this->assertStringContainsString("data-sans-traceurs", $layout);
        $this->assertMatchesRegularExpression(
            '/isNativePlatform\(\)\)\s*\{.*?metaId\s*=\s*null.*?gaId\s*=\s*null/s',
            $layout,
            'Le pixel Meta et la mesure d’audience doivent être coupés dans l’application.'
        );
    }

    public function test_le_bandeau_cookies_est_retire_dans_l_application(): void
    {
        $this->assertMatchesRegularExpression(
            '/data-sans-traceurs.*?banner\.remove\(\)/s',
            $this->layout(),
            'Le bandeau de consentement ne doit pas s’afficher dans l’application.'
        );
    }

    public function test_le_site_web_conserve_son_bandeau_et_ses_traceurs(): void
    {
        // Requête web ordinaire : le bandeau et les identifiants restent présents.
        $reponse = $this->get('/');

        $reponse->assertOk();
        $reponse->assertSee('cookie-banner', false);
        $reponse->assertSee('swapiles_cookie_consent', false);
    }

    public function test_les_autorisations_photo_ios_sont_declarees(): void
    {
        // Leur absence fait planter l'app dès qu'on touche « Prendre une photo »
        // (refus 2.1(a)). Elles ne sont pas fournies par le modèle Capacitor.
        $codemagic = file_get_contents(base_path('codemagic.yaml'));

        $this->assertStringContainsString('NSCameraUsageDescription', $codemagic);
        $this->assertStringContainsString('NSPhotoLibraryUsageDescription', $codemagic);

        // Les libellés ne doivent pas contenir d'apostrophe : PlistBuddy analyse
        // lui-même la chaîne reçue et l'échappement ne lui survit pas.
        preg_match_all('/Add :NS\w+UsageDescription string ([^"]+)"/', $codemagic, $m);
        $this->assertNotEmpty($m[1], 'Aucun libellé d’autorisation trouvé.');

        foreach ($m[1] as $libelle) {
            $this->assertStringNotContainsString("'", $libelle);
            $this->assertNotSame('', trim($libelle));
        }
    }
}
