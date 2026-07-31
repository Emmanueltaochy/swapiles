<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Point 18 — capture de l'acheteur sur une vente en espèces.
 */
class CashSaleBuyerCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U ' . $email, 'email' => $email, 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    private function listing(User $seller): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'title' => 'Vélo', 'price' => 30,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => false, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    public function test_candidats_regroupent_favoris_et_messagers_hors_vendeur(): void
    {
        $seller = $this->user('seller@ex.com');
        $listing = $this->listing($seller);

        $favoriter = $this->user('fav@ex.com');
        $favoriter->favorites()->attach($listing->id);

        $messager = $this->user('msg@ex.com');
        Message::create(['listing_id' => $listing->id, 'sender_id' => $messager->id, 'receiver_id' => $seller->id, 'body' => 'dispo ?']);

        $stranger = $this->user('stranger@ex.com'); // ni favori ni message

        $candidateIds = $listing->cashBuyerCandidates()->pluck('id')->all();

        $this->assertContains($favoriter->id, $candidateIds);
        $this->assertContains($messager->id, $candidateIds);
        $this->assertNotContains($stranger->id, $candidateIds);
        $this->assertNotContains($seller->id, $candidateIds, 'Le vendeur ne peut pas être son propre acheteur.');
    }

    public function test_vente_cash_capte_l_acheteur_choisi(): void
    {
        $seller = $this->user('s2@ex.com');
        $listing = $this->listing($seller);
        $buyer = $this->user('b2@ex.com');
        $buyer->favorites()->attach($listing->id);

        $this->actingAs($seller)
            ->patch(route('account.listings.cash-paid', $listing), ['buyer_id' => $buyer->id])
            ->assertRedirect();

        $tx = Transaction::first();
        $this->assertNotNull($tx);
        $this->assertSame($buyer->id, $tx->buyer_id);
        $this->assertSame('especes', $tx->payment_method);
        $this->assertSame((float) 30, (float) $tx->amount);
        $this->assertSame('sold', $listing->fresh()->status);
    }

    public function test_acheteur_non_candidat_est_rejete(): void
    {
        $seller = $this->user('s3@ex.com');
        $listing = $this->listing($seller);
        $stranger = $this->user('str@ex.com'); // pas favori, pas message

        $this->actingAs($seller)
            ->patch(route('account.listings.cash-paid', $listing), ['buyer_id' => $stranger->id])
            ->assertRedirect();

        $this->assertNull(Transaction::first()->buyer_id, 'Un membre au hasard ne doit pas être rattaché.');
    }

    public function test_vente_cash_sans_acheteur_reste_possible(): void
    {
        $seller = $this->user('s4@ex.com');
        $listing = $this->listing($seller);

        $this->actingAs($seller)
            ->patch(route('account.listings.cash-paid', $listing), [])
            ->assertRedirect();

        $tx = Transaction::first();
        $this->assertNotNull($tx);
        $this->assertNull($tx->buyer_id);
    }
}
