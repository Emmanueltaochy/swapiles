<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\Tendances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnnoncesPopulairesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vendeur(string $nom): User
    {
        return User::create(['name' => $nom, 'email' => strtolower($nom) . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function annonce(User $vendeur, int $vuesDepuisToujours = 0): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'views_count' => $vuesDepuisToujours,
        ]);
    }

    private function populaires(string $visiteur, ?User $moi = null)
    {
        return Tendances::populaires(fn ($q) => $q->where('territoire', 'La Réunion'), $moi, Tendances::graine($visiteur));
    }

    public function test_la_selection_change_selon_le_creneau_et_le_visiteur_mais_reste_stable_pendant_la_visite(): void
    {
        Carbon::setTestNow('2026-10-20 09:00');
        for ($i = 0; $i < 12; $i++) {
            $this->annonce($this->vendeur('Vendeur' . $i), 100 - $i);
        }

        $matin = $this->populaires('visiteur-a')->pluck('id')->all();
        $this->assertCount(8, $matin);
        $this->assertSame($matin, $this->populaires('visiteur-a')->pluck('id')->all(), 'Même visite : même sélection.');

        $differences = 0;
        foreach (['visiteur-b', 'visiteur-c', 'visiteur-d', 'visiteur-e'] as $autre) {
            $differences += $this->populaires($autre)->pluck('id')->all() !== $matin ? 1 : 0;
        }
        $this->assertGreaterThan(0, $differences, 'Un autre visiteur ne voit pas toujours la même chose.');

        $creneaux = 0;
        foreach (['12:00', '15:00', '18:00', '21:00'] as $heure) {
            Carbon::setTestNow('2026-10-20 ' . $heure);
            $creneaux += $this->populaires('visiteur-a')->pluck('id')->all() !== $matin ? 1 : 0;
        }
        $this->assertGreaterThan(0, $creneaux, 'Plus tard dans la journée, la sélection change.');
    }

    public function test_la_popularite_recente_compte_plus_que_le_compteur_depuis_toujours(): void
    {
        Carbon::setTestNow('2026-10-20 09:00');
        $ancienne = $this->annonce($this->vendeur('Ancien'), 5000);
        $tendance = $this->annonce($this->vendeur('Nouveau'));
        for ($i = 0; $i < 9; $i++) {
            $this->annonce($this->vendeur('Autre' . $i));
        }

        // Très vue il y a un mois, plus rien depuis.
        DB::table('listing_vues_jours')->insert(['listing_id' => $ancienne->id, 'jour' => '2026-09-15', 'vues' => 5000]);
        // Vue 40 fois cette semaine.
        Tendances::noterVue($tendance);
        DB::table('listing_vues_jours')->where('listing_id', $tendance->id)->update(['vues' => 40]);

        $sorties = ['tendance' => 0, 'ancienne' => 0];
        foreach (range(1, 40) as $n) {
            $selection = $this->populaires('visiteur-' . $n);
            $sorties['tendance'] += $selection->contains('id', $tendance->id) ? 1 : 0;
            $sorties['ancienne'] += $selection->contains('id', $ancienne->id) ? 1 : 0;
        }
        $this->assertGreaterThanOrEqual(34, $sorties['tendance'], 'L’annonce tendance de la semaine sort presque à chaque fois.');
        $this->assertLessThan($sorties['tendance'], $sorties['ancienne'], 'Le vieux compteur ne garde plus la tête.');

        $this->assertSame(1, Tendances::nettoyer(), 'Les vues de plus de 30 jours sont effacées.');
    }

    public function test_variete_et_jamais_ses_propres_annonces(): void
    {
        $gros = $this->vendeur('Gros');
        for ($i = 0; $i < 10; $i++) {
            $this->annonce($gros, 1000);
        }
        $moi = $this->vendeur('Moi');
        $this->annonce($moi, 2000);
        foreach (['A', 'B', 'C'] as $nom) {
            $this->annonce($this->vendeur($nom));
        }

        $selection = $this->populaires('visiteur', $moi);

        $this->assertLessThanOrEqual(2, $selection->where('user_id', $gros->id)->count(), 'Au plus 2 annonces par vendeur.');
        $this->assertFalse($selection->contains('user_id', $moi->id), 'Jamais ses propres annonces.');
        $this->assertCount(5, $selection);
    }

    public function test_ouvrir_une_annonce_compte_une_vue_du_jour_et_l_accueil_s_affiche(): void
    {
        $annonce = $this->annonce($this->vendeur('Léa'));

        $this->get(route('listings.show', $annonce))->assertOk();
        $this->assertSame(1, (int) DB::table('listing_vues_jours')->where('listing_id', $annonce->id)->value('vues'));

        $this->get('/')->assertOk()->assertSee('La sélection change au fil de la journée.');
    }

    public function test_onglets_7_jours_et_depuis_toujours_sur_toute_la_plateforme(): void
    {
        $reunion = $this->annonce($this->vendeur('Lea'), 50);
        $star = Listing::create([
            'user_id' => $this->vendeur('Max')->id, 'title' => 'Robe star de Martinique', 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'Martinique', 'views_count' => 900,
        ]);
        $this->annonce($this->vendeur('Zoe'), 0);

        $toujours = Tendances::depuisToujours();
        $this->assertSame([$star->id, $reunion->id], $toujours->pluck('id')->all(), 'Toutes îles, du plus vu au moins vu, sans les annonces jamais vues.');

        $this->get('/')->assertOk()
            ->assertSee('🔥 7 derniers jours')
            ->assertSee('ces 7 derniers jours', false)
            ->assertSee('🏆 Depuis toujours')
            ->assertSeeInOrder(['🏆 N° 1', 'Robe star de Martinique', '900 vues au total', '📍 Martinique']);
    }
}
