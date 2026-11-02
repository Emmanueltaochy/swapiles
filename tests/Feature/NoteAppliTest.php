<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\NoteAppli;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NoteAppliTest extends TestCase
{
    use RefreshDatabase;

    public function test_demande_envoyee_une_fois_puis_ignoree(): void
    {
        Queue::fake();
        $lea = User::create(['name' => 'Léa Martin', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        $this->assertTrue(NoteAppli::demander($lea));
        $this->assertFalse(NoteAppli::demander($lea->fresh()), 'Pas deux fois en 60 jours.');

        $notif = Notification::where('type', 'app_note')->sole();
        $this->assertSame('/noter', $notif->url);
        $this->assertStringStartsWith('Léa, une note', $notif->message);

        $this->travel(61)->days();
        $this->assertTrue(NoteAppli::demander($lea->fresh()));
    }

    public function test_lien_noter_ouvre_la_page_d_avis_du_bon_store(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)')->get('/noter')
            ->assertRedirect(config('deeplinks.stores.ios') . '?action=write-review');
        $android = $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Mobile')->get('/noter');
        $this->assertStringContainsString('showAllReviews=true', $android->headers->get('Location'));
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0)')->get('/noter')->assertOk()->assertSee('Noter sur l’App Store', false);
    }

    /** Vente terminée entre Léa (vendeuse) et Paul (acheteur). */
    private function venteTerminee(): array
    {
        $lea = User::create(['name' => 'Léa', 'email' => 'lea2@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
        $paul = User::create(['name' => 'Paul', 'email' => 'paul@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
        $annonce = \App\Models\Listing::create([
            'user_id' => $lea->id, 'title' => 'Robe', 'description' => 'Description de test assez longue.', 'price' => 20,
            'currency' => 'EUR', 'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'category_level1' => 'femme',
        ]);
        $vente = \App\Models\Transaction::create([
            'listing_id' => $annonce->id, 'seller_id' => $lea->id, 'buyer_id' => $paul->id,
            'amount' => 20, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 0,
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
            'delivery_method' => 'hand_delivery', 'status' => 'completed', 'shipping_status' => 'received',
        ]);

        return [$lea, $paul, $vente];
    }

    public function test_avis_5_etoiles_moment_heureux_pour_les_deux(): void
    {
        [$lea, $paul, $vente] = $this->venteTerminee();

        $this->actingAs($paul)->post(route('account.reviews.store', $vente), ['rating' => 5, 'comment' => 'Top !'])->assertSessionHasNoErrors();

        $this->assertTrue(NoteAppli::natifDu($paul->fresh()), 'L’acheteur content.');
        $this->assertTrue(NoteAppli::natifDu($lea->fresh()), 'La vendeuse bien notée.');

        // La page suivante, dans l'appli, ouvre la fenêtre de notation.
        $this->actingAs($paul->fresh())->get('/')->assertOk()->assertSee('id="swp-note-natif"', false);
    }

    public function test_avis_moyen_rien(): void
    {
        [$lea, $paul, $vente] = $this->venteTerminee();

        $this->actingAs($paul)->post(route('account.reviews.store', $vente), ['rating' => 3])->assertSessionHasNoErrors();

        $this->assertFalse(NoteAppli::natifDu($paul->fresh()));
        $this->assertFalse(NoteAppli::natifDu($lea->fresh()));
        $this->actingAs($paul->fresh())->get('/')->assertOk()->assertDontSee('id="swp-note-natif"', false);
    }

    public function test_fenetre_proposee_puis_pas_avant_4_mois(): void
    {
        [$lea, $paul] = $this->venteTerminee();
        NoteAppli::momentHeureux($paul);

        $this->actingAs($paul->fresh())->post(route('app.noter.natif'))->assertNoContent();
        $paul->refresh();
        $this->assertNotNull($paul->note_native_at);
        $this->assertFalse(NoteAppli::natifDu($paul));

        // Nouveau moment heureux 1 mois plus tard : trop tôt.
        $this->travel(30)->days();
        NoteAppli::momentHeureux($paul->fresh());
        $this->assertFalse(NoteAppli::natifDu($paul->fresh()));

        // Après 4 mois : de nouveau possible.
        $this->travel(100)->days();
        NoteAppli::momentHeureux($paul->fresh());
        $this->assertTrue(NoteAppli::natifDu($paul->fresh()));

        // Un moment heureux non utilisé expire après 14 jours.
        $this->travel(15)->days();
        $this->assertFalse(NoteAppli::natifDu($paul->fresh()));
    }

    public function test_page_noter_dans_l_appli_ouvre_la_fenetre_integree(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 SwapilesApp')
            ->get('/noter')->assertOk()->assertSee('La fenêtre de notation s’ouvre', false)->assertSee('Noter sur l’App Store', false);
    }

    public function test_sur_le_site_web_pas_de_fenetre_pour_un_visiteur(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="swp-note-natif"', false);
    }

    public function test_bandeau_mise_a_jour_pour_les_anciennes_versions_de_l_appli(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('id="swp-maj-appli" hidden', $html, 'Caché par défaut : seule l’ancienne appli l’affiche.');
        $this->assertStringContainsString('SwapilesApp', $html);
        $this->assertStringContainsString('play.google.com/store/apps/details?id=com.swapiles.app', $html);
    }
}
