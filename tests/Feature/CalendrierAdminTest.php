<?php

namespace Tests\Feature;

use App\Filament\Pages\Calendrier;
use App\Models\Listing;
use App\Models\User;
use App\Support\CalendrierStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class CalendrierAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_un_jour_au_clic_avec_ses_chiffres(): void
    {
        Carbon::setTestNow('2026-10-20 18:00');
        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        // Le 14 : deux inscriptions, une annonce, trois visiteurs.
        Carbon::setTestNow('2026-10-14 10:00');
        $lea = User::create(['name' => 'Léa', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        User::create(['name' => 'Tom', 'email' => 'tom@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'Guyane']);
        Listing::create(['user_id' => $lea->id, 'title' => 'Robe', 'description' => 'Description de test assez longue.', 'price' => 10, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => 'published', 'territoire' => 'La Réunion']);
        foreach (['a', 'b', 'c'] as $cle) {
            DB::table('visiteurs_jours')->insert(['jour' => '2026-10-14', 'cle' => $cle, 'appli' => $cle === 'a']);
        }
        Carbon::setTestNow('2026-10-20 18:00');

        $mois = CalendrierStats::mois(Carbon::parse('2026-10-01'));
        $this->assertSame(['visiteurs' => 3, 'inscriptions' => 2, 'annonces' => 1, 'ventes' => 0], $mois['2026-10-14']);
        $this->assertNull($mois['2026-10-25']['visiteurs'], 'Jour à venir : rien.');
        $this->assertNull($mois['2026-10-01']['visiteurs'], 'Avant le comptage fiable : rien.');

        $this->actingAs($admin);
        Livewire::test(Calendrier::class)
            ->assertSee('Octobre 2026')
            ->call('choisir', '2026-10-14')
            ->assertSee('Mercredi 14 octobre 2026')
            ->assertSee('Léa')
            ->assertSee('dont 1 dans l’appli', false)
            ->call('moisPrecedent')
            ->assertSee('Septembre 2026');
    }
}
