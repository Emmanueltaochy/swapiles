<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entete toujours visible et menu de navigation mobile.
 *
 * L'entete portait bien « position: sticky », mais « overflow-x: hidden » sur
 * le corps de page transforme celui-ci en conteneur de defilement, ce qui
 * neutralise l'adherence : l'entete disparaissait des qu'on faisait defiler.
 */
class MobileHeaderMenuTest extends TestCase
{
    use RefreshDatabase;

    private function layout(): string
    {
        return file_get_contents(resource_path('views/layouts/app.blade.php'));
    }

    public function test_le_corps_de_page_ne_casse_plus_l_adherence_de_l_entete(): void
    {
        $layout = $this->layout();

        // « clip » n'ouvre pas de conteneur de defilement, contrairement a « hidden ».
        $this->assertMatchesRegularExpression(
            '/html,\s*body\s*\{[^}]*overflow-x:\s*clip/s',
            $layout,
            'Le corps de page doit utiliser overflow-x: clip, sinon l’entete ne tient pas.'
        );

        // L'entete reste declaree collante.
        $this->assertStringContainsString('sticky top-0 z-50', $layout);
    }

    public function test_un_repli_existe_pour_les_navigateurs_anciens(): void
    {
        $this->assertStringContainsString('@supports not (overflow: clip)', $this->layout());
    }

    public function test_le_menu_mobile_est_present_pour_un_visiteur(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-menu-ouvrir', $html);
        $this->assertStringContainsString('id="menu-mobile"', $html);

        // Un visiteur doit pouvoir se connecter ou s'inscrire depuis le menu.
        $this->assertStringContainsString('Se connecter', $html);
        $this->assertStringContainsString("S'inscrire", $html);
    }

    /**
     * Contenu du seul panneau de menu, pied de page exclu : « Confidentialité »
     * vit aussi dans le pied de page, une recherche sur toute la page ne dirait
     * donc rien sur le menu.
     */
    private function contenuDuMenu(string $html): string
    {
        $debut = strpos($html, 'id="menu-mobile"');
        $fin = strpos($html, '<main>', $debut ?: 0);

        $this->assertNotFalse($debut, 'Le panneau de menu a disparu.');
        $this->assertNotFalse($fin, 'Impossible de delimiter le panneau de menu.');

        return substr($html, $debut, $fin - $debut);
    }

    public function test_le_menu_expose_la_navigation_absente_de_la_barre_du_bas(): void
    {
        $menu = $this->contenuDuMenu($this->get('/')->assertOk()->getContent());

        foreach (['Classement des dressings', 'Devenir point relais', 'Comment ça marche'] as $entree) {
            $this->assertStringContainsString($entree, $menu, "Entrée manquante : {$entree}");
        }
    }

    public function test_le_menu_ne_contient_pas_les_pages_juridiques(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $menu = $this->contenuDuMenu($html);

        // Le menu sert a naviguer dans la boutique, pas a lire du juridique.
        $this->assertStringNotContainsString('Conditions générales', $menu);
        $this->assertStringNotContainsString('Confidentialité', $menu);

        // Mais les deux pages restent liees ailleurs : Apple et Google l'exigent,
        // et les retirer partout ferait refuser l'application.
        $this->assertStringContainsString(route('legal.cgu'), $html);
        $this->assertStringContainsString(route('legal.privacy'), $html);
    }

    public function test_un_membre_connecte_voit_son_compte_et_la_deconnexion(): void
    {
        $membre = User::create([
            'name' => 'Marie',
            'email' => 'marie@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);

        $html = $this->actingAs($membre)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Marie', $html);
        $this->assertStringContainsString('Mes transactions', $html);
        $this->assertStringContainsString('Mon profil et mes notifications', $html);
        $this->assertStringContainsString('Se déconnecter', $html);
    }

    public function test_le_menu_est_ferme_au_chargement(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // L'attribut « hidden » evite que le panneau apparaisse avant le JavaScript.
        $this->assertMatchesRegularExpression('/id="menu-mobile"[^>]*hidden/', $html);
    }
}
