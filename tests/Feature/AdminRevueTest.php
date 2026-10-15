<?php

namespace Tests\Feature;

use App\Filament\Pages\SalesByMode;
use App\Http\Middleware\TrackAnalyticsPageView;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRevueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function vente(User $vendeur, User $acheteur, array $plus): Transaction
    {
        $annonce = Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);

        return Transaction::create($plus + [
            'listing_id' => $annonce->id, 'seller_id' => $vendeur->id, 'buyer_id' => $acheteur->id,
            'amount' => 22, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2, 'shipping_fee' => 0,
            'currency' => 'EUR', 'payment_method' => 'cb', 'delivery_method' => 'hand_delivery', 'status' => 'paid',
            'shipping_status' => 'pending',
        ]);
    }

    public function test_ventes_par_mode_separe_la_carte_hors_paiement_en_ligne(): void
    {
        $vendeur = User::create(['name' => 'Léa', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $acheteur = User::create(['name' => 'Tom', 'email' => 'tom@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        $this->vente($vendeur, $acheteur, ['stripe_payment_intent_id' => 'pi_1']);
        $this->vente($vendeur, $acheteur, ['seller_amount' => 30, 'amount' => 30]);
        $this->vente($vendeur, $acheteur, ['payment_method' => 'especes', 'seller_amount' => 10, 'amount' => 10]);

        $donnees = (new SalesByMode)->getViewData();
        $lignes = collect($donnees['rows'])->keyBy('mode');

        $this->assertSame(20.0, (float) $lignes['online']['total_all']);
        $this->assertSame(30.0, (float) $lignes['card_off']['total_all']);
        $this->assertSame(10.0, (float) $lignes['cash']['total_all']);
        // Le tableau et la carte « Sécurisé » disent la même chose.
        $this->assertSame(20.0, (float) $donnees['securedAll']);
        $this->assertSame(60.0, (float) $donnees['totalAll']);
        $this->assertSame(40.0, (float) $donnees['offPlatform']);
    }

    public function test_analyse_avancee_deduit_le_credit_parrainage_des_revenus(): void
    {
        $vendeur = User::create(['name' => 'Léa', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $acheteur = User::create(['name' => 'Tom', 'email' => 'tom@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        // 20 € d'article + 2 € de protection, dont 1 € payé avec le crédit : la carte est débitée de 21 €.
        $this->vente($vendeur, $acheteur, ['stripe_payment_intent_id' => 'pi_1', 'amount' => 21, 'credit_utilise' => 1]);

        $m = AnalyticsMetrics::advanced('30d');

        $this->assertEqualsWithDelta(1.0, $m['netRevenue'], 0.001, 'Protection 2 € − crédit 1 €.');
        $this->assertEqualsWithDelta(22.0, $m['gmv'], 0.001, 'Volume = carte + crédit.');
    }

    public function test_pages_admin_compactees(): void
    {
        $admin = $this->admin();
        $vendeur = User::create(['name' => 'Léa', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $vente = $this->vente($vendeur, $admin, ['stripe_payment_intent_id' => 'pi_1']);
        Message::create(['sender_id' => $admin->id, 'receiver_id' => $vendeur->id, 'listing_id' => $vente->listing_id, 'body' => 'Toujours dispo ?']);

        $this->actingAs($admin);

        $this->get('/admin/messages')->assertOk()->assertSee('Toujours dispo ?')->assertSee('1 message · ● non lu', false);
        $this->get('/admin/reports')->assertOk()->assertSee('Rien pour l’instant', false);
        $this->get('/admin/favorites')->assertOk();
        $this->get('/admin/relay-points')->assertOk()->assertSee('Points relais')->assertDontSee('Points Relais');
        $this->get('/admin/users/' . $vendeur->id)->assertOk()->assertSee('Léa')->assertDontSee('Afficher Léa');

        $this->get('/admin/transactions/' . $vente->id . '/edit')
            ->assertOk()
            ->assertSee('Corriger la vente n° ' . $vente->id)
            ->assertSee('Carte bancaire')
            ->assertDontSee('Supprimer');
    }

    public function test_noms_de_pages_lisibles(): void
    {
        $this->assertSame('Choix de l’île', TrackAnalyticsPageView::libelleRoute('territoire.switch'));
        $this->assertSame('Lives', TrackAnalyticsPageView::libelleRoute('live.show'));
        $this->assertNull(TrackAnalyticsPageView::libelleRoute('inconnu.route'));
    }
}
