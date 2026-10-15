<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DonneesMarche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Données de valeur : chiffres agrégés et anonymes, revendables / montrables
 * à un acheteur de l'entreprise. Aucune ligne de moins de 5 personnes ou
 * annonces, aucune donnée personnelle dans l'export.
 */
class DonneesMarcheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $email, string $ile = 'La Réunion'): User
    {
        return User::create(['name' => 'Membre ' . $email, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => $ile]);
    }

    private function annonce(User $vendeur, float $prix, string $cat = 'femme', string $marque = 'Zara', string $statut = 'published'): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Article', 'description' => 'Description de test assez longue.',
            'price' => $prix, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => $statut,
            'territoire' => $vendeur->territoire, 'category_level1' => $cat, 'marque' => $marque,
        ]);
    }

    private function vente(Listing $annonce, User $acheteur, float $total, float $prix, string $mode = 'cb'): Transaction
    {
        return Transaction::create([
            'listing_id' => $annonce->id, 'seller_id' => $annonce->user_id, 'buyer_id' => $acheteur->id,
            'amount' => $total, 'seller_amount' => $prix, 'commission' => 0, 'buyer_protection_fee' => round($total - $prix, 2),
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => $mode, 'delivery_method' => 'hand_delivery',
            'status' => 'completed', 'shipping_status' => 'hand_delivered',
        ]);
    }

    public function test_indicateurs_de_croissance(): void
    {
        $vendeur = $this->membre('v@ex.com');
        $acheteur = $this->membre('a@ex.com');
        $autre = $this->membre('b@ex.com');
        $a1 = $this->annonce($vendeur, 20, statut: 'sold');
        $a2 = $this->annonce($vendeur, 30, statut: 'sold');
        $a3 = $this->annonce($vendeur, 10, statut: 'sold');
        $this->annonce($vendeur, 40);
        DB::table('listings')->where('id', $a1->id)->update(['created_at' => now()->subDays(4)]);
        DB::table('listings')->where('id', $a2->id)->update(['created_at' => now()->subDays(2)]);

        $this->vente($a1, $acheteur, 22.00, 20.00);
        $this->vente($a2, $acheteur, 33.00, 30.00);
        $this->vente($a3, $autre, 10.00, 10.00, 'especes');

        $c = DonneesMarche::croissance();

        $this->assertSame(55.0, $c['gmv_en_ligne'], 'Seuls les paiements en ligne comptent dans le volume.');
        $this->assertSame(5.0, $c['revenus']);
        $this->assertSame(9.1, $c['take_rate']);
        $this->assertSame(27.5, $c['panier_moyen']);
        $this->assertSame(2, $c['acheteurs']);
        $this->assertSame(50.0, (float) $c['acheteurs_recurrents']);
        $this->assertSame(75.0, (float) $c['taux_vente_annonces']);
        $this->assertSame(3, $c['articles_reemployes']);
        $this->assertSame(2.0, DonneesMarche::delaiMoyenDeVente(), '4, 2 et 0 jours.');
    }

    public function test_par_mois(): void
    {
        $vendeur = $this->membre('v@ex.com');
        $vente = $this->vente($this->annonce($vendeur, 20), $this->membre('a@ex.com'), 22, 20);
        DB::table('transactions')->where('id', $vente->id)->update(['created_at' => '2026-08-10 10:00:00']);

        $mois = collect(DonneesMarche::parMois())->keyBy('mois');

        $this->assertCount(12, $mois);
        $this->assertSame(1, $mois['2026-08']['ventes_en_ligne']);
        $this->assertSame(22.0, $mois['2026-08']['volume_en_ligne']);
        $this->assertSame(2.0, $mois['2026-08']['revenus']);
        $this->assertSame(0, $mois['2026-10']['ventes_en_ligne']);
    }

    public function test_les_petits_groupes_sont_masques(): void
    {
        $vendeur = $this->membre('v@ex.com');
        foreach ([10, 20, 30, 40, 50] as $prix) {
            $this->annonce($vendeur, $prix, 'femme', 'Zara');
        }
        // 4 annonces seulement : catégorie et marque trop petites, masquées.
        foreach ([5, 6, 7, 8] as $prix) {
            $this->annonce($vendeur, $prix, 'sport', 'Decathlon');
        }

        $categories = DonneesMarche::parCategorie();
        $this->assertCount(1, $categories);
        $this->assertSame(5, $categories[0]['annonces']);
        $this->assertSame(30.0, $categories[0]['prix_median']);

        $this->assertSame(['Zara'], array_column(DonneesMarche::marques(), 'marque'));
        $this->assertSame([], DonneesMarche::parIle(), 'Une île avec un seul membre ne s’affiche pas.');
    }

    public function test_demande_non_satisfaite_seulement_si_plusieurs_personnes(): void
    {
        foreach (range(1, 5) as $i) {
            DB::table('search_no_results')->insert(['term' => 'trottinette', 'raw_term' => 'Trottinette', 'visitor_id' => 'v' . $i, 'created_at' => now()]);
        }
        foreach (range(1, 3) as $i) {
            DB::table('search_no_results')->insert(['term' => 'paul dupont', 'raw_term' => 'paul dupont', 'visitor_id' => 'w' . $i, 'created_at' => now()]);
        }

        $this->assertSame([['terme' => 'trottinette', 'recherches' => 5, 'personnes' => 5]], DonneesMarche::demandeNonSatisfaite());
    }

    public function test_l_export_ne_contient_aucune_donnee_personnelle(): void
    {
        $vendeur = $this->membre('vendeuse.secrete@ex.com');
        $acheteur = $this->membre('acheteur.secret@ex.com');
        $this->vente($this->annonce($vendeur, 20), $acheteur, 22, 20);

        $csv = DonneesMarche::csv();

        $this->assertStringContainsString('Croissance mois par mois', $csv);
        $this->assertStringNotContainsString('secret', $csv);
        $this->assertStringNotContainsString('Membre ', $csv);
    }

    public function test_le_bouton_d_export_telecharge_le_csv(): void
    {
        $this->actingAs($this->membre('cabinet@taochyconsulting.fr'));

        \Livewire\Livewire::test(\App\Filament\Pages\DonneesValeur::class)
            ->callAction('csv')
            ->assertFileDownloaded('swapiles-donnees-anonymes-2026-10-15.csv');
    }

    public function test_la_page_admin(): void
    {
        $admin = $this->membre('cabinet@taochyconsulting.fr');

        $this->actingAs($admin)->get('/admin/donnees-valeur')
            ->assertOk()
            ->assertSee('Croissance de l’entreprise', false)
            ->assertSee('Demande non satisfaite')
            ->assertSee('Exporter (CSV anonyme)')
            ->assertSee('Ce qu’on a le droit de partager', false);
    }
}
