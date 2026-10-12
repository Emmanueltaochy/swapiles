<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FicheTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tableau de bord d'une vente dans l'administration.
 * `amount` = total payé par l'acheteur (prix + protection + livraison + relais).
 */
class FicheTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $vendeur;

    private User $acheteur;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 15:00:00');
        $this->vendeur = $this->membre('vendeur@ex.com', 'Marie Vendeuse');
        $this->acheteur = $this->membre('acheteur@ex.com', 'Paul Acheteur');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $email, string $nom): User
    {
        return User::create(['name' => $nom, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function vente(array $champs = []): Transaction
    {
        $annonce = Listing::create([
            'user_id' => $this->vendeur->id, 'title' => 'Sac à main', 'description' => 'Description de test assez longue.',
            'price' => 12, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => 'sold', 'territoire' => 'La Réunion',
        ]);

        return Transaction::create($champs + [
            'listing_id' => $annonce->id, 'seller_id' => $this->vendeur->id, 'buyer_id' => $this->acheteur->id,
            'amount' => 22.70, 'seller_amount' => 12.00, 'commission' => 0, 'buyer_protection_fee' => 1.20,
            'shipping_fee' => 6.50, 'relay_fee' => 3.00, 'relay_merchant_fee' => 1.00, 'currency' => 'EUR',
            'payment_method' => 'cb', 'delivery_method' => 'relay', 'status' => 'paid', 'shipping_status' => 'pending',
            'stripe_payment_intent_id' => 'pi_test',
        ]);
    }

    public function test_repartition_de_l_argent_d_une_vente_en_point_relais(): void
    {
        $a = FicheTransaction::argent($this->vente());

        $this->assertSame(22.70, $a['total_acheteur']);
        $this->assertSame(12.00, $a['pour_le_vendeur']);
        $this->assertSame(1.00, $a['part_commercant']);
        $this->assertSame(2.00, $a['part_relais_swapiles']);
        // Revenu = protection 1,20 + part relais 2,00.
        $this->assertSame(3.20, $a['revenu_swapiles']);
        // Stripe : 22,70 × 1,5 % = 0,34 + 0,25 = 0,59.
        $this->assertSame(0.59, $a['frais_stripe_estimes']);
        $this->assertSame(2.61, $a['marge_estimee']);
        $this->assertSame(0.0, $a['ecart']);
    }

    public function test_commission_vendeur(): void
    {
        $a = FicheTransaction::argent($this->vente([
            'amount' => 21.50, 'seller_amount' => 18.00, 'commission' => 2.00, 'buyer_protection_fee' => 1.50,
            'shipping_fee' => 0, 'relay_fee' => 0, 'relay_merchant_fee' => 0, 'delivery_method' => 'hand_delivery',
        ]));

        $this->assertSame(20.00, $a['prix_article']);
        $this->assertSame(3.50, $a['revenu_swapiles']);
        $this->assertSame(0.0, $a['ecart']);
    }

    public function test_un_montant_incoherent_est_signale(): void
    {
        $vente = $this->vente(['amount' => 25.00]);

        $this->assertSame(2.30, FicheTransaction::argent($vente)['ecart']);
        $this->assertStringContainsString('écart de 2,30 €', FicheTransaction::alertes($vente)[0]['texte']);
    }

    public function test_une_vente_declaree_ne_rapporte_rien_et_n_a_pas_de_frais_stripe(): void
    {
        $a = FicheTransaction::argent($this->vente([
            'amount' => 40, 'seller_amount' => 40, 'buyer_protection_fee' => 0, 'shipping_fee' => 0, 'relay_fee' => 0,
            'relay_merchant_fee' => 0, 'payment_method' => 'especes', 'delivery_method' => 'hand_delivery',
            'status' => 'completed', 'stripe_payment_intent_id' => null,
        ]));

        $this->assertSame(0.0, $a['revenu_swapiles']);
        $this->assertSame(0.0, $a['frais_stripe_estimes']);
        $this->assertFalse($a['en_ligne']);
    }

    public function test_etapes(): void
    {
        $vente = $this->vente();
        $this->assertSame('Payée, à expédier par le vendeur', FicheTransaction::etape($vente)['libelle']);

        $vente->forceFill(['relay_deposited_at' => now()])->save();
        $this->assertSame('Au point relais, en attente de retrait', FicheTransaction::etape($vente)['libelle']);

        $vente->forceFill(['received_at' => now(), 'status' => 'completed'])->save();
        $this->assertSame('Reçue, versement au vendeur en attente', FicheTransaction::etape($vente)['libelle']);

        $vente->forceFill(['transferred_at' => now()])->save();
        $this->assertSame('Terminée, vendeur payé', FicheTransaction::etape($vente->fresh())['libelle']);

        $vente->forceFill(['status' => 'refunded'])->save();
        $this->assertSame('Remboursée', FicheTransaction::etape($vente)['libelle']);
    }

    public function test_une_vente_non_expediee_depuis_5_jours_est_signalee(): void
    {
        $vente = $this->vente();
        DB::table('transactions')->where('id', $vente->id)->update(['created_at' => now()->subDays(6)]);

        $textes = collect(FicheTransaction::alertes($vente->fresh()))->pluck('texte');

        $this->assertContains('Payée il y a 6 jours et toujours pas expédiée.', $textes);
        $this->assertSame([], FicheTransaction::alertes($this->vente()), 'Une vente du jour ne déclenche rien.');
    }

    public function test_profil_et_conversation(): void
    {
        $vente = $this->vente(['status' => 'completed']);
        $intrus = $this->membre('intrus@ex.com', 'Intrus');
        Message::create(['listing_id' => $vente->listing_id, 'sender_id' => $this->acheteur->id, 'receiver_id' => $this->vendeur->id, 'body' => 'Dispo ?']);
        Message::create(['listing_id' => $vente->listing_id, 'sender_id' => $this->vendeur->id, 'receiver_id' => $this->acheteur->id, 'body' => 'Oui']);
        Message::create(['listing_id' => $vente->listing_id, 'sender_id' => $intrus->id, 'receiver_id' => $this->vendeur->id, 'body' => 'Autre personne']);

        $this->assertSame(['Dispo ?', 'Oui'], FicheTransaction::conversation($vente)->pluck('body')->all());
        $this->assertSame(1, FicheTransaction::profil($this->acheteur)['achats']);
        $this->assertSame(1, FicheTransaction::profil($this->vendeur)['ventes']);
    }

    public function test_le_tableau_de_bord_compte_la_part_relais_dans_les_revenus(): void
    {
        $this->vente();

        $this->assertEqualsWithDelta(3.20, \App\Support\TableauDeBord::periode('30j')['revenus'], 0.001);
    }

    public function test_la_page_admin_de_la_vente(): void
    {
        $admin = $this->membre('cabinet@taochyconsulting.fr', 'Admin');
        $vente = $this->vente();

        $this->actingAs($admin)->get('/admin/transactions/' . $vente->id)
            ->assertOk()
            ->assertSee('Vente n° ' . $vente->id . ' · Sac à main')
            ->assertSee('Marie Vendeuse → Paul Acheteur')
            ->assertSee('Payée, à expédier par le vendeur')
            ->assertSee('22,70 €')
            ->assertSee('3,20 €')
            ->assertSee('Point relais Swap’Îles', false)
            ->assertSee('Parcours de la vente')
            ->assertSee('Déposé au point relais');
    }
}
