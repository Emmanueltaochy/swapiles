<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripePaymentIntentService;
use App\Support\OrderPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutAmountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('pricing.protection_rate', 0.10);
        config()->set('pricing.protection_floor', 0.50);
        config()->set('pricing.protection_cap', 15.00);
    }

    /** Remplace le service Stripe par un espion qui capture le montant envoyé. */
    private function spyStripe(): object
    {
        $spy = new class extends StripePaymentIntentService
        {
            public int $lastAmount = -1;

            public array $lastMetadata = [];

            public array $lastOptions = [];

            public function create(int $amountCents, array $metadata, array $options = [], string $currency = 'eur'): object
            {
                $this->lastAmount = $amountCents;
                $this->lastMetadata = $metadata;
                $this->lastOptions = $options;

                return (object) ['id' => 'pi_test_'.uniqid(), 'client_secret' => 'cs_test_secret'];
            }

            // Item 9 : pas d'appel réseau Stripe en test (Customer simulé).
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
            'name' => 'Vendeur',
            'email' => 'seller_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
            'stripe_account_id' => 'acct_test',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
            'stripe_details_submitted' => true,
        ]);
    }

    private function listingAt(float $price, User $seller): Listing
    {
        return Listing::create([
            'user_id' => $seller->id,
            'title' => 'Article '.$price,
            'price' => $price,
            'status' => 'published',
            'listing_type' => 'achat',
            'territoire' => 'La Réunion',
            'requires_online_payment' => true,
            'allows_hand_delivery' => true,
            'pickup_enabled' => true,
        ]);
    }

    public function test_le_montant_stripe_egale_toujours_le_total_affiche(): void
    {
        // Bornes produit : prix -> (protection, total)
        $cases = [
            [3.00, 0.50, 3.50],
            [5.00, 0.50, 5.50],
            [10.00, 1.00, 11.00],
            [20.00, 2.00, 22.00],
            [150.00, 15.00, 165.00],
            [300.00, 15.00, 315.00],
        ];

        foreach ($cases as [$price, $expectedProtection, $expectedTotal]) {
            $seller = $this->seller();
            $buyer = User::create([
                'name' => 'Acheteur',
                'email' => 'buyer_'.uniqid().'@ex.com',
                'password' => bcrypt('secret1234'),
                'territoire' => 'La Réunion',
            ]);
            $listing = $this->listingAt($price, $seller);

            $spy = $this->spyStripe();

            $response = $this->actingAs($buyer)->post(route('checkout.start', $listing), [
                'delivery_method' => 'hand_delivery',
            ]);

            $response->assertOk();

            $pricing = OrderPricing::fromEuros($price);

            // 1) Cohérence interne avec la table produit.
            $this->assertSame((int) round($expectedProtection * 100), $pricing->protectionCents(), "protection {$price}");
            $this->assertSame((int) round($expectedTotal * 100), $pricing->totalCents(), "total {$price}");

            // 2) LE test critique : montant envoyé à Stripe == total (centimes).
            $this->assertSame($pricing->totalCents(), $spy->lastAmount, "Stripe amount != total pour {$price} €");

            // 3) Le total est bien AFFICHÉ dans la page de paiement (== ce qu'on débite).
            $response->assertSee(number_format($expectedTotal, 2, ',', ' ').' €');

            // 4) Transaction stockée : vendeur = prix, commission 0, total exact.
            $tx = Transaction::where('listing_id', $listing->id)->latest('id')->first();
            $this->assertNotNull($tx);
            $this->assertEqualsWithDelta($price, (float) $tx->seller_amount, 0.001, "seller_amount {$price}");
            $this->assertEqualsWithDelta(0.0, (float) $tx->commission, 0.001);
            $this->assertEqualsWithDelta(0.0, (float) $tx->platform_commission, 0.001);
            $this->assertEqualsWithDelta($expectedTotal, (float) $tx->amount, 0.001, "amount {$price}");

            // 5) Métadonnées : pas de colissimo_delivery_type en main propre.
            $this->assertArrayNotHasKey('colissimo_delivery_type', $spy->lastMetadata);
            $this->assertSame('hand_delivery', $spy->lastMetadata['delivery_method']);

            // 6) Item 9 : reçu e-mail à l'acheteur + Customer rattaché.
            $this->assertSame($buyer->email, $spy->lastOptions['receipt_email'] ?? null, "receipt_email {$price}");
            $this->assertSame('cus_test_'.$buyer->id, $spy->lastOptions['customer'] ?? null, "customer {$price}");
        }
    }
}
