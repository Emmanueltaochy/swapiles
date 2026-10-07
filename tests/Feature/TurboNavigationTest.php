<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navigation sans rechargement (Turbo).
 *
 * Turbo ne remplace que le contenu de la page : styles, scripts et connexion
 * restent en mémoire. Vérifié dans un Chromium Android simulé : 584 ms → 373 ms
 * par changement d'onglet, retour arrière servi depuis la mémoire.
 *
 * Ces tests gardent les règles qui rendent cela sûr. Chacune répare un
 * problème réel rencontré pendant la mise en place.
 */
class TurboNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function membre(): User
    {
        $membre = User::create([
            'name' => 'Marie',
            'email' => 'marie' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
        $membre->forceFill(['email_verified_at' => now()])->save();

        return $membre;
    }

    public function test_turbo_est_charge_et_suivi(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Après une mise en ligne, le nom des fichiers change : Turbo le voit
        // et recharge la page en entier, personne ne garde l'ancienne version.
        preg_match('/<script[^>]*build\/assets\/app-[^>]*>/', $html, $balise);
        $this->assertNotEmpty($balise, 'Le script principal (Turbo) n\'est plus chargé.');
        $this->assertStringContainsString('data-turbo-track="reload"', $balise[0]);
    }

    public function test_les_scripts_communs_sont_charges_une_seule_fois(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $entete = substr($html, 0, strpos($html, '</head>'));

        // Dans l'entête, différés et suivis : exécutés une fois, puis gardés en
        // mémoire. Dans le corps, Turbo les rejouerait à chaque page — un
        // toucher sur un cœur aurait alors été traité plusieurs fois.
        foreach (['push', 'report', 'share', 'password-eye', 'form-draft', 'favorite'] as $script) {
            $this->assertMatchesRegularExpression(
                '/<script defer data-turbo-track="reload"\s+src="[^"]*js\/' . preg_quote($script, '/') . '\.js\?v=\d+"/',
                $entete,
                "{$script}.js doit être dans l'entête, différé, suivi et versionné."
            );
        }

        $corps = substr($html, strpos($html, '<body'));
        $this->assertStringNotContainsString('js/favorite.js', $corps);
        $this->assertStringNotContainsString('instantpage.js', $html);
    }

    public function test_le_socle_passe_avant_tout_autre_script(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $socle = strpos($html, 'window.swpPage');
        $this->assertNotFalse($socle, 'Le socle de navigation a disparu.');
        $this->assertLessThan(strpos($html, 'build/assets/app-'), $socle);

        // Les scripts qui attendaient « DOMContentLoaded » démarrent encore
        // sur une page ouverte sans rechargement.
        $this->assertStringContainsString("type === 'DOMContentLoaded' && document.readyState !== 'loading'", $html);
    }

    public function test_pas_de_fondu_sur_les_pages_ouvertes_par_turbo(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Mesuré : Turbo attend la fin du fondu avant de rendre la page prête
        // (535 ms au lieu de 246).
        $this->assertStringNotContainsString('<meta name="view-transition"', $html);

        // Retour arrière instantané, sans aperçu périmé en avance.
        $this->assertStringContainsString('<meta name="turbo-cache-control" content="no-preview">', $html);
    }

    public function test_les_formulaires_restent_classiques_sauf_la_recherche(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("Turbo.config.forms.mode = 'optin'", $js);

        // Seules les recherches (GET, sans effet) passent par Turbo.
        $this->get('/')->assertOk()->assertSee('id="header-search-form"', false)
            ->assertSee('data-turbo="true" class="flex-1 relative" id="header-search-form"', false);
        $this->get(route('search'))->assertOk()->assertSee('data-turbo="true" class="space-y-3"', false);
    }

    public function test_les_pages_lourdes_se_rechargent_toujours_entierement(): void
    {
        $membre = $this->membre();

        foreach ([route('account.listings.create'), route('account.messages.index')] as $url) {
            $this->actingAs($membre)->get($url)
                ->assertOk()
                ->assertSee('<meta name="turbo-visit-control" content="reload">', false);
        }
    }

    public function test_la_recherche_peut_etre_rejouee_sans_erreur(): void
    {
        // « const » redéclarée à la deuxième visite : tout le script de la page
        // tombait en erreur.
        $html = $this->get(route('search'))->assertOk()->getContent();

        $this->assertStringContainsString('var categoryTree =', $html);
        $this->assertStringNotContainsString('const categoryTree', $html);
    }

    public function test_l_accueil_commence_par_son_doctype(): void
    {
        // Un script placé après la section était imprimé AVANT <!doctype html> :
        // le navigateur passait l'accueil en mode de compatibilité.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringStartsWith('<!doctype html>', ltrim($html));
    }

    public function test_aucune_page_n_imprime_de_contenu_avant_son_doctype(): void
    {
        foreach (glob(resource_path('views/**/*.blade.php')) ?: [] as $fichier) {
            $source = file_get_contents($fichier);
            if (! str_contains($source, "@extends('layouts.app')")) {
                continue;
            }

            $fin = strrpos($source, '@endsection');
            if ($fin === false) {
                continue;
            }

            $apres = trim(preg_replace('/\{\{--.*?--\}\}/s', '', substr($source, $fin + strlen('@endsection'))));
            $this->assertSame('', $apres, basename($fichier) . ' : contenu après la dernière section, imprimé avant la page.');
        }
    }
}
