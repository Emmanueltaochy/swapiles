<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\Categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Toutes les catégories au dépôt d'une annonce.
 *
 * La navigation proposait onze rayons, mais le formulaire de dépôt n'en
 * offrait que trois (Femme / Homme / Enfant). Le vendeur d'un clavier ou d'un
 * frigo ne pouvait donc pas ranger son annonce là où l'acheteur la cherche — et
 * modifier une annonce déjà rangée en Maison la renvoyait vers ces trois-là.
 */
class ListingFormCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function vendeur(): User
    {
        $vendeur = User::create([
            'name' => 'Vendeur',
            'email' => 'v' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
        $vendeur->forceFill(['email_verified_at' => now()])->save();

        return $vendeur;
    }

    public function test_le_depot_propose_toutes_les_categories(): void
    {
        $html = $this->actingAs($this->vendeur())->get(route('account.listings.create'))
            ->assertOk()->getContent();

        foreach (Categories::niveau1() as $cle => $categorie) {
            $this->assertStringContainsString('<option value="' . $cle . '"', $html, "Catégorie absente du dépôt : {$categorie['label']}");
        }
    }

    public function test_le_javascript_ne_filtre_plus_les_trois_categories_d_origine(): void
    {
        foreach (['create', 'edit'] as $vue) {
            $source = file_get_contents(resource_path("views/account/listings/{$vue}.blade.php"));

            $this->assertStringNotContainsString("['femme','homme','enfant']", $source, "{$vue} : liste en dur restante.");
        }
    }

    public function test_on_peut_publier_un_clavier_en_high_tech(): void
    {
        Storage::fake('public');
        Mail::fake();
        Queue::fake();

        $this->actingAs($this->vendeur())->post(route('account.listings.store'), [
            'submission_token' => 'jeton-' . uniqid(),
            'title' => 'Clavier Bluetooth',
            'description' => 'Clavier pour iPad, très bon état, avec pavé tactile.',
            'listing_type' => 'achat',
            'price' => 40,
            'territoire' => 'La Réunion',
            'category_level1' => 'high-tech',
            'category_level2' => 'informatique',
            'category_level3' => 'claviers-souris',
            'pickup_city' => 'Saint-Denis',
            'pickup_postal_code' => '97400',
            'allows_hand_delivery' => '1',
            'images' => [UploadedFile::fake()->image('clavier.jpg', 800, 800)],
        ])->assertRedirect();

        $annonce = Listing::where('title', 'Clavier Bluetooth')->firstOrFail();

        $this->assertSame('high-tech', $annonce->category_level1);
        $this->assertSame('informatique', $annonce->category_level2);
        $this->assertSame('claviers-souris', $annonce->category_level3);
    }

    public function test_modifier_une_annonce_rangee_en_maison_garde_sa_categorie(): void
    {
        $vendeur = $this->vendeur();
        $annonce = Listing::create([
            'user_id' => $vendeur->id,
            'title' => 'Réfrigérateur',
            'description' => 'Description de test suffisamment longue.',
            'price' => 150,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'maison',
            'category_level2' => 'electromenager',
        ]);

        $html = $this->actingAs($vendeur)->get(route('account.listings.edit', $annonce))
            ->assertOk()->getContent();

        // Avant : la liste ne connaissait pas « maison », le formulaire
        // s'ouvrait sur « Choisir » et le vendeur défaisait le rangement.
        $this->assertMatchesRegularExpression('/<option value="maison"\s+selected/', $html);
    }
}
