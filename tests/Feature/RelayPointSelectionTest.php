<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\RelayPoint;
use App\Models\User;
use App\Services\StripePaymentIntentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Périmètre des points relais proposés à l'acheteur :
 * surcharge annonce > défaut vendeur > tous les relais actifs de l'île.
 */
class RelayPointSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('features.relay_points', true);
        config()->set('pricing.relay_fee', 3.00);
        config()->set('pricing.relay_merchant_fee', 1.00);
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

    private function listing(User $seller): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'title' => 'Article', 'price' => 20,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    private function relay(string $name): RelayPoint
    {
        return RelayPoint::create([
            'name' => $name, 'territoire' => 'La Réunion', 'city' => 'Saint-Denis', 'is_active' => true,
        ]);
    }

    public function test_sans_choix_tous_les_relais_de_l_ile(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller);
        $a = $this->relay('A');
        $b = $this->relay('B');

        $ids = $listing->effectiveRelayPoints()->pluck('id')->sort()->values()->all();
        $this->assertSame([$a->id, $b->id], $ids);
    }

    public function test_le_defaut_vendeur_restreint_le_perimetre(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller);
        $a = $this->relay('A');
        $this->relay('B');
        $this->relay('C');

        // Le vendeur n'accepte que A.
        $seller->acceptedRelayPoints()->sync([$a->id]);

        $ids = $listing->effectiveRelayPoints()->pluck('id')->all();
        $this->assertSame([$a->id], $ids);
    }

    public function test_la_surcharge_annonce_est_prioritaire_sur_le_defaut(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller);
        $a = $this->relay('A');
        $b = $this->relay('B');

        $seller->acceptedRelayPoints()->sync([$a->id]); // défaut = A
        $listing->relayPoints()->sync([$b->id]);        // surcharge = B

        $ids = $listing->effectiveRelayPoints()->pluck('id')->all();
        $this->assertSame([$b->id], $ids, 'La surcharge annonce prime sur le défaut vendeur.');
    }

    public function test_les_relais_inactifs_sont_exclus(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller);
        $a = $this->relay('A');
        $b = $this->relay('B');
        $b->update(['is_active' => false]);

        // Le vendeur avait coché A et B, mais B est désactivé depuis.
        $seller->acceptedRelayPoints()->sync([$a->id, $b->id]);

        $ids = $listing->effectiveRelayPoints()->pluck('id')->all();
        $this->assertSame([$a->id], $ids);
    }

    public function test_checkout_refuse_un_relais_hors_perimetre(): void
    {
        $seller = $this->seller();
        $buyer = User::create([
            'name' => 'Acheteur', 'email' => 'buyer_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $listing = $this->listing($seller);
        $a = $this->relay('A');
        $b = $this->relay('B');

        // L'annonce n'accepte que A ; l'acheteur tente B.
        $listing->relayPoints()->sync([$a->id]);

        $spy = new class extends StripePaymentIntentService {
            public function create(int $amountCents, array $metadata, array $options = [], string $currency = 'eur'): object
            {
                return (object) ['id' => 'pi_x', 'client_secret' => 'cs_x'];
            }
            public function resolveCustomerId(\App\Models\User $buyer): ?string { return 'cus_x'; }
        };
        $this->app->instance(StripePaymentIntentService::class, $spy);

        $this->actingAs($buyer)->post(route('checkout.start', $listing), [
            'delivery_method' => 'relay', 'relay_point_id' => $b->id,
        ])->assertSessionHasErrors('relay_point_id');

        // Et A (dans le périmètre) passe.
        $this->actingAs($buyer)->post(route('checkout.start', $listing), [
            'delivery_method' => 'relay', 'relay_point_id' => $a->id,
        ])->assertOk();
    }
}
