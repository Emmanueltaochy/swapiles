<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Navigation rapide dans l'application, sans fausser les chiffres.
 *
 * Pour que les pages s'ouvrent sans attendre, le navigateur les télécharge dès
 * que le doigt touche un lien — parfois juste en faisant défiler. Deux
 * conséquences à tenir :
 *  - une annonce préchargée ne doit pas compter de vue tant qu'elle n'est pas
 *    réellement affichée (sinon vues gonflées et vendeur prévenu à tort) ;
 *  - les adresses qui AGISSENT quand on les charge (messages marqués lus,
 *    changement d'île, Stripe…) ne doivent jamais être préchargées.
 */
class NavigationSpeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function annonce(): Listing
    {
        $vendeur = User::create([
            'name' => 'Vendeuse',
            'email' => 'v' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);

        return Listing::create([
            'user_id' => $vendeur->id,
            'title' => 'Robe longue',
            'description' => 'Description de test suffisamment longue.',
            'price' => 20,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'femme',
            'category_level2' => 'vetements',
        ]);
    }

    public function test_une_visite_normale_compte_une_vue(): void
    {
        $annonce = $this->annonce();

        $this->get(route('listings.show', $annonce))
            ->assertOk()
            ->assertDontSee('prerenderingchange', false);

        $this->assertSame(1, (int) $annonce->fresh()->views_count);
    }

    public function test_un_prechargement_ne_compte_pas_de_vue(): void
    {
        $annonce = $this->annonce();

        // Ce qu'envoie Chrome / Android quand le doigt effleure la carte.
        $this->get(route('listings.show', $annonce), ['Sec-Purpose' => 'prefetch'])
            ->assertOk()
            // La page saura signaler la vue elle-même, à l'affichage.
            ->assertSee('prerenderingchange', false)
            // Adresse écrite pour JavaScript : les « / » y sont échappés.
            ->assertSee(json_encode(route('listings.view', $annonce)), false);

        $this->assertSame(0, (int) $annonce->fresh()->views_count);
        $this->assertDatabaseMissing('notifications', ['type' => 'listing_viewed']);
    }

    public function test_un_prerendu_ne_compte_pas_non_plus(): void
    {
        $annonce = $this->annonce();

        $this->get(route('listings.show', $annonce), ['Sec-Purpose' => 'prefetch;prerender'])->assertOk();

        $this->assertSame(0, (int) $annonce->fresh()->views_count);
    }

    public function test_les_autres_navigateurs_sont_reconnus(): void
    {
        $annonce = $this->annonce();

        $this->get(route('listings.show', $annonce), ['X-Moz' => 'prefetch'])->assertOk();
        $this->get(route('listings.show', $annonce), ['Purpose' => 'prefetch'])->assertOk();

        $this->assertSame(0, (int) $annonce->fresh()->views_count);
    }

    public function test_la_page_prechargee_signale_sa_vue_a_l_affichage(): void
    {
        $annonce = $this->annonce();

        $this->post(route('listings.view', $annonce))->assertNoContent();

        $this->assertSame(1, (int) $annonce->fresh()->views_count);
    }

    public function test_le_signalement_ne_compte_pas_une_annonce_retiree(): void
    {
        $annonce = $this->annonce();
        $annonce->update(['status' => 'draft']);

        $this->post(route('listings.view', $annonce))->assertNoContent();

        $this->assertSame(0, (int) $annonce->fresh()->views_count);
    }

    public function test_les_robots_ne_comptent_toujours_pas(): void
    {
        $annonce = $this->annonce();

        $this->post(route('listings.view', $annonce), [], ['User-Agent' => 'Googlebot/2.1'])->assertNoContent();

        $this->assertSame(0, (int) $annonce->fresh()->views_count);
    }

    public function test_le_prechargement_de_turbo_ne_compte_pas_de_vue(): void
    {
        $annonce = $this->annonce();

        // Turbo précharge en JavaScript : il ne peut pas poser d'en-tête
        // « Sec-… » et envoie « X-Sec-Purpose » à la place.
        $this->get(route('listings.show', $annonce), ['X-Sec-Purpose' => 'prefetch'])
            ->assertOk()
            ->assertSee('prerenderingchange', false);

        $this->assertSame(0, (int) $annonce->fresh()->views_count);
    }

    public function test_le_signalement_de_vue_s_efface_apres_usage(): void
    {
        $annonce = $this->annonce();

        // La page gardée en mémoire pour le retour arrière rejoue ses scripts :
        // sans cet effacement, la vue serait signalée une seconde fois.
        $this->get(route('listings.show', $annonce), ['X-Sec-Purpose' => 'prefetch'])
            ->assertOk()
            ->assertSee('document.currentScript.remove()', false);
    }

    public function test_les_adresses_qui_agissent_ne_sont_jamais_prechargees(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));

        // La liste des messages les marque tous comme lus ; /territoire change
        // d'île ; Stripe et le portefeuille créent des liens de paiement.
        foreach (["'/messages'", "'/territoire/'", "'/stripe/'", "'/portefeuille/'", "'/checkout/'"] as $interdit) {
            $this->assertStringContainsString($interdit, $js, "Adresse sensible non protégée : {$interdit}");
        }

        $this->assertStringContainsString('turbo:before-prefetch', $js);
    }

    public function test_les_pages_s_enchainent_sans_ecran_blanc(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('@view-transition', $html);
        $this->assertStringContainsString('navigation: auto', $html);

        // L'entête et la barre du bas restent immobiles pendant la transition.
        $this->assertStringContainsString('view-transition-name: swp-entete', $html);
        $this->assertStringContainsString('view-transition-name: swp-barre-bas', $html);
        $this->assertStringContainsString('data-barre-bas', $html);
    }

    public function test_le_toucher_est_acquitte_immediatement(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="swp-chargement"', $html);
        $this->assertSame(4, substr_count($html, 'data-onglet '), 'Les quatre onglets du bas doivent réagir au toucher.');
    }
}
