<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingOffer;
use App\Models\LiveMessage;
use App\Models\LiveSession;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Messagerie en direct (nouveaux messages, « en train d'écrire ») et choix de
 * l'acheteur d'une vente en main propre (liste élargie + autre membre).
 */
class MessagerieDirectEtAcheteurTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $nom): User
    {
        return User::create(['name' => $nom, 'email' => mb_strtolower($nom) . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
    }

    private function annonce(User $vendeur): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Vélo', 'description' => 'Description de test assez longue.', 'price' => 30,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => false, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    public function test_etat_de_la_conversation_et_en_train_d_ecrire(): void
    {
        $lea = $this->membre('Lea');
        $paul = $this->membre('Paul');
        $velo = $this->annonce($lea);
        $m = Message::create(['listing_id' => $velo->id, 'sender_id' => $paul->id, 'receiver_id' => $lea->id, 'body' => 'Dispo ?']);

        $page = $this->actingAs($lea)->get(route('account.messages.show', ['listing' => $velo, 'user' => $paul]))->assertOk();
        $page->assertSee('data-etat-url', false)->assertSee('est en train d’écrire', false);

        $etat = route('account.messages.etat', ['listing' => $velo, 'user' => $paul]);
        $this->actingAs($lea)->getJson($etat)->assertOk()->assertExactJson(['dernier' => $m->id, 'ecrit' => false]);

        // Paul écrit : Léa le voit.
        $this->actingAs($paul)->post(route('account.messages.ecrit', ['listing' => $velo, 'user' => $lea]))->assertNoContent();
        $this->actingAs($lea)->getJson($etat)->assertJson(['ecrit' => true]);
        // …mais Paul ne se voit pas lui-même écrire.
        $this->actingAs($paul)->getJson(route('account.messages.etat', ['listing' => $velo, 'user' => $lea]))->assertJson(['ecrit' => false]);

        // Nouveau message : le numéro du dernier change.
        $m2 = Message::create(['listing_id' => $velo->id, 'sender_id' => $paul->id, 'receiver_id' => $lea->id, 'body' => 'Je prends']);
        $this->actingAs($lea)->getJson($etat)->assertJson(['dernier' => $m2->id]);

        // Un inconnu n'a pas accès à la conversation.
        $intrus = $this->membre('Intrus');
        $this->actingAs($intrus)->getJson(route('account.messages.etat', ['listing' => $velo, 'user' => $paul]))->assertForbidden();
    }

    public function test_conversation_directe_en_direct(): void
    {
        $lea = $this->membre('Lea');
        $paul = $this->membre('Paul');
        $m = Message::create(['listing_id' => null, 'sender_id' => $paul->id, 'receiver_id' => $lea->id, 'body' => 'Salut']);

        $this->actingAs($paul)->post(route('account.messages.ecrit.general', $lea))->assertNoContent();
        $this->actingAs($lea)->getJson(route('account.messages.etat.general', $paul))
            ->assertExactJson(['dernier' => $m->id, 'ecrit' => true]);
    }

    public function test_liste_des_acheteurs_elargie(): void
    {
        $lea = $this->membre('Lea');
        $velo = $this->annonce($lea);
        $offre = $this->membre('Offre');
        ListingOffer::create(['listing_id' => $velo->id, 'buyer_id' => $offre->id, 'seller_id' => $lea->id, 'amount' => 25, 'status' => 'pending']);
        $direct = $this->membre('Direct');
        Message::create(['listing_id' => null, 'sender_id' => $direct->id, 'receiver_id' => $lea->id, 'body' => 'Tu as encore le vélo ?']);
        $spectateur = $this->membre('Spectateur');
        $live = LiveSession::create(['user_id' => $lea->id, 'titre' => 'Vide-dressing', 'statut' => 'termine']);
        LiveMessage::create(['live_session_id' => $live->id, 'user_id' => $spectateur->id, 'body' => 'Je prends le vélo !', 'type' => 'message']);
        $inconnu = $this->membre('Inconnu');

        $ids = $velo->cashBuyerCandidates()->pluck('id')->all();
        $this->assertContains($offre->id, $ids);
        $this->assertContains($direct->id, $ids);
        $this->assertContains($spectateur->id, $ids);
        $this->assertNotContains($inconnu->id, $ids);
    }

    public function test_un_autre_membre_cherche_par_son_nom(): void
    {
        $lea = $this->membre('Lea');
        $velo = $this->annonce($lea);
        $marie = $this->membre('Marie Payet');
        $this->membre('Paul');

        // Recherche : réservée à la vendeuse de l'annonce.
        $this->actingAs($lea)->getJson(route('account.listings.acheteurs', ['listing' => $velo, 'q' => 'mar']))
            ->assertOk()->assertJsonPath('membres.0.nom', 'Marie Payet')->assertJsonCount(1, 'membres');
        $this->actingAs($marie)->getJson(route('account.listings.acheteurs', ['listing' => $velo, 'q' => 'pa']))->assertForbidden();

        $this->actingAs($lea)->patch(route('account.listings.cash-paid', $velo), ['buyer_id' => 'autre', 'buyer_autre_id' => $marie->id])
            ->assertRedirect();

        $vente = Transaction::sole();
        $this->assertSame($marie->id, $vente->buyer_id);
        $this->assertTrue($vente->acheteur_hors_liste);
        $this->assertSame(1, Notification::where('user_id', $marie->id)->where('type', 'cash_sale_confirm')->count());

        // Pas d'avis de la vendeuse avant la confirmation de Marie (contre les faux avis).
        $this->actingAs($lea)->post(route('account.reviews.store', $vente), ['rating' => 1])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'confirmé'));
        $this->assertSame(0, \App\Models\Review::count());

        $vente->forceFill(['acheteur_confirme_at' => now()])->save();
        $this->actingAs($lea)->post(route('account.reviews.store', $vente), ['rating' => 5])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\Review::count());
    }

    public function test_le_vendeur_ne_peut_pas_se_designer_lui_meme(): void
    {
        $lea = $this->membre('Lea');
        $velo = $this->annonce($lea);

        $this->actingAs($lea)->patch(route('account.listings.cash-paid', $velo), ['buyer_id' => 'autre', 'buyer_autre_id' => $lea->id]);

        $this->assertNull(Transaction::sole()->buyer_id);
    }

    public function test_diagnostic_des_lives(): void
    {
        $this->artisan('live:diagnostic')->expectsOutputToContain('Enregistrement des replays configuré')->assertExitCode(0);
    }

    public function test_page_pro_live_shopping_cachee_de_google(): void
    {
        $this->get('/pro/live-shopping')->assertOk()
            ->assertSee('Vendez en direct', false)
            ->assertSee('noindex, nofollow', false);
        $this->get('/sitemap.xml')->assertDontSee('live-shopping');
    }
}
