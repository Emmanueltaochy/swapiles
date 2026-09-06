<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Signalement en deux temps (signaler puis bloquer) et fenêtre de partage
 * affichée juste après la publication d'une annonce.
 */
class ReportModalAndShareTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $nom = 'Membre'): User
    {
        return User::create([
            'name' => $nom,
            'email' => strtolower($nom) . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    private function annonce(User $vendeur): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id,
            'title' => 'Basket asics',
            'description' => 'Basket asics en bon état, taille 44 pour homme.',
            'price' => 25,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'Mode',
        ]);
    }

    public function test_le_signalement_d_une_annonce_propose_de_bloquer_le_vendeur(): void
    {
        Queue::fake();

        $vendeur = $this->membre('Vendeur');
        $signaleur = $this->membre('Signaleur');
        $annonce = $this->annonce($vendeur);

        $response = $this->actingAs($signaleur)
            ->postJson(route('reports.listing', $annonce), ['reason' => 'arnaque']);

        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonPath('block.name', 'Vendeur')
            ->assertJsonPath('block.url', route('users.block.toggle', $vendeur));

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $signaleur->id,
            'reportable_type' => Listing::class,
            'reportable_id' => $annonce->id,
            'reason' => 'arnaque',
        ]);
    }

    public function test_le_signalement_d_un_membre_propose_de_le_bloquer(): void
    {
        Queue::fake();

        $cible = $this->membre('Cible');
        $signaleur = $this->membre('Signaleur');

        $this->actingAs($signaleur)
            ->postJson(route('reports.user', $cible), ['reason' => 'harcelement'])
            ->assertOk()
            ->assertJsonPath('block.name', 'Cible');
    }

    public function test_aucun_blocage_propose_si_la_personne_est_deja_bloquee(): void
    {
        Queue::fake();

        $cible = $this->membre('Cible');
        $signaleur = $this->membre('Signaleur');
        $signaleur->blockedUsers()->syncWithoutDetaching([$cible->id]);

        $this->actingAs($signaleur)
            ->postJson(route('reports.user', $cible), ['reason' => 'spam'])
            ->assertOk()
            ->assertJsonPath('block', null);
    }

    public function test_un_motif_invalide_est_refuse(): void
    {
        $cible = $this->membre('Cible');
        $signaleur = $this->membre('Signaleur');

        $this->actingAs($signaleur)
            ->postJson(route('reports.user', $cible), ['reason' => 'nimporte-quoi'])
            ->assertStatus(422);

        $this->assertSame(0, Report::count());
    }

    public function test_on_ne_peut_pas_signaler_sa_propre_annonce(): void
    {
        $vendeur = $this->membre('Vendeur');
        $annonce = $this->annonce($vendeur);

        $this->actingAs($vendeur)
            ->postJson(route('reports.listing', $annonce), ['reason' => 'spam'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertSame(0, Report::count());
    }

    public function test_le_blocage_repond_en_json_depuis_la_fenetre(): void
    {
        $cible = $this->membre('Cible');
        $moi = $this->membre('Moi');

        $this->actingAs($moi)
            ->postJson(route('users.block.toggle', $cible))
            ->assertOk()
            ->assertJson(['ok' => true, 'blocked' => true]);

        $this->assertTrue($moi->fresh()->hasBlocked($cible));
    }

    public function test_la_fenetre_de_partage_apparait_apres_la_publication(): void
    {
        Storage::fake('public');
        Mail::fake();
        Queue::fake();

        $vendeur = $this->membre('Vendeur');

        $this->actingAs($vendeur)
            ->post(route('account.listings.store'), [
                'submission_token' => 'jeton-partage',
                'title' => 'Basket asics',
                'description' => 'Basket asics en bon état, taille 44 pour homme.',
                'listing_type' => 'achat',
                'price' => 25,
                'territoire' => 'La Réunion',
                'category_level1' => 'Mode',
                'pickup_city' => 'Saint-Denis',
                'pickup_postal_code' => '97400',
                'allows_hand_delivery' => '1',
                'images' => [UploadedFile::fake()->image('asics.jpg', 800, 800)],
            ])
            ->assertRedirect()
            ->assertSessionHas('just_published', true);

        $annonce = Listing::where('user_id', $vendeur->id)->firstOrFail();

        $this->actingAs($vendeur)
            ->get(route('listings.show', $annonce))
            ->assertOk()
            ->assertSee('Annonce publiée !')
            ->assertSee('Partagez-la sur vos réseaux', false);
    }

    public function test_la_fenetre_de_signalement_est_presente_sur_le_profil_d_un_membre(): void
    {
        $cible = $this->membre('Cible');
        $moi = $this->membre('Moi');

        $this->actingAs($moi)
            ->get(route('profiles.show', $cible))
            ->assertOk()
            ->assertSee('data-report-modal', false)
            ->assertSee('Contenu signalé', false)
            ->assertSee('Souhaitez-vous aussi bloquer', false);
    }

    public function test_la_fiche_annonce_propose_les_boutons_de_partage(): void
    {
        $annonce = $this->annonce($this->membre('Vendeur'));

        $this->get(route('listings.show', $annonce))
            ->assertOk()
            ->assertSee('data-share-native', false)
            ->assertSee('data-share-copy', false)
            ->assertSee('wa.me', false)
            ->assertSee('facebook.com/sharer', false);
    }
}
