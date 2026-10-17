<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTransactionsOngletsTest extends TestCase
{
    use RefreshDatabase;

    public function test_onglets_reussies_par_defaut_et_annulees_a_part(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $lea = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $tom = User::create(['name' => 'Tom', 'email' => 'tom@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        foreach (['Robe payee' => 'paid', 'Jupe terminee' => 'completed', 'Sac annule' => 'cancelled', 'Veste attente' => 'pending'] as $titre => $statut) {
            $l = Listing::create(['user_id' => $lea->id, 'title' => $titre, 'description' => 'Description de test assez longue.', 'price' => 20, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion']);
            Transaction::create([
                'listing_id' => $l->id, 'seller_id' => $lea->id, 'buyer_id' => $tom->id,
                'amount' => 22, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2, 'shipping_fee' => 0,
                'currency' => 'EUR', 'payment_method' => 'cb', 'delivery_method' => 'hand_delivery', 'status' => $statut,
                'shipping_status' => 'pending', 'stripe_payment_intent_id' => 'pi_' . uniqid(),
            ]);
        }

        $this->actingAs($admin);
        $this->get('/admin/transactions')->assertOk()
            ->assertSee('Réussies')->assertSee('Annulées')
            ->assertSee('Robe payee')->assertSee('Jupe terminee')->assertDontSee('Sac annule')->assertDontSee('Veste attente');
        $this->get('/admin/transactions?tab=annulees')->assertOk()->assertSee('Sac annule')->assertDontSee('Robe payee');
    }
}
