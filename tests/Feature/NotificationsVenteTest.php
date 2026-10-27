<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use App\Models\RelayPoint;
use App\Models\Transaction;
use App\Models\User;
use App\Support\NotificationsVente;
use App\Support\PushPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Notifications dans l'appli tout au long d'une vente, relances, baisse de
 * prix d'un favori, et rappels (messages, favoris) qui n'existaient que par
 * e-mail.
 */
class NotificationsVenteTest extends TestCase
{
    use RefreshDatabase;

    private User $vendeur;

    private User $acheteur;

    private User $commercant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->vendeur = $this->membre('vendeur@ex.com', 'Marie');
        $this->acheteur = $this->membre('acheteur@ex.com', 'Paul');
        $this->commercant = $this->membre('relais@ex.com', 'Tao');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $email, string $nom): User
    {
        return User::create(['name' => $nom, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function annonce(float $prix = 20): Listing
    {
        return Listing::create([
            'user_id' => $this->vendeur->id, 'title' => 'Écran digital', 'description' => 'Description de test assez longue.',
            'price' => $prix, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => 'published', 'territoire' => 'La Réunion',
            'category_level1' => 'high-tech',
        ]);
    }

    private function vente(string $mode = 'colissimo', array $plus = []): Transaction
    {
        $relais = null;
        if ($mode === 'relay') {
            $relais = RelayPoint::create(['name' => 'Tao Bijoux', 'territoire' => 'La Réunion', 'city' => 'Saint-Gilles', 'is_active' => true, 'manager_user_id' => $this->commercant->id]);
            $relais->forceFill(['charte_version' => config('relais.charte_version'), 'charte_acceptee_at' => now()])->save();
        }

        return Transaction::create($plus + [
            'listing_id' => $this->annonce()->id, 'seller_id' => $this->vendeur->id, 'buyer_id' => $this->acheteur->id,
            'amount' => 25.70, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 1.20, 'shipping_fee' => 4.50,
            'currency' => 'EUR', 'payment_method' => 'cb', 'delivery_method' => $mode, 'status' => 'paid',
            'shipping_status' => 'pending', 'relay_point_id' => $relais?->id, 'relay_pickup_code' => $relais ? 'HKRYDV' : null,
            'relay_status' => $relais ? 'awaiting_deposit' : null,
        ]);
    }

    private function notifs(User $membre): array
    {
        return Notification::where('user_id', $membre->id)->orderBy('id')->pluck('message', 'type')->all();
    }

    // --- Étapes -----------------------------------------------------------------

    public function test_colis_parti_avec_le_suivi(): void
    {
        $vente = $this->vente();

        $this->actingAs($this->vendeur)->patch(route('transactions.shipped', $vente), ['carrier' => 'Colissimo', 'tracking_number' => '6A123'])->assertRedirect();

        $this->assertSame(
            '« Écran digital » est en route (suivi Colissimo 6A123). Confirme la réception dès que tu l\'as.',
            $this->notifs($this->acheteur)['transaction_shipped']
        );
    }

    public function test_point_relais_de_bout_en_bout(): void
    {
        $vente = $this->vente('relay');

        // Paiement : le commerçant sait qu'un colis arrive.
        (new \App\Jobs\SendTransactionStatusEmails($vente->id, 'paid'))->handle();
        $this->assertSame('Marie va déposer « Écran digital » chez toi pour Paul.', $this->notifs($this->commercant)['relay_incoming']);

        // Le commerçant réceptionne : acheteur (avec code) et vendeur prévenus.
        $this->actingAs($this->commercant)->patch(route('account.relay.deposit', $vente))->assertRedirect();
        $this->assertSame('« Écran digital » est disponible chez Tao Bijoux, Saint-Gilles. Code de retrait : HKRYDV.', $this->notifs($this->acheteur)['relay_ready']);
        $this->assertArrayHasKey('relay_deposit_confirmed', $this->notifs($this->vendeur));

        // Retrait au comptoir : vendeur payé, acheteur invité à laisser un avis.
        $this->actingAs($this->commercant)->patch(route('account.relay.pickup', $vente), ['pickup_code' => 'hkrydv'])->assertRedirect();
        $this->assertSame('Paul a retiré « Écran digital ». Ton paiement de 20,00 € est en route.', $this->notifs($this->vendeur)['transaction_received']);
        $this->assertSame('Bonne utilisation de « Écran digital » ! Tu peux laisser un avis à Marie.', $this->notifs($this->acheteur)['relay_collected']);
    }

    public function test_depot_declare_par_le_vendeur_previent_le_commercant(): void
    {
        $vente = $this->vente('relay');

        $this->actingAs($this->vendeur)->patch(route('transactions.relay-deposited', $vente))->assertRedirect();

        $this->assertArrayHasKey('relay_deposit_declared', $this->notifs($this->commercant));
        $this->assertArrayHasKey('relay_ready', $this->notifs($this->acheteur));
        $this->assertArrayNotHasKey('relay_deposit_confirmed', $this->notifs($this->vendeur), 'C’est lui qui l’a fait.');
    }

    public function test_paiement_envoye_au_vendeur(): void
    {
        $vente = $this->vente('colissimo', ['status' => 'completed']);

        (new \App\Jobs\SendTransactionStatusEmails($vente->id, 'released'))->handle();

        $this->assertSame('20,00 € pour « Écran digital » arrivent sur ton compte bancaire (1 à 3 jours ouvrés).', $this->notifs($this->vendeur)['payout_sent']);
    }

    public function test_jamais_deux_fois_la_meme_notification_si_le_job_est_rejoue(): void
    {
        $vente = $this->vente();

        NotificationsVente::apresEtape($vente, 'shipped');
        NotificationsVente::apresEtape($vente, 'shipped');

        $this->assertSame(1, Notification::where('type', 'transaction_shipped')->count());
    }

    // --- Relances ---------------------------------------------------------------

    private function vieillir(Transaction $t, int $jours, string $colonne = 'created_at'): void
    {
        DB::table('transactions')->where('id', $t->id)->update([$colonne => now()->subDays($jours)]);
    }

    public function test_pense_a_expedier_deux_fois_au_plus(): void
    {
        $vente = $this->vente();
        $this->vieillir($vente, 1);
        $this->assertSame(0, NotificationsVente::envoyerRelances(), 'Trop tôt.');

        $this->vieillir($vente, 2);
        $this->assertSame(1, NotificationsVente::envoyerRelances());
        $this->assertSame(0, NotificationsVente::envoyerRelances(), 'Pas deux fois le même jour.');
        $this->assertSame('Paul attend « Écran digital » depuis 2 jours. Sans envoi sous 10 jours, la vente peut être remboursée.', $this->notifs($this->vendeur)['seller_ship_reminder']);

        $this->vieillir($vente, 6);
        $this->assertSame(1, NotificationsVente::envoyerRelances());
        $this->vieillir($vente, 9);
        $this->assertSame(0, NotificationsVente::envoyerRelances(), 'Jamais plus de deux relances.');
    }

    public function test_confirme_la_reception(): void
    {
        $vente = $this->vente('colissimo', ['shipped_at' => now()->subDays(4)]);

        NotificationsVente::envoyerRelances();

        $this->assertSame('Confirme la réception de « Écran digital » pour que Marie soit payé. Sans réponse, la vente sera validée le 23/10.', $this->notifs($this->acheteur)['buyer_confirm_reminder']);
        $this->assertSame([], $this->notifs($this->vendeur), 'Déjà expédiée : on ne relance plus le vendeur.');
    }

    public function test_colis_qui_attend_au_point_relais_et_depot_oublie(): void
    {
        $attend = $this->vente('relay', ['relay_status' => 'deposited', 'relay_deposited_at' => now()->subDays(2), 'shipped_at' => now()->subDays(2)]);
        $oublie = $this->vente('relay');
        $this->vieillir($oublie, 3);

        $this->assertSame(2, NotificationsVente::envoyerRelances());
        $this->assertStringContainsString('Code de retrait : HKRYDV', $this->notifs($this->acheteur)['relay_pickup_reminder']);
        $this->assertStringContainsString('Dépose-le chez Tao Bijoux, Saint-Gilles', $this->notifs($this->vendeur)['seller_ship_reminder']);
    }

    public function test_remise_en_main_propre_et_ventes_declarees(): void
    {
        $main = $this->vente('hand_delivery');
        $this->vieillir($main, 2);
        $declaree = $this->vente('hand_delivery', ['payment_method' => 'especes', 'status' => 'completed']);
        $this->vieillir($declaree, 5);

        // La remise en main propre payée en ligne, et la vente déclarée en espèces
        // (l'acheteur doit confirmer pour qu'elle compte).
        $this->assertSame(2, NotificationsVente::envoyerRelances());
        $this->assertArrayHasKey('buyer_handover_reminder', $this->notifs($this->acheteur));
        $this->assertArrayHasKey('cash_sale_confirm_reminder', $this->notifs($this->acheteur));
    }

    public function test_les_relances_attendent_le_matin(): void
    {
        $this->acheteur->forceFill(['territoire' => 'La Réunion'])->save();
        $nuit = Carbon::parse('2026-10-13 23:30:00', 'Indian/Reunion');

        $this->assertSame('rappel', PushPolicy::niveau('seller_ship_reminder'));
        $this->assertSame('differer', PushPolicy::decider($this->acheteur, 'buyer_confirm_reminder', $nuit)['action']);
        $this->assertSame('envoyer', PushPolicy::decider($this->acheteur, 'relay_ready', $nuit)['action'], 'Le colis disponible part tout de suite.');
    }

    // --- Favoris et messages ----------------------------------------------------

    public function test_baisse_de_prix_d_un_favori(): void
    {
        $annonce = $this->annonce(30);
        $this->acheteur->favorites()->attach($annonce->id);
        $this->vendeur->favorites()->attach($annonce->id);

        $annonce->update(['price' => 25]);
        $this->assertSame(1, NotificationsVente::baisseDePrix($annonce, 30));
        $this->assertSame('« Écran digital » passe de 30,00 € à 25,00 €.', $this->notifs($this->acheteur)['favorite_price_drop']);
        $this->assertArrayNotHasKey('favorite_price_drop', $this->notifs($this->vendeur), 'Pas le vendeur lui-même.');

        $annonce->update(['price' => 22]);
        $this->assertSame(0, NotificationsVente::baisseDePrix($annonce, 25), 'Une seule alerte par jour et par article.');
        $this->assertSame(0, NotificationsVente::baisseDePrix($annonce, 20), 'Une hausse ne prévient personne.');
    }

    public function test_le_vendeur_qui_baisse_son_prix_declenche_l_alerte(): void
    {
        $annonce = $this->annonce(30);
        $annonce->images()->create(['url' => 'https://example.com/1.jpg', 'order' => 0]);
        $this->acheteur->favorites()->attach($annonce->id);

        $reponse = $this->actingAs($this->vendeur)->put(route('account.listings.update', $annonce), [
            'title' => 'Écran digital', 'description' => 'Description de test assez longue.', 'price' => 24,
            'listing_type' => 'achat', 'territoire' => 'La Réunion', 'category_level1' => 'high-tech',
            'payment_hand' => '1', 'pickup_city' => 'Saint-Paul', 'pickup_postal_code' => '97460',
        ]);
        $reponse->assertSessionHasNoErrors();

        $this->assertSame((float) 24, (float) $annonce->fresh()->price);
        $this->assertArrayHasKey('favorite_price_drop', $this->notifs($this->acheteur));
    }

    public function test_message_sans_reponse_aussi_dans_l_appli(): void
    {
        $annonce = $this->annonce();
        $m = Message::create(['listing_id' => $annonce->id, 'sender_id' => $this->acheteur->id, 'receiver_id' => $this->vendeur->id, 'body' => 'Dispo ?']);
        $m->forceFill(['created_at' => now()->subHours(30)])->saveQuietly();

        $this->artisan('messages:remind-unanswered')->assertExitCode(0);

        $notif = Notification::where('user_id', $this->vendeur->id)->where('type', 'message_reminder')->sole();
        $this->assertSame('💬 Paul attend ta réponse', $notif->title);
        $this->assertSame(route('account.messages.show', ['listing' => $annonce, 'user' => $this->acheteur], absolute: false), $notif->url);
    }

    public function test_rappel_des_favoris_aussi_dans_l_appli(): void
    {
        $annonce = $this->annonce(15);
        $this->acheteur->favorites()->attach($annonce->id);
        DB::table('favorites')->update(['created_at' => now()->subDays(8)]);

        $this->artisan('favorites:remind')->assertExitCode(0);

        $this->assertSame('« Écran digital » à 15,00 € t’attend encore. Avant qu’un autre ne le prenne ?', $this->notifs($this->acheteur)['favorite_reminder']);
    }

    public function test_les_emails_de_notification_se_generent_sans_tourner_en_boucle(): void
    {
        // La mise en page des e-mails s'appelait elle-même : chaque e-mail
        // (rappel des favoris, offres, annonce masquée…) tournait sans fin.
        $this->assertStringNotContainsString('x-mail::layout', file_get_contents(resource_path('views/vendor/mail/html/layout.blade.php')));

        $debut = microtime(true);
        $html = (string) (new \App\Notifications\FavoriteReminderNotification($this->annonce(15)))->toMail($this->acheteur)->render();

        $this->assertLessThan(5, microtime(true) - $debut);
        $this->assertStringContainsString('Écran digital', $html);
        $this->assertStringContainsString('L’équipe Swap’Îles', $html);
    }

    public function test_preference_vos_favoris(): void
    {
        $this->assertTrue($this->acheteur->accepteNotification('favorite_price_drop', 'push'), 'Activé par défaut.');

        $this->acheteur->forceFill(['notification_prefs' => ['favoris_suivis' => ['push' => false, 'email' => false]]])->save();
        $this->assertFalse($this->acheteur->fresh()->accepteNotification('favorite_price_drop', 'push'));
        $this->assertTrue($this->acheteur->fresh()->accepteNotification('seller_ship_reminder', 'push'), 'Les relances de vente ne se coupent pas.');
    }
}
