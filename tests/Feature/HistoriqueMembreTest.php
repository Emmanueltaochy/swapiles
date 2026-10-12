<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Message;
use App\Models\Transaction;
use App\Models\User;
use App\Support\HistoriqueMembre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fiche membre de l'administration : habitudes et historique d'activité.
 * Usage interne ; le contenu des messages privés n'est jamais affiché.
 */
class HistoriqueMembreTest extends TestCase
{
    use RefreshDatabase;

    private User $lea;

    private User $vendeur;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 15:00:00');

        $this->lea = $this->membre('lea@ex.com', 'Martinique');
        $this->vendeur = $this->membre('vendeur@ex.com');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $email, string $ile = 'La Réunion'): User
    {
        return User::create(['name' => 'M ' . $email, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => $ile]);
    }

    private function annonce(float $prix, string $categorie = 'femme', ?User $vendeur = null): Listing
    {
        return Listing::create([
            'user_id' => ($vendeur ?? $this->vendeur)->id, 'title' => 'Article ' . $prix, 'description' => 'Description de test assez longue.',
            'price' => $prix, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => 'published',
            'territoire' => 'La Réunion', 'category_level1' => $categorie,
        ]);
    }

    private function transaction(User $acheteur, User $vendeur, float $montant, string $statut): Transaction
    {
        return Transaction::create([
            'listing_id' => $this->annonce($montant, 'femme', $vendeur)->id, 'seller_id' => $vendeur->id, 'buyer_id' => $acheteur->id,
            'amount' => $montant, 'seller_amount' => $montant, 'commission' => 0, 'buyer_protection_fee' => 0,
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
            'delivery_method' => 'hand_delivery', 'status' => $statut, 'shipping_status' => 'pending',
        ]);
    }

    public function test_achats_et_ventes_ne_comptent_que_les_paiements_reussis(): void
    {
        $this->transaction($this->lea, $this->vendeur, 12.50, 'paid');
        $this->transaction($this->lea, $this->vendeur, 30.25, 'completed');
        $this->transaction($this->lea, $this->vendeur, 99.00, 'refunded');
        $this->transaction($this->lea, $this->vendeur, 45.00, 'pending');
        $this->transaction($this->vendeur, $this->lea, 18.90, 'completed');
        $this->transaction($this->vendeur, $this->lea, 7.00, 'cancelled');

        $h = HistoriqueMembre::habitudes($this->lea);

        $this->assertSame(['nombre' => 2, 'montant' => 42.75], $h['achats']);
        $this->assertSame(['nombre' => 1, 'montant' => 18.90], $h['ventes']);
    }

    public function test_fourchette_de_budget(): void
    {
        $this->assertNull(HistoriqueMembre::fourchette([]));
        $this->assertSame(['median' => 15.0, 'min' => 15.0, 'max' => 15.0], HistoriqueMembre::fourchette([15.0]));
        $this->assertSame(['median' => 12.5, 'min' => 10.0, 'max' => 15.0], HistoriqueMembre::fourchette([10.0, 15.0]));
        $this->assertSame(['median' => 20.0, 'min' => 10.0, 'max' => 30.0], HistoriqueMembre::fourchette([5.0, 10.0, 20.0, 30.0, 200.0]));
    }

    public function test_budget_et_categories_d_apres_favoris_et_consultations(): void
    {
        $robe = $this->annonce(10, 'femme');
        $jupe = $this->annonce(20, 'femme');
        $velo = $this->annonce(90, 'sport');
        DB::table('favorites')->insert(['user_id' => $this->lea->id, 'listing_id' => $robe->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('listing_consultations')->insert([
            ['user_id' => $this->lea->id, 'listing_id' => $jupe->id, 'vues' => 2, 'derniere_vue_at' => now()],
            ['user_id' => $this->lea->id, 'listing_id' => $velo->id, 'vues' => 1, 'derniere_vue_at' => now()],
        ]);

        $h = HistoriqueMembre::habitudes($this->lea);

        $this->assertSame(['median' => 20.0, 'min' => 10.0, 'max' => 90.0], $h['budget']);
        $this->assertSame(67, $h['categories'][0]['part']);
        $this->assertSame(33, $h['categories'][1]['part']);
    }

    public function test_le_moment_prefere_est_a_l_heure_de_son_ile(): void
    {
        // 02 h à La Réunion (fuseau du serveur) = 18 h la veille en Martinique.
        foreach (range(1, 3) as $i) {
            DB::table('analytics_events')->insert(['user_id' => $this->lea->id, 'path' => '/', 'device' => 'mobile', 'created_at' => now()->subDays($i)->setTime(2, 0)]);
        }

        $h = HistoriqueMembre::habitudes($this->lea);

        $this->assertSame('le soir (18 h – 24 h)', $h['moment_prefere']);
        $this->assertSame(3, $h['jours_actifs']);
        $this->assertSame('Téléphone (site)', $h['appareil']);
    }

    public function test_recherches_recentes_sans_doublon(): void
    {
        foreach (['robe', 'Robe', 'nike air'] as $i => $q) {
            DB::table('analytics_events')->insert(['user_id' => $this->lea->id, 'path' => '/recherche', 'url' => 'https://swapiles.com/recherche?q=' . urlencode($q), 'created_at' => now()->subMinutes(10 - $i)]);
        }

        $this->assertSame(['nike air', 'Robe'], HistoriqueMembre::recherches($this->lea));
    }

    public function test_la_chronologie_ne_montre_jamais_le_contenu_des_messages(): void
    {
        $annonce = $this->annonce(25);
        Message::create(['listing_id' => $annonce->id, 'sender_id' => $this->lea->id, 'receiver_id' => $this->vendeur->id, 'body' => 'Mon numéro secret 0692000000']);
        DB::table('favorites')->insert(['user_id' => $this->lea->id, 'listing_id' => $annonce->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->transaction($this->lea, $this->vendeur, 14.00, 'paid');

        $textes = HistoriqueMembre::chronologie($this->lea)->pluck('texte')->implode("\n");

        $this->assertStringNotContainsString('0692000000', $textes);
        $this->assertStringContainsString('1 message(s) envoyé(s) à propos de « Article 25 »', $textes);
        $this->assertStringContainsString('A mis en favori « Article 25 »', $textes);
        $this->assertStringContainsString('Achat de « Article 14 » · 14,00 € · payé', $textes);
        $this->assertStringContainsString("Inscription sur Swap'Îles", $textes);
    }

    public function test_la_fiche_membre_affiche_habitudes_et_historique(): void
    {
        $admin = $this->membre('cabinet@taochyconsulting.fr');

        $this->actingAs($admin)->get('/admin/users/' . $this->lea->id)
            ->assertOk()
            ->assertSee('Habitudes')
            ->assertSee("Historique d'activité")
            ->assertSee('Inscription sur Swap');
    }
}
