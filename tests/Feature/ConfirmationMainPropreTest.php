<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Support\NotificationsVente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Vente en main propre déclarée : badge « À confirmer » et deux relances à l'acheteur. */
class ConfirmationMainPropreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vente(): array
    {
        $vendeur = User::create(['name' => 'Marie', 'email' => 'marie@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $acheteuse = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
        $annonce = Listing::create(['user_id' => $vendeur->id, 'title' => 'Robe fleurie', 'description' => 'Description de test assez longue.', 'price' => 15, 'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion']);
        $t = Transaction::create([
            'listing_id' => $annonce->id, 'seller_id' => $vendeur->id, 'buyer_id' => $acheteuse->id,
            'amount' => 15, 'seller_amount' => 15, 'commission' => 0, 'currency' => 'EUR',
            'payment_method' => 'especes', 'delivery_method' => 'hand_delivery', 'status' => 'completed', 'shipping_status' => 'hand_delivered',
        ]);

        return [$t, $acheteuse];
    }

    public function test_deux_relances_puis_plus_rien_apres_confirmation(): void
    {
        Carbon::setTestNow('2026-11-01 10:00:00');
        [$t, $acheteuse] = $this->vente();
        $compte = fn () => Notification::where('user_id', $acheteuse->id)->where('type', 'cash_sale_confirm_reminder')->count();

        Carbon::setTestNow('2026-11-02 10:00:00');
        NotificationsVente::envoyerRelances();
        $this->assertSame(0, $compte());

        Carbon::setTestNow('2026-11-03 11:00:00');
        NotificationsVente::envoyerRelances();
        NotificationsVente::envoyerRelances();
        $this->assertSame(1, $compte());

        Carbon::setTestNow('2026-11-07 11:00:00');
        NotificationsVente::envoyerRelances();
        $this->assertSame(2, $compte());

        Carbon::setTestNow('2026-11-20 11:00:00');
        NotificationsVente::envoyerRelances();
        $this->assertSame(2, $compte());

        $t->forceFill(['acheteur_confirme_at' => now()])->save();
        $this->assertNull(NotificationsVente::relanceConfirmationMainPropre($t->fresh()));
    }

    public function test_badge_a_confirmer(): void
    {
        [$t, $acheteuse] = $this->vente();

        $this->actingAs($acheteuse)->get(route('account.transactions.index'))->assertOk()->assertSee('À confirmer');
        $this->actingAs($acheteuse)->get(route('account.dashboard'))->assertOk()->assertSee('1 non lu(s)', false);
    }
}
