<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dépôt d'annonce : upload des photos (correctif « la photo passe 1 fois sur 2 »).
 * Grande photo acceptée (limite 20 Mo) + normalisation JPEG/redimensionnement.
 */
class ListingImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::create([
            'name' => 'V', 'email' => 'v' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
        ]);
    }

    private function basePayload(): array
    {
        return [
            'title' => 'Belle robe',
            'description' => 'Robe portée deux fois, très bon état.',
            'listing_type' => 'achat',
            'price' => 25,
            'territoire' => 'La Réunion',
            'category_level1' => 'Mode',
            'pickup_city' => 'Saint-Denis',
            'pickup_postal_code' => '97400',
            'allows_hand_delivery' => '1',
        ];
    }

    public function test_grande_photo_acceptee_normalisee_en_jpg_et_redimensionnee(): void
    {
        Storage::fake('public');
        Mail::fake();
        Queue::fake();

        $seller = $this->seller();

        $response = $this->actingAs($seller)->post(route('account.listings.store'), $this->basePayload() + [
            'images' => [UploadedFile::fake()->image('photo.jpg', 3000, 2000)],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $listing = Listing::where('title', 'Belle robe')->first();
        $this->assertNotNull($listing);
        $this->assertSame(1, $listing->images()->count());

        $path = str_replace('/storage/', '', $listing->images()->first()->url);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);

        // Redimensionnée : plus grand côté <= 1600 px.
        $size = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(1600, max($size[0], $size[1]));
    }

    public function test_photo_png_est_acceptee(): void
    {
        Storage::fake('public');
        Mail::fake();
        Queue::fake();

        $seller = $this->seller();

        $this->actingAs($seller)->post(route('account.listings.store'), $this->basePayload() + [
            'images' => [UploadedFile::fake()->image('photo.png', 800, 600)],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Listing::where('title', 'Belle robe')->first()->images()->count());
    }

    public function test_format_non_supporte_est_refuse_avec_message(): void
    {
        Storage::fake('public');
        Mail::fake();
        Queue::fake();

        $seller = $this->seller();

        // Fichier .heic factice (non décodable comme image) -> rejeté par la règle image.
        $response = $this->actingAs($seller)->post(route('account.listings.store'), $this->basePayload() + [
            'images' => [UploadedFile::fake()->create('photo.heic', 200, 'image/heic')],
        ]);

        $response->assertSessionHasErrors('images.0');
        $this->assertNull(Listing::where('title', 'Belle robe')->first());
    }
}
