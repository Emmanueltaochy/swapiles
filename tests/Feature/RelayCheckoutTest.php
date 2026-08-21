<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\RelayPoint;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripePaymentIntentService;
use App\Support\OrderPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tunnel d'achat avec point relais : frais relais ajoutés au total, répartis
 * commerçant / plateforme, vendeur inchangé, code de retrait généré.
 */
class RelayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('pricing.protection_rate', 0.10);
        config()->set('pricing.protection_floor', 0.50);
        config()->set('pricing.protection_cap', 15.00);
        config()->set('pricing.relay_fee', 3.00);
        config()->set('pricing.relay_merchant_fee', 1.00);
        config()->set('features.relay_points', true);
    }

    private function spyStripe(): object
    {
        $spy = new class extends StripePaymentIntentService
        {
            public int $lastAmount = -1;

            public array $lastMetadata = [];

            public function create(int $amountCents, array $metadata, array $options = [], string $currency = 'eur'): object
            {
                $this->lastAmount = $amountCents;
                $this->lastMetadata = $metadata;

                return (object) ['id' => 'pi_test_'.uniqid(), 'client_secret' => 'cs_test_secret'];
            }

            public function resolveCustomerId(\App\Models\User $buyer): ?string
            {
                return 'cus_test_'.$buyer->id;
            }
        };

        $this->app->instance(StripePaymentIntentService::class, $spy);

        return $spy;
    }

    private function seller(): User
    {
        return User::create([
            'name' => 'Vendeur', 'email' => 'seller_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
            'stripe_account_id' => 'acct_test', 'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true, 'stripe_details_submitted' => true,
        ]);
    }

    private function buyer(): User
    {
        return User::create([
            'name' => 'Acheteur', 'email' => 'buyer_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
    }

    private function listingAt(float $price, User $seller): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'title' => 'Article', 'price' => $price,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    private function relay(string $territoire = 'La Réunion', bool $active = true): RelayPoint
    {
        return RelayPoint::create([
            'name' => 'Boutique Test', 'territoire' => $territoire,
            'city' => 'Saint-Denis', 'is_active' => $active,
        ]);
    }

    public function test_frais_relais_ajoutes_au_total_et_repartis(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $listing = $this->listingAt(20.00, $seller);
        $relay = $this->relay();
        $spy = $this->spyStripe();

        $response = $this->actingAs($buyer)->post(route('checkout.start', $listing), [
            'delivery_method' => 'relay',
            'relay_point_id' => $relay->id,
        ]);

        $response->assertOk();

        // Attendu : article 20 + protection 2 + relais 3 = 25 €.
        $pricing = OrderPricing::fromEuros(20.00, 0.0, 3.00);
        $this->assertSame(2500, $pricing->totalCents());
        $this->assertSame($pricing->totalCents(), $spy->lastAmount, 'Stripe débite bien le total AVEC frais relais.');
        $this->assertSame($relay->id, (int) ($spy->lastMetadata['relay_point_id'] ?? 0));

        $tx = Transaction::where('listing_id', $listing->id)->latest('id')->first();
        $this->assertNotNull($tx);
        $this->assertSame('relay', $tx->delivery_method);
        $this->assertSame($relay->id, (int) $tx->relay_point_id);
        $this->assertEqualsWithDelta(25.00, (float) $tx->amount, 0.001);
        $this->assertEqualsWithDelta(3.00, (float) $tx->relay_fee, 0.001);
        $this->assertEqualsWithDelta(1.00, (float) $tx->relay_merchant_fee, 0.001);
        // Le vendeur touche toujours le prix article, jamais les frais relais.
        $this->assertEqualsWithDelta(20.00, (float) $tx->seller_amount, 0.001);
        // Part plateforme = frais - part commerçant = 2 €.
        $this->assertEqualsWithDelta(2.00, (float) $tx->relay_fee - (float) $tx->relay_merchant_fee, 0.001);
        $this->assertSame('awaiting_deposit', $tx->relay_status);
        $this->assertSame(6, strlen((string) $tx->relay_pickup_code));
    }

    public function test_point_relais_dun_autre_territoire_refuse(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $listing = $this->listingAt(20.00, $seller);
        $relayAilleurs = $this->relay('Martinique');
        $this->spyStripe();

        $response = $this->actingAs($buyer)->post(route('checkout.start', $listing), [
            'delivery_method' => 'relay',
            'relay_point_id' => $relayAilleurs->id,
        ]);

        $response->assertSessionHasErrors('relay_point_id');
        $this->assertSame(0, Transaction::count());
    }

    public function test_point_relais_inactif_refuse(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $listing = $this->listingAt(20.00, $seller);
        $relayInactif = $this->relay('La Réunion', false);
        $this->spyStripe();

        $response = $this->actingAs($buyer)->post(route('checkout.start', $listing), [
            'delivery_method' => 'relay',
            'relay_point_id' => $relayInactif->id,
        ]);

        $response->assertSessionHasErrors('relay_point_id');
        $this->assertSame(0, Transaction::count());
    }

    public function test_flux_depot_puis_retrait_libere_le_paiement(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $listing = $this->listingAt(20.00, $seller);
        $relay = $this->relay();

        $tx = Transaction::create([
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'amount' => 25, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2,
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
            'delivery_method' => 'relay', 'relay_point_id' => $relay->id, 'relay_fee' => 3,
            'relay_merchant_fee' => 1, 'relay_status' => 'awaiting_deposit', 'relay_pickup_code' => 'ABC234',
            'status' => 'paid', 'shipping_status' => 'pending',
            'stripe_payment_intent_id' => 'pi_test',
        ]);

        // Vendeur confirme le dépôt.
        $this->actingAs($seller)->patch(route('transactions.relay-deposited', $tx))->assertRedirect();
        $tx->refresh();
        $this->assertSame('deposited', $tx->relay_status);
        $this->assertNotNull($tx->relay_deposited_at);
        $this->assertSame('shipped', $tx->shipping_status);

        // Acheteur confirme le retrait -> transaction terminée + collecte datée.
        $this->actingAs($buyer)->patch(route('transactions.received', $tx))->assertRedirect();
        $tx->refresh();
        $this->assertSame('collected', $tx->relay_status);
        $this->assertNotNull($tx->relay_collected_at);
        $this->assertSame('completed', $tx->status);
    }

    public function test_seul_le_vendeur_confirme_le_depot(): void
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $listing = $this->listingAt(20.00, $seller);
        $relay = $this->relay();

        $tx = Transaction::create([
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'amount' => 25, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2,
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
            'delivery_method' => 'relay', 'relay_point_id' => $relay->id, 'relay_fee' => 3,
            'relay_merchant_fee' => 1, 'relay_status' => 'awaiting_deposit', 'relay_pickup_code' => 'ABC234',
            'status' => 'paid', 'shipping_status' => 'pending',
        ]);

        $this->actingAs($buyer)->patch(route('transactions.relay-deposited', $tx))->assertForbidden();
        $tx->refresh();
        $this->assertSame('awaiting_deposit', $tx->relay_status);
    }
}
