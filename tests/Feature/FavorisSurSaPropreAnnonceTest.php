<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\DressingLeaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Beaucoup de vendeurs mettent leurs propres annonces en favori pour les
 * retrouver. C'est permis, mais ça ne compte ni dans le ❤️ affiché, ni dans
 * les statistiques, ni dans le classement.
 */
class FavorisSurSaPropreAnnonceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ses_propres_favoris_ne_gonflent_pas_les_compteurs(): void
    {
        $vendeur = User::create(['name' => 'Léa', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $fan = User::create(['name' => 'Tom', 'email' => 'tom@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $robe = Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe', 'description' => 'Description de test assez longue.',
            'price' => 10, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => 'published', 'territoire' => 'La Réunion',
        ]);

        $robe->favoritedBy()->attach([$vendeur->id, $fan->id]);

        // Le favori reste enregistré (le vendeur retrouve son annonce)…
        $this->assertSame(2, $robe->favoritedBy()->count());

        // … mais seul celui du fan compte.
        $this->assertSame(1, Listing::withCount(['favorisRecus as favorited_by_count'])->find($robe->id)->favorited_by_count);
        $this->get(route('listings.show', $robe))->assertOk()->assertSee('<span id="favorite-count">1</span>', false);

        // Classement des dressings : un seul favori compté.
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame(1, DressingLeaderboard::ranked()->firstWhere('user_id', $vendeur->id)->favorites);

        $this->assertSame(1, DB::table('favorites')->join('listings', 'listings.id', '=', 'favorites.listing_id')
            ->whereColumn('favorites.user_id', '!=', 'listings.user_id')->count());
    }
}
