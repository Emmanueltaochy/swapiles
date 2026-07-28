<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Point 19 — KYC différé à la première vente.
 *
 * On vérifie les points sensibles au flux d'argent :
 *  - une annonce CB d'un vendeur SANS KYC est payable (encaissement plateforme) ;
 *  - le flag OFF rétablit l'ancien contrôle strict ;
 *  - à la vente, la notification vendeur devient une sollicitation IBAN avec le
 *    bon montant net, uniquement si le vendeur n'est pas encore payable.
 */
class DeferKycTest extends TestCase
{
    use RefreshDatabase;

    private function seller(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'V', 'email' => 's' . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ], $overrides));
    }

    private function cbListing(User $seller): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'title' => 'Robe', 'price' => 10,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_colissimo' => false,
            'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    public function test_cb_listing_of_non_kyc_seller_is_payable_when_flag_on(): void
    {
        config(['features.defer_kyc' => true]);

        $seller = $this->seller(); // aucun stripe_account_id / KYC
        $listing = $this->cbListing($seller);

        $this->assertTrue($listing->isOnlinePayable(), 'Annonce CB payable sans KYC (KYC différé).');
        $this->assertTrue(
            Listing::onlinePayable()->whereKey($listing->id)->exists(),
            'Le scope onlinePayable inclut l\'annonce sans KYC.'
        );
    }

    public function test_non_kyc_seller_not_payable_when_flag_off(): void
    {
        config(['features.defer_kyc' => false]);

        $seller = $this->seller();
        $listing = $this->cbListing($seller);

        $this->assertFalse($listing->isOnlinePayable(), 'Sans le flag, le KYC complet reste requis.');
        $this->assertFalse(Listing::onlinePayable()->whereKey($listing->id)->exists());
    }

    public function test_sale_notification_solicits_iban_with_net_amount_when_seller_not_payout_ready(): void
    {
        config(['features.defer_kyc' => true]);

        $seller = $this->seller(); // pas de versements activés
        $buyer = $this->seller(['email' => 'b' . uniqid() . '@ex.com']);
        $listing = $this->cbListing($seller);

        $t = Transaction::create([
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'amount' => 11.00, 'seller_amount' => 10.00, 'buyer_protection_fee' => 1.00,
            'shipping_fee' => 0, 'commission' => 0, 'currency' => 'EUR',
            'payment_method' => 'cb', 'delivery_method' => 'hand_delivery', 'status' => 'pending',
        ]);

        $this->assertTrue(TransactionPayment::markPaidOnce($t));

        $notif = Notification::where('user_id', $seller->id)
            ->where('type', 'transaction_paid_seller')->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('IBAN', $notif->title);
        $this->assertStringContainsString('10,00', $notif->message, 'Le montant net (prix affiché) doit apparaître.');
    }

    public function test_sale_notification_is_normal_when_seller_payout_ready(): void
    {
        config(['features.defer_kyc' => true]);

        $seller = $this->seller([
            'stripe_account_id' => 'acct_ok', 'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true, 'stripe_details_submitted' => true,
        ]);
        $buyer = $this->seller(['email' => 'b' . uniqid() . '@ex.com']);
        $listing = $this->cbListing($seller);

        $t = Transaction::create([
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'amount' => 11.00, 'seller_amount' => 10.00, 'buyer_protection_fee' => 1.00,
            'shipping_fee' => 0, 'commission' => 0, 'currency' => 'EUR',
            'payment_method' => 'cb', 'delivery_method' => 'hand_delivery', 'status' => 'pending',
        ]);

        TransactionPayment::markPaidOnce($t);

        $notif = Notification::where('user_id', $seller->id)
            ->where('type', 'transaction_paid_seller')->first();

        $this->assertNotNull($notif);
        $this->assertStringNotContainsString('IBAN', $notif->title);
        $this->assertStringContainsString('Nouvelle vente', $notif->title);
    }
}
