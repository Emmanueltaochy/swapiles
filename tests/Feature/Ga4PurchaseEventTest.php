<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Point 11 : la page de confirmation flashe un événement GA4 « purchase »
 * standard (transaction_id + value + currency + items).
 */
class Ga4PurchaseEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_confirmation_flashe_un_evenement_purchase_ga4(): void
    {
        Queue::fake();

        $seller = User::create(['name' => 'V', 'email' => 's@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $buyer = User::create(['name' => 'A', 'email' => 'a@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Petit sac', 'price' => 3,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
        $tx = Transaction::create([
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'amount' => 3.50, 'buyer_protection_fee' => 0.50, 'shipping_fee' => 0, 'seller_amount' => 3.00,
            'commission' => 0, 'platform_commission' => 0, 'currency' => 'EUR',
            'payment_method' => 'cb', 'status' => 'pending', 'stripe_payment_intent_id' => 'pi_test',
        ]);

        $response = $this->actingAs($buyer)->get(route('checkout.success', $tx));

        $response->assertSessionHas('ga_event');
        $ga = session('ga_event');
        $this->assertSame('purchase', $ga['event']);
        $this->assertSame((string) $tx->id, $ga['params']['transaction_id']);
        $this->assertEqualsWithDelta(3.50, $ga['params']['value'], 0.001);
        $this->assertSame('EUR', $ga['params']['currency']);
        $this->assertNotEmpty($ga['params']['items']);
    }
}
