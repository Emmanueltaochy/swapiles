<?php

namespace Tests\Feature;

use App\Support\Categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rangée Femme / Homme / Enfant / High-tech… toujours à portée de doigt.
 *
 * Elle était « collante » en CSS, mais un élément collant ne colle que dans
 * le bloc qui le contient : l'en-tête de recherche, qui sort vite de l'écran.
 * Avec le défilement infini, il fallait remonter très loin pour changer de
 * rayon. Une copie de la rangée s'accroche désormais sous l'entête dès que
 * l'originale disparaît ; les autres filtres (Don, Paiement sécurisé…)
 * défilent normalement.
 */
class StickyCategoryRowTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->get(route('search'))->assertOk()->getContent();
    }

    public function test_la_copie_accrochee_existe_hors_de_l_en_tete_de_recherche(): void
    {
        $html = $this->page();

        // Fixe sous l'entête, cachée au départ (l'originale est visible).
        $this->assertMatchesRegularExpression('#<div data-rangee-fixe hidden class="fixed [^"]*" style="top: var\(--swp-entete, 64px\)">#', $html);

        // Placée APRÈS la fin de l'en-tête de recherche : rien ne la retient.
        $finEnTete = strpos($html, '</form>', strpos($html, 'data-rangee-categories'));
        $this->assertGreaterThan($finEnTete, strpos($html, 'data-rangee-fixe'));
    }

    public function test_la_copie_propose_toutes_les_categories_et_seulement_elles(): void
    {
        $html = $this->page();

        preg_match('#<div data-rangee-fixe.*?</nav>#s', $html, $copie);
        $this->assertNotEmpty($copie);

        foreach (Categories::niveau1() as $categorie) {
            $this->assertStringContainsString(e($categorie['label']), $copie[0]);
        }
        $this->assertStringContainsString('>Tout</a>', $copie[0]);

        // Pas les filtres secondaires : ils défilent avec la page.
        $this->assertStringNotContainsString('data-filtres-ouvrir', $copie[0]);
        $this->assertStringNotContainsString('Paiement sécurisé', $copie[0]);
    }

    public function test_l_original_et_la_copie_sont_identiques(): void
    {
        $html = $this->page();

        preg_match('#<div data-rangee-categories[^>]*>(.*?)</div>#s', $html, $originale);
        preg_match('#<div data-rangee-fixe.*?<nav[^>]*>(.*?)</nav>#s', $html, $copie);

        $this->assertSame(trim($originale[1]), trim($copie[1]));
    }

    public function test_le_script_bascule_selon_la_position_de_l_originale(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("var cachee = originale.getBoundingClientRect().bottom <= hauteurEntete();", $html);
        // Écouteurs retirés au changement de page (navigation sans rechargement).
        $this->assertStringContainsString('var options = { passive: true, signal: window.swpPage() };', $html);
    }
}
