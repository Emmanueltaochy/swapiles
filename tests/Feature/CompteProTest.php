<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\LiveSession;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripePaymentIntentService;
use App\Support\ComptePro;
use App\Support\LiveAcces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Compte professionnel : prix affiché = prix payé, commission retenue sur la
 * part du pro, retrait en boutique avec code (pro payé à la validation),
 * livraison par le pro, mentions légales, lives réservés aux autorisés.
 */
class CompteProTest extends TestCase
{
    use RefreshDatabase;

    private const SIRET = '73282932000074';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('pricing.protection_rate', 0.10);
        config()->set('pricing.protection_floor', 0.50);
        config()->set('pricing.protection_cap', 15.00);
        config()->set('pricing.pro_commission_rate', 0.05);
        config()->set('pricing.pro_versement_auto_jours', 7);
        config()->set('live.ouvert_a_tous', false);
        config()->set('live.testeurs', []);
    }

    private function spyStripe(): object
    {
        $spy = new class extends StripePaymentIntentService
        {
            public int $lastAmount = -1;

            public function create(int $amountCents, array $metadata, array $options = [], string $currency = 'eur'): object
            {
                $this->lastAmount = $amountCents;

                return (object) ['id' => 'pi_test_' . uniqid(), 'client_secret' => 'cs_test'];
            }

            public function resolveCustomerId(User $buyer): ?string
            {
                return 'cus_test_' . $buyer->id;
            }
        };
        $this->app->instance(StripePaymentIntentService::class, $spy);

        return $spy;
    }

    private function membre(string $nom, array $plus = []): User
    {
        $u = User::create([
            'name' => $nom, 'email' => mb_strtolower(str_replace(' ', '', $nom)) . '@ex.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion', 'email_verified_at' => now(),
            'stripe_account_id' => 'acct_test', 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true, 'stripe_details_submitted' => true,
        ]);
        if ($plus) {
            $u->forceFill($plus)->save();
        }

        return $u->fresh();
    }

    private function pro(array $plus = []): User
    {
        return $this->membre('My Foot Run', $plus + [
            'pro_raison_sociale' => 'MY FOOT RUN SAS', 'pro_siret' => self::SIRET, 'pro_adresse' => '12 rue du Port, 97460 Saint-Paul',
            'pro_retrait_boutique' => true, 'pro_valide_at' => now(),
        ]);
    }

    private function basket(User $vendeur, float $prix = 89): Listing
    {
        $a = Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Dunk Low bleue', 'description' => 'Description de test assez longue.', 'price' => $prix,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'category_level1' => 'chaussures',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
        $a->images()->create(['url' => 'https://example.com/1.jpg', 'order' => 0]);

        return $a;
    }

    public function test_siret_et_activation(): void
    {
        $this->assertTrue(ComptePro::siretValide(self::SIRET));
        $this->assertFalse(ComptePro::siretValide('73282932000075'));
        $this->assertFalse(ComptePro::siretValide('123'));
        $this->assertSame('732 829 320 00074', ComptePro::siret(self::SIRET));

        // Sans validation par l'admin : rien ne change.
        $pasValide = $this->membre('Boutique', ['pro_raison_sociale' => 'X', 'pro_siret' => self::SIRET]);
        $this->assertFalse(ComptePro::estPro($pasValide));
        $this->assertTrue(ComptePro::estPro($this->pro()));
    }

    public function test_achat_chez_un_pro_retrait_en_boutique(): void
    {
        $spy = $this->spyStripe();
        $pro = $this->pro();
        $basket = $this->basket($pro);
        $client = $this->membre('Kevin');

        $this->actingAs($client)->post(route('checkout.start', $basket), ['delivery_method' => 'hand_delivery'])->assertOk();

        $t = Transaction::sole();
        $this->assertSame(8900, $spy->lastAmount, 'Prix affiché = prix payé : 89 €, rien ajouté.');
        $this->assertEquals(0, (float) $t->buyer_protection_fee);
        $this->assertEquals(4.45, (float) $t->commission);
        $this->assertEquals(84.55, (float) $t->seller_amount);
        $this->assertTrue($t->vendeur_pro);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $t->code_retrait);
        $this->assertSame('12 rue du Port, 97460 Saint-Paul', $t->hand_delivery_location);

        // Paiement reçu : le client voit son code, le pro le valide en boutique.
        $t->update(['status' => 'paid']);
        $this->actingAs($client)->get(route('account.transactions.show', $t))->assertOk()->assertSee($t->code_retrait);

        $this->actingAs($pro)->post(route('transactions.retrait-boutique', $t), ['code' => 'AAAAAA'])->assertSessionHasErrors('code');
        $this->assertSame('paid', $t->fresh()->status);

        $this->actingAs($client)->post(route('transactions.retrait-boutique', $t), ['code' => $t->code_retrait])->assertForbidden();

        $this->actingAs($pro)->post(route('transactions.retrait-boutique', $t), ['code' => mb_strtolower($t->code_retrait)])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Retrait validé'));
        $t->refresh();
        $this->assertSame('completed', $t->status);
        $this->assertNotNull($t->retrait_valide_at);
    }

    public function test_livraison_par_le_pro_a_son_prix(): void
    {
        $spy = $this->spyStripe();
        $pro = $this->pro(['pro_livraison' => true, 'pro_livraison_prix' => 5, 'pro_livraison_delai' => '48 h']);
        $basket = $this->basket($pro);
        $client = $this->membre('Lea');

        $this->actingAs($client)->get(route('checkout.start', $basket))->assertOk()
            ->assertSee('Livraison par la boutique')->assertSee('Retrait en boutique')
            ->assertSee('Droit de rétractation de 14 jours', false)->assertDontSee('Protection acheteur et livraison calculées');

        $this->actingAs($client)->post(route('checkout.start', $basket), [
            'delivery_method' => 'livraison_pro', 'buyer_full_name' => 'Léa Payet', 'buyer_phone' => '0692000000',
            'shipping_address_line1' => '3 rue des Lilas', 'shipping_postal_code' => '97400', 'shipping_city' => 'Saint-Denis',
        ])->assertOk();

        $t = Transaction::sole();
        $this->assertSame(9400, $spy->lastAmount, '89 € + 5 € de livraison du pro.');
        $this->assertEquals(5.0, (float) $t->shipping_fee);
        $this->assertEquals(4.45, (float) $t->commission);
        $this->assertEquals(89.55, (float) $t->seller_amount, 'La livraison revient au pro.');
        $this->assertNull($t->code_retrait);

        // Expédié il y a 8 jours, sans confirmation : versé automatiquement (7 jours pour un pro).
        $t->update(['status' => 'paid', 'shipping_status' => 'shipped', 'shipped_at' => now()->subDays(8)]);
        $this->artisan('transactions:auto-resolve')->assertExitCode(0);
        $this->assertSame('completed', $t->fresh()->status);
    }

    public function test_livraison_pro_refusee_si_le_pro_ne_livre_pas(): void
    {
        $this->spyStripe();
        $basket = $this->basket($this->pro());

        $this->actingAs($this->membre('Lea'))->post(route('checkout.start', $basket), [
            'delivery_method' => 'livraison_pro', 'buyer_full_name' => 'Léa', 'buyer_phone' => '0692', 'shipping_address_line1' => 'x',
            'shipping_postal_code' => '97400', 'shipping_city' => 'Saint-Denis',
        ])->assertForbidden();
    }

    public function test_particulier_inchange(): void
    {
        $spy = $this->spyStripe();
        $vendeur = $this->membre('Marie');
        $basket = $this->basket($vendeur);

        $this->actingAs($this->membre('Paul'))->post(route('checkout.start', $basket), ['delivery_method' => 'hand_delivery'])->assertOk();

        $t = Transaction::sole();
        $this->assertSame(9790, $spy->lastAmount, '89 € + 8,90 € de protection acheteur.');
        $this->assertEquals(0, (float) $t->commission);
        $this->assertEquals(89.0, (float) $t->seller_amount);
        $this->assertFalse($t->vendeur_pro);
        $this->assertNull($t->code_retrait);
    }

    public function test_fiche_annonce_et_profil_du_pro(): void
    {
        $pro = $this->pro(['pro_retours' => 'Échange possible sous 30 jours.']);
        $basket = $this->basket($pro);

        $this->get(route('listings.show', $basket))->assertOk()
            ->assertSee('Prix TTC · aucun frais ajouté', false)
            ->assertSee('Vendeur professionnel')
            ->assertSee('732 829 320 00074')
            ->assertSee('Échange possible sous 30 jours.', false)
            ->assertDontSee('de protection acheteur');

        $this->get(route('profiles.show', $pro))->assertOk()->assertSee('Vendeur professionnel')->assertSee('MY FOOT RUN SAS');
        $this->get(route('legal.vendeurs-pro'))->assertOk()->assertSee('Droit de rétractation de 14 jours', false);
    }

    public function test_espace_pro_et_bon_de_commande(): void
    {
        $this->spyStripe();
        $pro = $this->pro();
        $basket = $this->basket($pro);
        $client = $this->membre('Kevin');
        $this->actingAs($client)->post(route('checkout.start', $basket), ['delivery_method' => 'hand_delivery']);
        $t = Transaction::sole();
        $t->update(['status' => 'paid']);

        $this->actingAs($pro)->get(route('account.pro.ventes'))->assertOk()
            ->assertSee('MY FOOT RUN SAS')->assertSee('Valider le code')->assertSee('84,55 €', false)->assertSee('4,45 €', false);

        $this->actingAs($client)->get(route('transactions.bon-de-commande', $t))->assertOk()
            ->assertSee('89,00 €', false)->assertSee('732 829 320 00074')->assertDontSee('Commission Swap');
        $this->actingAs($pro)->get(route('transactions.bon-de-commande', $t))->assertOk()->assertSee('Commission Swap', false);

        $intrus = $this->membre('Intrus');
        $this->actingAs($intrus)->get(route('transactions.bon-de-commande', $t))->assertForbidden();
        $this->actingAs($intrus)->get(route('account.pro.ventes'))->assertNotFound();
    }

    public function test_lives_regarder_et_lancer(): void
    {
        $membre = $this->membre('Lea');
        $autorise = $this->membre('Boutique', ['live_autorise' => true]);

        $this->assertFalse(LiveAcces::visible($membre));
        $this->assertTrue(LiveAcces::peutAnimer($autorise));

        // Lives ouverts à tous : tout le monde regarde, seuls les autorisés lancent.
        config()->set('live.ouvert_a_tous', true);
        $this->assertTrue(LiveAcces::visible($membre));
        $this->assertFalse(LiveAcces::peutAnimer($membre));

        $this->actingAs($membre)->get(route('live.create'))->assertRedirect(route('live.index'));
        $this->actingAs($membre)->post(route('live.store'), ['titre' => 'Test', 'articles' => [1]])->assertRedirect(route('live.index'));
        $this->assertSame(0, LiveSession::count());

        $this->actingAs($autorise)->get(route('live.create'))->assertOk();
    }

    public function test_admin_active_le_compte_pro(): void
    {
        $admin = $this->membre('Admin');
        $admin->forceFill(['email' => 'cabinet@taochyconsulting.fr'])->save();
        $boutique = $this->membre('Boutique');
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)
            ->callTableAction('comptePro', $boutique, [
                'pro_valide' => true, 'pro_raison_sociale' => 'MY FOOT RUN SAS', 'pro_siret' => '732 829 320 00075',
            ])
            ->assertHasTableActionErrors(['pro_siret']);
        $this->assertFalse(ComptePro::estPro($boutique->fresh()));

        \Livewire\Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)
            ->callTableAction('comptePro', $boutique, [
                'pro_valide' => true, 'pro_raison_sociale' => 'MY FOOT RUN SAS', 'pro_siret' => '732 829 320 00074',
                'pro_adresse' => '12 rue du Port', 'pro_commission_pct' => 7, 'pro_retrait_boutique' => true,
                'pro_livraison' => true, 'pro_livraison_prix' => 0, 'live_autorise' => true,
            ])
            ->assertHasNoTableActionErrors();

        $boutique->refresh();
        $this->assertTrue(ComptePro::estPro($boutique));
        $this->assertSame('73282932000074', $boutique->pro_siret);
        $this->assertEqualsWithDelta(0.07, ComptePro::taux($boutique), 0.00001);
        $this->assertTrue(LiveAcces::peutAnimer($boutique));
        $this->assertSame(0.0, ComptePro::prixLivraison($boutique));
    }

    public function test_page_admin_des_ventes_pros(): void
    {
        $this->spyStripe();
        $pro = $this->pro();
        $this->actingAs($this->membre('Kevin'))->post(route('checkout.start', $this->basket($pro)), ['delivery_method' => 'hand_delivery']);
        Transaction::sole()->update(['status' => 'paid']);

        $admin = $this->membre('Admin');
        $admin->forceFill(['email' => 'cabinet@taochyconsulting.fr'])->save();
        $this->actingAs($admin)->get('/admin/vendeurs-pros')->assertOk()
            ->assertSee('MY FOOT RUN SAS')->assertSee('4,45 €', false)->assertSee('commission à facturer');
    }
}
