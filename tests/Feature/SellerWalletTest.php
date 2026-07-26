<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Support\SellerWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug du solde fictif : une vente en espèces créditait le wallet. On verrouille
 * la règle — SEULES les ventes sécurisées (PaymentIntent Stripe) comptent.
 */
class SellerWalletTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;
    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::create([
            'name' => 'V', 'email' => 's_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $this->buyer = User::create([
            'name' => 'A', 'email' => 'b_'.uniqid().'@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
        $this->listing = Listing::create([
            'user_id' => $this->seller->id, 'title' => 'Sac', 'price' => 3.00,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    private function sale(array $o): Transaction
    {
        return Transaction::create(array_merge([
            'listing_id' => $this->listing->id,
            'seller_id' => $this->seller->id,
            'buyer_id' => $this->buyer->id,
            'amount' => 3.00,
            'commission' => 0,
            'platform_commission' => 0,
            'buyer_protection_fee' => 0,
            'shipping_fee' => 0,
            'seller_amount' => 3.00,
            'currency' => 'EUR',
            'status' => 'completed',
        ], $o));
    }

    public function test_une_vente_en_especes_ne_credite_jamais_le_solde(): void
    {
        // Reproduction exacte du bug : espèces, completed, seller_amount = prix.
        $cash = $this->sale(['payment_method' => 'especes', 'stripe_payment_intent_id' => null]);

        $this->assertFalse($cash->is_secured);
        $balances = SellerWallet::balances(collect([$cash]));
        $this->assertSame(0.0, $balances['pending']);
        $this->assertSame(0.0, $balances['processing']);
        $this->assertSame(0.0, $balances['paid']);
    }

    public function test_seules_les_ventes_en_ligne_alimentent_le_solde(): void
    {
        $online = $this->sale(['payment_method' => 'cb', 'stripe_payment_intent_id' => 'pi_123', 'status' => 'paid']);
        $cash = $this->sale(['payment_method' => 'especes', 'stripe_payment_intent_id' => null]);
        $gift = $this->sale(['payment_method' => 'don', 'seller_amount' => 0, 'stripe_payment_intent_id' => null]);
        $exchange = $this->sale(['payment_method' => 'echange', 'seller_amount' => 0, 'stripe_payment_intent_id' => null]);

        $sales = collect([$online, $cash, $gift, $exchange]);

        // Seul l'online (3 €, statut payé) est dans « en attente ».
        $balances = SellerWallet::balances($sales);
        $this->assertSame(3.0, $balances['pending']);
        $this->assertSame(0.0, $balances['processing']);
        $this->assertSame(0.0, $balances['paid']);

        // securedOnly ne garde que l'online.
        $this->assertCount(1, SellerWallet::securedOnly($sales));
    }

    public function test_recap_mensuel_ventes_vs_securise(): void
    {
        // 47 € de ventes dont 3 € sécurisés : un online 3 €, du cash 44 €.
        $this->sale(['payment_method' => 'cb', 'stripe_payment_intent_id' => 'pi_a', 'seller_amount' => 3.00]);
        $this->sale(['payment_method' => 'especes', 'stripe_payment_intent_id' => null, 'seller_amount' => 44.00, 'amount' => 44.00]);

        $sales = Transaction::where('seller_id', $this->seller->id)->get();
        $now = now();
        $monthly = SellerWallet::monthlyTotals($sales, (int) $now->year, (int) $now->month);

        $this->assertSame(47.0, $monthly['sales']);
        $this->assertSame(3.0, $monthly['secured']);
    }

    public function test_classification_des_modes(): void
    {
        $this->assertSame('online', SellerWallet::mode($this->sale(['payment_method' => 'cb', 'stripe_payment_intent_id' => 'pi_x'])));
        $this->assertSame('cash', SellerWallet::mode($this->sale(['payment_method' => 'especes'])));
        $this->assertSame('gift', SellerWallet::mode($this->sale(['payment_method' => 'don'])));
        $this->assertSame('exchange', SellerWallet::mode($this->sale(['payment_method' => 'echange'])));
    }

    public function test_totaux_par_mode(): void
    {
        $this->sale(['payment_method' => 'cb', 'stripe_payment_intent_id' => 'pi_a', 'seller_amount' => 3.00]);
        $this->sale(['payment_method' => 'especes', 'seller_amount' => 10.00]);
        $this->sale(['payment_method' => 'especes', 'seller_amount' => 5.00]);

        $totals = SellerWallet::totalsByMode(Transaction::where('seller_id', $this->seller->id)->get());

        $this->assertSame(3.0, $totals['online']);
        $this->assertSame(15.0, $totals['cash']);
    }
}
