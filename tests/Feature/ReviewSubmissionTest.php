<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Avis mutuels acheteur ↔ vendeur après une transaction terminée.
 */
class ReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U ' . $email, 'email' => $email, 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion', 'rating' => 0,
        ]);
    }

    private function tx(User $seller, ?User $buyer, string $status = 'completed'): Transaction
    {
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Article', 'price' => 20,
            'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);

        return Transaction::create([
            'listing_id' => $listing->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer?->id,
            'amount' => 20, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 0,
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'especes',
            'delivery_method' => 'hand_delivery', 'status' => $status,
        ]);
    }

    public function test_acheteur_note_vendeur_et_recalcule_la_note(): void
    {
        $seller = $this->user('s@ex.com');
        $buyer = $this->user('b@ex.com');
        $tx = $this->tx($seller, $buyer);

        $this->actingAs($buyer)
            ->post(route('account.reviews.store', $tx), ['rating' => 4, 'comment' => 'Nickel'])
            ->assertRedirect();

        $review = Review::first();
        $this->assertNotNull($review);
        $this->assertSame($seller->id, $review->reviewed_id);
        $this->assertSame($buyer->id, $review->reviewer_id);
        $this->assertSame(4, (int) $review->rating);
        $this->assertSame('4.00', (string) $seller->fresh()->rating);
    }

    public function test_note_vendeur_est_la_moyenne_des_avis(): void
    {
        $seller = $this->user('s2@ex.com');
        $b1 = $this->user('b1@ex.com');
        $b2 = $this->user('b2@ex.com');

        $this->actingAs($b1)->post(route('account.reviews.store', $this->tx($seller, $b1)), ['rating' => 5]);
        $this->actingAs($b2)->post(route('account.reviews.store', $this->tx($seller, $b2)), ['rating' => 2]);

        // (5 + 2) / 2 = 3.5
        $this->assertSame('3.50', (string) $seller->fresh()->rating);
    }

    public function test_avis_mutuel_vendeur_note_acheteur(): void
    {
        $seller = $this->user('s3@ex.com');
        $buyer = $this->user('b3@ex.com');
        $tx = $this->tx($seller, $buyer);

        $this->actingAs($seller)
            ->post(route('account.reviews.store', $tx), ['rating' => 5])
            ->assertRedirect();

        $this->assertSame($buyer->id, Review::first()->reviewed_id);
        $this->assertSame('5.00', (string) $buyer->fresh()->rating);
    }

    public function test_transaction_non_terminee_non_notable(): void
    {
        $seller = $this->user('s4@ex.com');
        $buyer = $this->user('b4@ex.com');
        $tx = $this->tx($seller, $buyer, 'paid');

        $this->actingAs($buyer)
            ->post(route('account.reviews.store', $tx), ['rating' => 5])
            ->assertForbidden();

        $this->assertSame(0, Review::count());
    }

    public function test_non_participant_interdit(): void
    {
        $seller = $this->user('s5@ex.com');
        $buyer = $this->user('b5@ex.com');
        $intrus = $this->user('x5@ex.com');
        $tx = $this->tx($seller, $buyer);

        $this->actingAs($intrus)
            ->post(route('account.reviews.store', $tx), ['rating' => 5])
            ->assertForbidden();
    }

    public function test_pas_de_double_avis(): void
    {
        $seller = $this->user('s6@ex.com');
        $buyer = $this->user('b6@ex.com');
        $tx = $this->tx($seller, $buyer);

        $this->actingAs($buyer)->post(route('account.reviews.store', $tx), ['rating' => 5]);
        $this->actingAs($buyer)->post(route('account.reviews.store', $tx), ['rating' => 1]);

        $this->assertSame(1, Review::where('transaction_id', $tx->id)->where('reviewer_id', $buyer->id)->count());
    }

    public function test_vente_cash_sans_acheteur_identifie_bloque_l_avis(): void
    {
        $seller = $this->user('s7@ex.com');
        $tx = $this->tx($seller, null); // buyer_id null

        $this->actingAs($seller)
            ->post(route('account.reviews.store', $tx), ['rating' => 5])
            ->assertSessionHasErrors('rating');

        $this->assertSame(0, Review::count());
    }
}
