<?php

namespace Tests\Feature;

use App\Jobs\SendTransactionStatusEmails;
use App\Models\Listing;
use App\Models\RelayPoint;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contenu des e-mails transactionnels point relais.
 */
class RelayEmailTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U '.$email, 'email' => $email,
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
    }

    private function relayTx(string $code = 'ABC234', ?User $manager = null): Transaction
    {
        $seller = $this->user('s_'.uniqid().'@ex.com');
        $buyer = $this->user('b_'.uniqid().'@ex.com');
        $relay = RelayPoint::create([
            'name' => 'Boutique Kaz', 'territoire' => 'La Réunion',
            'address' => '5 rue Jean', 'postal_code' => '97400', 'city' => 'Saint-Denis',
            'opening_hours' => 'Lun-Sam 9h-18h', 'is_active' => true,
            'manager_user_id' => $manager?->id,
        ]);
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Robe créole', 'price' => 20,
            'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);

        return Transaction::create([
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'amount' => 25, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2,
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
            'delivery_method' => 'relay', 'relay_point_id' => $relay->id, 'relay_fee' => 3,
            'relay_merchant_fee' => 1, 'relay_status' => 'deposited', 'relay_pickup_code' => $code,
            'status' => 'paid', 'shipping_status' => 'shipped',
        ]);
    }

    public function test_email_depot_contient_le_code_et_le_relais_pour_l_acheteur(): void
    {
        $tx = $this->relayTx('X12345');
        $m = SendTransactionStatusEmails::messagesForTransaction($tx, 'relay_deposited');

        $this->assertArrayHasKey('buyer', $m);
        $this->assertStringContainsString('relais', strtolower($m['buyer'][0]));
        $this->assertStringContainsString('X12345', $m['buyer'][1]);
        $this->assertStringContainsString('Boutique Kaz', $m['buyer'][1]);
        $this->assertStringContainsString('Saint-Denis', $m['buyer'][1]);
        // Le vendeur est prévenu du dépôt, sans code.
        $this->assertArrayHasKey('seller', $m);
        $this->assertStringNotContainsString('X12345', $m['seller'][1]);
    }

    public function test_email_achat_relais_notifie_le_commercant(): void
    {
        $manager = $this->user('gerant@ex.com');
        $tx = $this->relayTx('CODE99', $manager);
        $m = SendTransactionStatusEmails::messagesForTransaction($tx, 'paid');

        $this->assertArrayHasKey('merchant', $m);
        $this->assertStringContainsString('colis', strtolower($m['merchant'][1]));
        // Le vendeur est orienté vers le point relais (pas Colissimo).
        $this->assertStringContainsString('point relais', strtolower($m['seller'][1]));
        $this->assertStringNotContainsString('Colissimo', $m['seller'][1]);
    }

    public function test_une_vente_standard_ne_notifie_pas_de_commercant(): void
    {
        $seller = $this->user('ss@ex.com');
        $buyer = $this->user('bb@ex.com');
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Article', 'price' => 20,
            'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
        $tx = Transaction::create([
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'amount' => 22, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2,
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
            'delivery_method' => 'hand_delivery', 'status' => 'paid', 'shipping_status' => 'pending',
        ]);

        $m = SendTransactionStatusEmails::messagesForTransaction($tx, 'paid');
        $this->assertArrayNotHasKey('merchant', $m);
        $this->assertArrayHasKey('seller', $m);
    }
}
