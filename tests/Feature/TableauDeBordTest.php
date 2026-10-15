<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TableauDeBord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chiffres du tableau de bord de l'administration (App\Support\TableauDeBord).
 * Montants : seules les ventes payées ou terminées comptent, jamais une vente
 * en attente, remboursée ou annulée.
 */
class TableauDeBordTest extends TestCase
{
    use RefreshDatabase;

    private User $vendeur;

    private User $acheteur;

    private Listing $annonce;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 15:00:00');

        $this->vendeur = $this->membre('vendeur@ex.com');
        $this->acheteur = $this->membre('acheteur@ex.com');
        $this->annonce = Listing::create([
            'user_id' => $this->vendeur->id, 'title' => 'Robe', 'description' => 'Une robe de test assez longue.',
            'price' => 20, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => 'published',
            'territoire' => 'La Réunion', 'category_level1' => 'femme',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $email): User
    {
        return User::create(['name' => 'M', 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function vente(string $statut, float $montant, float $commission, float $protection, string $date = '2026-10-10 10:00:00'): Transaction
    {
        $t = Transaction::create([
            'listing_id' => $this->annonce->id, 'seller_id' => $this->vendeur->id, 'buyer_id' => $this->acheteur->id,
            'amount' => $montant, 'seller_amount' => $montant - $commission, 'commission' => $commission,
            'buyer_protection_fee' => $protection, 'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
            'delivery_method' => 'hand_delivery', 'status' => $statut, 'shipping_status' => 'pending',
        ]);
        DB::table('transactions')->where('id', $t->id)->update(['created_at' => $date]);

        return $t;
    }

    public function test_volume_revenus_et_panier_moyen_ne_comptent_que_les_ventes_payees(): void
    {
        $this->vente('paid', 20.00, 1.00, 1.40);
        $this->vente('completed', 35.50, 1.78, 2.19);
        $this->vente('pending', 100.00, 5.00, 5.70);
        $this->vente('refunded', 50.00, 2.50, 3.20);
        $this->vente('cancelled', 40.00, 2.00, 2.70);

        $p = TableauDeBord::periode('30j');

        $this->assertSame(2, $p['ventes']);
        $this->assertEqualsWithDelta(55.50, $p['volume'], 0.001);
        // Revenus Swap'Îles = protection acheteur + commission.
        $this->assertEqualsWithDelta(1.00 + 1.40 + 1.78 + 2.19, $p['revenus'], 0.001);
        $this->assertEqualsWithDelta(27.75, $p['panier_moyen'], 0.001);
    }

    public function test_la_periode_exclut_les_ventes_plus_anciennes(): void
    {
        $this->vente('completed', 10.00, 0.50, 0.70, '2026-10-11 09:00:00');
        $this->vente('completed', 90.00, 4.50, 5.00, '2026-08-01 09:00:00');

        $this->assertEqualsWithDelta(10.00, TableauDeBord::periode('7j')['volume'], 0.001);
        $this->assertEqualsWithDelta(10.00, TableauDeBord::periode('30j')['volume'], 0.001);
        $this->assertEqualsWithDelta(100.00, TableauDeBord::periode('90j')['volume'], 0.001);
        $this->assertEqualsWithDelta(100.00, TableauDeBord::periode('tout')['volume'], 0.001);
        $this->assertEqualsWithDelta(0.50 + 0.70 + 4.50 + 5.00, TableauDeBord::periode('tout')['revenus'], 0.001);
    }

    public function test_sans_vente_le_panier_moyen_vaut_zero(): void
    {
        $p = TableauDeBord::periode('30j');

        $this->assertSame(0, $p['ventes']);
        $this->assertEquals(0, $p['panier_moyen']);
    }

    public function test_serie_journaliere_et_evolution(): void
    {
        $this->vente('paid', 10, 0, 0, '2026-10-11 09:00:00');
        $this->vente('paid', 10, 0, 0, '2026-10-12 09:00:00');
        $this->vente('completed', 10, 0, 0, '2026-10-12 11:00:00');
        $this->vente('pending', 10, 0, 0, '2026-10-12 12:00:00');

        $serie = TableauDeBord::serieJournaliere(TableauDeBord::ventes(), 3);

        $this->assertSame([0, 1, 2], $serie);
        $this->assertSame(100, TableauDeBord::evolution($serie));
        $this->assertSame(-50, TableauDeBord::evolution([4, 2]));
        $this->assertNull(TableauDeBord::evolution([0, 3]), 'Pas de pourcentage quand hier vaut 0.');
    }

    public function test_evolution_sur_12_mois_par_mois(): void
    {
        $this->vente('paid', 10, 0, 0, '2026-03-15 09:00:00');

        $e = TableauDeBord::evolutionPeriode('12m');

        $this->assertCount(12, $e['labels']);
        $this->assertSame(1, array_sum($e['ventes']));
        $this->assertSame(1, $e['ventes'][4], 'Mars 2026 est le 5e mois en partant de novembre 2025.');
    }

    public function test_a_traiter(): void
    {
        $t = $this->vente('paid', 10, 0, 0);
        $t->forceFill(['auto_review_flagged_at' => now()])->save();
        $this->vente('completed', 10, 0, 0)->forceFill(['auto_review_flagged_at' => now()])->save();

        $this->assertSame(1, TableauDeBord::aTraiter()['ventes_a_verifier'], 'Une vente terminée n’est plus à vérifier.');
    }

    public function test_format_des_euros(): void
    {
        $this->assertSame('12,50 €', TableauDeBord::euros(12.5));
        $this->assertSame('1 235 €', TableauDeBord::euros(1234.6));
        $this->assertSame('0,00 €', TableauDeBord::euros(0));
    }

    public function test_annonces_par_ile(): void
    {
        $this->assertSame(['La Réunion' => 1], TableauDeBord::annoncesParIle());
    }

    public function test_le_tableau_de_bord_s_affiche_pour_l_administrateur(): void
    {
        $this->vente('completed', 35.50, 1.78, 2.19);
        $admin = $this->membre('cabinet@taochyconsulting.fr');

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('Lundi 12 octobre 2026')
            ->assertSee('Statistiques sur une période');
    }

    public function test_le_filtre_de_periode_est_place_juste_au_dessus_de_ce_qu_il_filtre(): void
    {
        $avecFiltre = [\App\Filament\Widgets\Tableau\PeriodeStats::class, \App\Filament\Widgets\Tableau\EvolutionChart::class, \App\Filament\Widgets\Tableau\VisiteursChart::class];

        // Sous le filtre : exactement les widgets qui lisent la période.
        $this->assertSame($avecFiltre, \App\Filament\Pages\Dashboard::WIDGETS_PERIODE);
        foreach (\App\Filament\Pages\Dashboard::WIDGETS_MAINTENANT as $widget) {
            $this->assertNotContains(\Filament\Widgets\Concerns\InteractsWithPageFilters::class, class_uses($widget), $widget . ' ignore la période : il doit rester au-dessus du filtre.');
        }
        foreach ($avecFiltre as $widget) {
            $this->assertContains(\Filament\Widgets\Concerns\InteractsWithPageFilters::class, class_uses($widget));
        }
    }

    public function test_les_badges_d_etat_sont_traduits_et_non_vides(): void
    {
        $this->vente('completed', 35.50, 1.78, 2.19);
        $this->actingAs($this->membre('cabinet@taochyconsulting.fr'));

        // Un paramètre de closure mal nommé ($s au lieu de $state) donnait des badges vides.
        \Livewire\Livewire::test(\App\Filament\Widgets\Tableau\DernieresVentes::class)->assertSee('Terminée');
    }

    public function test_chaque_widget_s_affiche(): void
    {
        $this->vente('completed', 35.50, 1.78, 2.19);
        $this->actingAs($this->membre('cabinet@taochyconsulting.fr'));

        foreach ((new \App\Filament\Pages\Dashboard)->getWidgets() as $widget) {
            \Livewire\Livewire::test($widget, ['pageFilters' => ['periode' => '30j']])->assertOk();
        }

        \Livewire\Livewire::test(\App\Filament\Widgets\Tableau\PeriodeStats::class, ['pageFilters' => ['periode' => '30j']])
            ->assertSee('35,50 €')
            ->assertSee('3,97 €');
    }
}
