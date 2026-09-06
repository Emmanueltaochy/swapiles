<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\ListingDuplicates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Doublons d'annonces : le même formulaire envoyé deux fois ne doit créer
 * qu'une seule annonce, et l'admin doit pouvoir nettoyer les doublons déjà
 * en ligne.
 */
class ListingDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function vendeur(): User
    {
        return User::create([
            'name' => 'Anne-Cé',
            'email' => 'anne' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    private function champs(string $token): array
    {
        return [
            'submission_token' => $token,
            'title' => 'Jolie combi Bleue',
            'description' => 'Combinaison bleue en très bon état, portée deux fois.',
            'listing_type' => 'achat',
            'price' => 7,
            'territoire' => 'La Réunion',
            'category_level1' => 'Mode',
            'pickup_city' => 'Saint-Denis',
            'pickup_postal_code' => '97400',
            'allows_hand_delivery' => '1',
            'images' => [UploadedFile::fake()->image('combi.jpg', 800, 800)],
        ];
    }

    private function annonce(User $vendeur, string $titre, int $prix): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id,
            'title' => $titre,
            'description' => 'Description de test suffisamment longue.',
            'price' => $prix,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'Mode',
        ]);
    }

    public function test_le_meme_formulaire_envoye_deux_fois_ne_cree_qu_une_annonce(): void
    {
        Storage::fake('public');
        Mail::fake();
        Queue::fake();
        $vendeur = $this->vendeur();
        $token = 'jeton-test-doublon';

        $this->actingAs($vendeur)
            ->post(route('account.listings.store'), $this->champs($token))
            ->assertRedirect();

        $this->assertSame(1, Listing::where('user_id', $vendeur->id)->count());

        // Deuxième appui sur « Publier » : même jeton.
        $second = $this->actingAs($vendeur)
            ->post(route('account.listings.store'), $this->champs($token));

        $premiere = Listing::where('user_id', $vendeur->id)->first();
        $second->assertRedirect(route('listings.show', $premiere));

        $this->assertSame(1, Listing::where('user_id', $vendeur->id)->count());
    }

    public function test_deux_annonces_identiques_deposees_separement_restent_possibles(): void
    {
        Storage::fake('public');
        Mail::fake();
        Queue::fake();
        $vendeur = $this->vendeur();

        // Deux passages distincts par le formulaire = deux jetons distincts :
        // un vendeur qui a réellement deux articles identiques n'est pas bloqué.
        $this->actingAs($vendeur)->post(route('account.listings.store'), $this->champs('jeton-a'));
        $this->actingAs($vendeur)->post(route('account.listings.store'), $this->champs('jeton-b'));

        $this->assertSame(2, Listing::where('user_id', $vendeur->id)->count());
    }

    public function test_le_formulaire_porte_un_jeton_anti_doublon(): void
    {
        $vendeur = $this->vendeur();

        $this->actingAs($vendeur)
            ->get(route('account.listings.create'))
            ->assertOk()
            ->assertSee('name="submission_token"', false);
    }

    public function test_les_doublons_sont_regroupes_avec_la_plus_ancienne_conservee(): void
    {
        $vendeur = $this->vendeur();

        $origine = $this->annonce($vendeur, 'Jolie combi Bleue', 7);
        $copie1 = $this->annonce($vendeur, 'Jolie combi Bleue', 7);
        // Casse différente : c'est le même article pour un humain.
        $copie2 = $this->annonce($vendeur, 'jolie combi bleue', 7);

        $groupes = ListingDuplicates::groups();

        $this->assertCount(1, $groupes);
        $this->assertSame($origine->id, $groupes[0]['keep']->id);
        $this->assertEqualsCanonicalizing(
            [$copie1->id, $copie2->id],
            $groupes[0]['copies']->pluck('id')->all()
        );
        $this->assertSame(2, ListingDuplicates::extraCount());
    }

    public function test_deux_vendeurs_differents_ne_forment_pas_un_doublon(): void
    {
        $this->annonce($this->vendeur(), 'Combi', 7);
        $this->annonce($this->vendeur(), 'Combi', 7);

        $this->assertCount(0, ListingDuplicates::groups());
    }

    public function test_un_prix_different_ne_forme_pas_un_doublon(): void
    {
        $vendeur = $this->vendeur();

        $this->annonce($vendeur, 'Combi', 7);
        $this->annonce($vendeur, 'Combi', 9);

        $this->assertCount(0, ListingDuplicates::groups());
    }
}
