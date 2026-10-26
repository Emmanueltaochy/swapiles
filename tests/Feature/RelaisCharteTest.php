<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\RelayPoint;
use App\Models\Transaction;
use App\Models\User;
use App\Support\GardeRelais;
use App\Support\Lots;
use App\Support\VersementsRelais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Charte des points relais : acceptation obligatoire, délai de garde de
 * 10 jours, limite de poids, relevés mensuels (autofacture) et versements.
 */
class RelaisCharteTest extends TestCase
{
    use RefreshDatabase;

    private User $commercant;

    private User $vendeur;

    private User $acheteur;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('features.relay_points', true);
        $this->commercant = $this->user('commerce@ex.com');
        $this->vendeur = $this->user('vendeur@ex.com');
        $this->acheteur = $this->user('acheteur@ex.com');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $email): User
    {
        return User::create(['name' => 'U ' . $email, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function relais(bool $charte = true): RelayPoint
    {
        $point = RelayPoint::create(['name' => 'D.E.S Point Services', 'territoire' => 'La Réunion', 'city' => 'L’Étang-Salé', 'is_active' => true, 'manager_user_id' => $this->commercant->id]);
        if ($charte) {
            $point->forceFill(['charte_version' => config('relais.charte_version'), 'charte_acceptee_at' => now()])->save();
        }

        return $point;
    }

    private function colis(RelayPoint $point, string $statut, array $plus = []): Transaction
    {
        $annonce = Listing::create(['user_id' => $this->vendeur->id, 'title' => 'Robe fleurie', 'description' => 'Description de test assez longue.', 'price' => 20, 'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion']);

        return Transaction::create($plus + [
            'listing_id' => $annonce->id, 'seller_id' => $this->vendeur->id, 'buyer_id' => $this->acheteur->id,
            'amount' => 25, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2, 'shipping_fee' => 0,
            'currency' => 'EUR', 'payment_method' => 'cb', 'delivery_method' => 'relay', 'relay_point_id' => $point->id,
            'relay_fee' => 3, 'relay_merchant_fee' => 1, 'relay_status' => $statut, 'relay_pickup_code' => 'ABC234',
            'status' => $statut === 'collected' ? 'completed' : 'paid',
            'shipping_status' => $statut === 'awaiting_deposit' ? 'pending' : 'shipped',
        ]);
    }

    public function test_charte_obligatoire_avant_de_gerer_les_colis(): void
    {
        $point = $this->relais(charte: false);
        $tx = $this->colis($point, 'awaiting_deposit');

        $this->actingAs($this->commercant)->get(route('account.relay.dashboard'))->assertOk()->assertSee('Accepte la charte des points relais');
        $this->actingAs($this->commercant)->patch(route('account.relay.deposit', $tx))->assertSessionHasErrors('charte');
        $this->assertSame('awaiting_deposit', $tx->fresh()->relay_status);

        $this->actingAs($this->commercant)->post(route('account.relay.charte'), ['accepte' => '1', 'raison_sociale' => 'D.E.S POINT SERVICES', 'siret' => '123 456 789 00012'])
            ->assertSessionHasNoErrors();
        $point->refresh();
        $this->assertTrue($point->charteAJour());
        $this->assertSame('12345678900012', $point->siret);
        $this->assertSame($this->commercant->id, $point->charte_acceptee_par);

        $this->actingAs($this->commercant)->patch(route('account.relay.deposit', $tx))->assertSessionHasNoErrors();
        $this->assertSame('deposited', $tx->fresh()->relay_status);

        // Nouvelle version de la charte : il faut l'accepter de nouveau.
        config()->set('relais.charte_version', '2099-01');
        $this->assertFalse($point->fresh()->charteAJour());
    }

    public function test_la_charte_publique_est_en_ligne(): void
    {
        $this->get(route('relay.charte'))->assertOk()
            ->assertSee('Charte des points relais')->assertSee('10 jours')->assertSee('mandat d’autofacturation')
            ->assertSee('dans la limite de 100 € par colis', false)->assertSee('toujours remboursé tout de suite', false)
            ->assertSee('récépissé de dépôt de plainte', false)->assertSee('déclaration de sinistre', false);
    }

    public function test_colis_non_retire_apres_10_jours(): void
    {
        $point = $this->relais();
        Carbon::setTestNow('2026-11-01 10:00:00');
        $tx = $this->colis($point, 'deposited', ['relay_deposited_at' => now(), 'shipped_at' => now()]);

        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->assertSame(0, GardeRelais::expirer());

        Carbon::setTestNow('2026-11-11 10:30:00');
        $this->assertSame(1, GardeRelais::expirer());
        $tx->refresh();
        $this->assertSame('expired', $tx->relay_status);
        $this->assertNotNull($tx->auto_review_flagged_at);
        $this->assertSame('paid', $tx->status);
        $this->assertTrue(Notification::where('user_id', $this->vendeur->id)->where('type', 'relay_expired_seller')->exists());
        $this->assertTrue(Notification::where('user_id', $this->acheteur->id)->where('type', 'relay_expired_buyer')->exists());
        $this->assertTrue(Notification::where('user_id', $this->commercant->id)->where('type', 'relay_expired_merchant')->exists());
        $this->assertSame(0, GardeRelais::expirer());

        // Plus de validation automatique à J14 pour un colis jamais retiré.
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->artisan('transactions:auto-resolve')->assertSuccessful();
        $this->assertSame('paid', $tx->fresh()->status);

        // Le vendeur récupère son colis : le commerçant est rémunéré.
        $this->actingAs($this->commercant)->patch(route('account.relay.return', $tx))->assertSessionHasNoErrors();
        $tx->refresh();
        $this->assertSame('returned', $tx->relay_status);
        $this->assertNotNull($tx->relay_returned_at);
        $this->assertSame(1.0, $point->earnedBalance());
    }

    public function test_acheteur_retardataire_peut_encore_retirer(): void
    {
        $point = $this->relais();
        $tx = $this->colis($point, 'expired', ['relay_deposited_at' => now()->subDays(11), 'shipped_at' => now()->subDays(11), 'auto_review_flagged_at' => now()]);

        $this->actingAs($this->commercant)->patch(route('account.relay.pickup', $tx), ['pickup_code' => 'abc234'])->assertSessionHasNoErrors();
        $tx->refresh();
        $this->assertSame('collected', $tx->relay_status);
        $this->assertSame('completed', $tx->status);
        $this->assertNull($tx->auto_review_flagged_at);
    }

    public function test_pas_de_point_relais_au_dessus_de_15_kg(): void
    {
        $this->relais();
        $leger = Listing::create(['user_id' => $this->vendeur->id, 'title' => 'Robe', 'description' => 'Description de test assez longue.', 'price' => 20, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'weight_kg' => 1]);
        $lourd = Listing::create(['user_id' => $this->vendeur->id, 'title' => 'Commode', 'description' => 'Description de test assez longue.', 'price' => 80, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'weight_kg' => 20]);
        $moyen = Listing::create(['user_id' => $this->vendeur->id, 'title' => 'Vélo', 'description' => 'Description de test assez longue.', 'price' => 60, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'weight_kg' => 14.5]);

        $this->assertCount(1, $leger->effectiveRelayPoints());
        $this->assertCount(0, $lourd->effectiveRelayPoints());
        // Le lot pèse 15,5 kg au total : pas de point relais.
        $this->assertCount(0, Lots::modesCommuns(collect([$leger, $moyen]))['relay']);
    }

    public function test_releve_mensuel_autofacture_et_versement(): void
    {
        $point = $this->relais();
        $point->forceFill(['raison_sociale' => 'D.E.S POINT SERVICES', 'siret' => '12345678900012'])->save();
        $this->colis($point, 'collected', ['relay_collected_at' => '2026-10-05 10:00:00']);
        $this->colis($point, 'collected', ['relay_collected_at' => '2026-10-20 10:00:00']);
        $this->colis($point, 'returned', ['relay_returned_at' => '2026-10-28 10:00:00']);
        $this->colis($point, 'collected', ['relay_collected_at' => '2026-11-02 10:00:00']);
        $this->colis($point, 'deposited');

        $octobre = Carbon::parse('2026-10-01');
        $r = VersementsRelais::releve($point, $octobre);
        $this->assertSame(3, $r['colis']);
        $this->assertSame(3.0, $r['total']);
        $this->assertTrue($r['autofacture']);
        $this->assertSame('SWP-R' . $point->id . '-202610', $r['numero']);

        $this->assertSame(['gagne' => 4.0, 'verse' => 0.0, 'retenues' => 0.0, 'a_verser' => 4.0], VersementsRelais::soldes($point->id));
        VersementsRelais::marquerVerse($point, $octobre, 'VIR-OCT');
        $this->assertSame(['gagne' => 4.0, 'verse' => 3.0, 'retenues' => 0.0, 'a_verser' => 1.0], VersementsRelais::soldes($point->id));

        $pdf = VersementsRelais::pdf($point, $octobre);
        $this->assertStringStartsWith('%PDF', $pdf);

        // Le commerçant télécharge son relevé ; un autre membre ne peut pas.
        $this->actingAs($this->commercant)->get(route('account.relay.releve', [$point, '2026-10']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->acheteur)->get(route('account.relay.releve', [$point, '2026-10']))->assertForbidden();

        // Dans l'appli : aperçu avec bouton retour, et version page web du relevé.
        $this->actingAs($this->commercant)->get(route('account.relay.releve', [$point, '2026-10', 'vue' => 'apercu']))
            ->assertOk()->assertSee('Retour à mon espace relais')->assertSee('Enregistrer / partager le PDF')->assertSee('vue=html', false);
        $this->actingAs($this->commercant)->get(route('account.relay.releve', [$point, '2026-10', 'vue' => 'html']))
            ->assertOk()->assertSee('SWP-R' . $point->id . '-202610');
        $this->actingAs($this->commercant)->get(route('account.relay.dashboard'))->assertOk()->assertSee('Mes relevés')->assertSee('versé le');

        // L'admin voit ce qui reste à verser.
        $admin = $this->user('cabinet@taochyconsulting.fr');
        $this->actingAs($admin)->get('/admin/relay-points')->assertOk()->assertSee('À verser')->assertSee('1,00 €');
    }

    public function test_tva_deduite_si_le_commerce_y_est_assujetti(): void
    {
        $point = $this->relais();
        $point->forceFill(['assujetti_tva' => true])->save();
        $this->colis($point, 'collected', ['relay_collected_at' => '2026-10-05 10:00:00']);

        $r = VersementsRelais::releve($point, Carbon::parse('2026-10-01'));
        $this->assertSame(1.0, $r['total']);
        $this->assertSame(0.92, $r['ht']);
        $this->assertSame(0.08, $r['tva']);
        $this->assertFalse($r['autofacture']);
    }

    public function test_retenue_pour_colis_perdu_par_le_commercant(): void
    {
        $point = $this->relais();
        $this->colis($point, 'collected', ['relay_collected_at' => '2026-10-05 10:00:00']);
        $this->colis($point, 'collected', ['relay_collected_at' => '2026-10-06 10:00:00']);
        $this->colis($point, 'collected', ['relay_collected_at' => '2026-10-07 10:00:00']);
        $octobre = Carbon::parse('2026-10-01');

        // Retenue de 2 € : 1 € part par virement. Jamais plus que les commissions du mois.
        $v = VersementsRelais::marquerVerse($point, $octobre, null, 2, 'Vente n° 12, colis égaré');
        $this->assertSame(1.0, $v->net());
        $this->assertSame(['gagne' => 3.0, 'verse' => 1.0, 'retenues' => 2.0, 'a_verser' => 0.0], VersementsRelais::soldes($point->id));

        $v = VersementsRelais::marquerVerse($point, $octobre, null, 50, 'Vente n° 12, colis égaré');
        $this->assertSame(3.0, (float) $v->retenue);
        $this->assertSame(0.0, $v->net());

        $this->assertStringStartsWith('%PDF', VersementsRelais::pdf($point, $octobre));
    }
}
