<?php

namespace Tests\Feature;

use App\Jobs\SendTransactionStatusEmails;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Les deux chemins (page de succès + webhook Stripe) appellent markPaidOnce.
 * On garantit : une seule notification vendeur, une seule acheteur, un seul
 * e-mail « paid » — quel que soit le nombre d'appels (fin des deux bugs).
 */
class MarkPaidOnceTest extends TestCase
{
    use RefreshDatabase;

    private function pendingTransaction(): Transaction
    {
        $seller = User::create([
            'name' => 'V', 'email' => 's_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $buyer = User::create([
            'name' => 'A', 'email' => 'b_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Petit sac', 'price' => 3.00,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);

        return Transaction::create([
            'listing_id' => $listing->id,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'amount' => 3.50,
            'buyer_protection_fee' => 0.50,
            'shipping_fee' => 0,
            'seller_amount' => 3.00,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);
    }

    public function test_premier_appel_notifie_et_email_second_appel_noop(): void
    {
        Queue::fake();
        $tx = $this->pendingTransaction();

        // 1er chemin (ex. page de succès) : effectue la transition.
        $this->assertTrue(TransactionPayment::markPaidOnce($tx));

        // 2e chemin (ex. webhook, quasi simultané) : ne refait rien.
        $this->assertFalse(TransactionPayment::markPaidOnce($tx->fresh()));
        // Et un 3e pour être sûr.
        $this->assertFalse(TransactionPayment::markPaidOnce($tx->fresh()));

        // Statut + annonce.
        $this->assertSame('paid', $tx->fresh()->status);
        $this->assertNotNull($tx->fresh()->paid_at);
        $this->assertSame('sold', $tx->listing->fresh()->status);

        // Exactement UNE notification vendeur, UNE acheteur (pas de doublon).
        $this->assertSame(1, Notification::where('user_id', $tx->seller_id)->where('type', 'transaction_paid_seller')->count());
        $this->assertSame(1, Notification::where('user_id', $tx->buyer_id)->where('type', 'transaction_paid_buyer')->count());
        $this->assertSame(2, Notification::count());

        // Exactement UN e-mail « paid » (acheteur + vendeur sont dans le même job).
        Queue::assertPushed(SendTransactionStatusEmails::class, 1);
        Queue::assertPushed(function (SendTransactionStatusEmails $job) use ($tx) {
            return $job->transactionId === $tx->id && $job->event === 'paid';
        });
    }

    public function test_une_transaction_non_pending_n_est_pas_traitee(): void
    {
        Queue::fake();
        $tx = $this->pendingTransaction();
        $tx->update(['status' => 'cancelled']);

        $this->assertFalse(TransactionPayment::markPaidOnce($tx->fresh()));
        $this->assertSame(0, Notification::count());
        Queue::assertNotPushed(SendTransactionStatusEmails::class);
    }
}
