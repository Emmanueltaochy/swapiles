<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\Stories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $nom, string $ile = 'La Réunion'): User
    {
        return User::create(['name' => $nom, 'email' => strtolower($nom) . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => $ile]);
    }

    private function annonce(User $vendeur, string $ile = 'La Réunion', string $statut = 'published'): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe de ' . $vendeur->name . ' ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => $statut, 'listing_type' => 'achat', 'territoire' => $ile,
        ]);
    }

    public function test_les_ronds_suivis_d_abord_puis_l_ile_sans_soi_ni_vieux_articles(): void
    {
        Carbon::setTestNow('2026-10-20 12:00');
        $moi = $this->membre('Moi');
        $suivie = $this->membre('Suivie', 'Martinique');
        $voisine = $this->membre('Voisine');
        $ancienne = $this->membre('Ancienne');
        $ailleurs = $this->membre('Ailleurs', 'Guyane');
        $bloquee = $this->membre('Bloquee');
        $moi->followedSellers()->attach($suivie->id);
        $moi->blockedUsers()->attach($bloquee->id);

        $this->annonce($moi);
        $this->annonce($bloquee);
        $this->annonce($ailleurs, 'Guyane');
        Carbon::setTestNow('2026-10-10 12:00');
        $this->annonce($ancienne);
        Carbon::setTestNow('2026-10-19 12:00');
        $this->annonce($suivie, 'Martinique');
        Carbon::setTestNow('2026-10-20 11:00');
        $this->annonce($voisine);
        $this->annonce($voisine);
        $this->annonce($voisine, 'La Réunion', 'draft');
        Carbon::setTestNow('2026-10-20 12:00');

        $ronds = Stories::pour($moi, 'La Réunion');

        $this->assertSame([$suivie->id, $voisine->id], $ronds->pluck('vendeur.id')->all(),
            'Le dressing suivi (même sur une autre île) passe devant ; ni moi, ni bloqué, ni autre île, ni article de plus de 7 jours.');
        $this->assertSame(2, $ronds[1]['nombre'], 'Les brouillons ne comptent pas.');
        $this->assertFalse($ronds[0]['vu']);
    }

    public function test_vu_puis_vert_a_nouveau_au_prochain_article(): void
    {
        Carbon::setTestNow('2026-10-20 12:00');
        $moi = $this->membre('Moi');
        $lea = $this->membre('Lea');
        $tom = $this->membre('Tom');
        $this->annonce($lea);
        Carbon::setTestNow('2026-10-20 12:05');
        $this->annonce($tom);

        $this->actingAs($moi)->postJson(route('stories.vu', $lea))->assertNoContent();

        $ronds = Stories::pour($moi, 'La Réunion');
        $this->assertSame([$tom->id, $lea->id], $ronds->pluck('vendeur.id')->all(), 'Les ronds déjà vus passent à la fin.');
        $this->assertTrue($ronds[1]['vu']);

        Carbon::setTestNow('2026-10-20 13:00');
        $this->annonce($lea);
        $this->assertFalse(Stories::pour($moi, 'La Réunion')->firstWhere('vendeur.id', $lea->id)['vu'], 'Nouvel article : de nouveau vert.');
    }

    public function test_la_visionneuse_recoit_les_nouveaux_articles(): void
    {
        $lea = $this->membre('Lea');
        $annonce = $this->annonce($lea);
        $annonce->images()->create(['url' => '/storage/listings/robe.jpg', 'order' => 0]);
        $this->annonce($lea, 'La Réunion', 'sold');

        $this->getJson(route('stories.show', $lea))
            ->assertOk()
            ->assertJsonPath('vendeur.nom', 'Lea')
            ->assertJsonCount(1, 'articles')
            ->assertJsonPath('articles.0.titre', $annonce->title)
            ->assertJsonPath('articles.0.prix', '20 €')
            ->assertJsonPath('articles.0.url', route('listings.show', $annonce));

        // Marquer « vu » demande d'être connecté.
        $this->postJson(route('stories.vu', $lea))->assertUnauthorized();
    }

    public function test_un_dressing_bloque_ou_banni_reste_invisible(): void
    {
        $moi = $this->membre('Moi');
        $lea = $this->membre('Lea');
        $this->annonce($lea);
        $lea->blockedUsers()->attach($moi->id);

        $this->actingAs($moi)->getJson(route('stories.show', $lea))->assertNotFound();

        $tom = $this->membre('Tom');
        $this->annonce($tom);
        $tom->forceFill(['is_banned' => true])->save();
        $this->getJson(route('stories.show', $tom))->assertNotFound();
        $this->assertFalse(Stories::pour($moi, 'La Réunion')->contains('vendeur.id', $tom->id));
    }

    public function test_la_barre_s_affiche_sur_l_accueil(): void
    {
        $lea = $this->membre('Lea');
        $this->annonce($lea);

        $this->withCookie('territoire', 'reunion')->get('/')->assertOk()
            ->assertSee('Nouveautés des dressings')
            ->assertSee('data-story-rond="' . $lea->id . '"', false);

        DB::table('listings')->update(['created_at' => now()->subDays(8)]);
        $this->get('/')->assertOk()->assertDontSee('Nouveautés des dressings');
    }
}
