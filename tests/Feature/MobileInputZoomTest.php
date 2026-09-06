<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Garde-fou : sur iPhone/iPad, WebKit zoome la page dès qu'on touche un champ
 * dont la police fait moins de 16 px, et dans l'application (WKWebView) la page
 * reste ensuite zoomée — l'utilisateur doit fermer l'app pour s'en sortir.
 *
 * La règle CSS qui force 16 px sur les champs tactiles est invisible à la
 * relecture de code : ce test empêche qu'elle disparaisse par mégarde.
 */
class MobileInputZoomTest extends TestCase
{
    public function test_la_regle_anti_zoom_est_presente_dans_la_feuille_de_style(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@media (pointer: coarse)', $css);
        $this->assertMatchesRegularExpression(
            '/@media \(pointer: coarse\).*?font-size:\s*16px\s*!important/s',
            $css,
            'La règle anti-zoom des champs (16 px sur écran tactile) a disparu de resources/css/app.css.'
        );
    }

    public function test_l_admin_injecte_aussi_la_regle_anti_zoom(): void
    {
        $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        $this->assertStringContainsString('pointer: coarse', $provider);
        $this->assertStringContainsString('font-size:16px', $provider);
    }
}
