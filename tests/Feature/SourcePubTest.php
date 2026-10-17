<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\LiensStores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SourcePubTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_lien_d_ile_garde_les_parametres_de_la_pub(): void
    {
        $this->get('/territoire/reunion?utm_source=meta&utm_medium=paid&utm_campaign=Inscription_RE&fbclid=AbC123')
            ->assertRedirect(route('home', ['utm_source' => 'meta', 'utm_medium' => 'paid', 'utm_campaign' => 'Inscription_RE', 'fbclid' => 'AbC123']))
            ->assertCookie(\App\Support\TerritoireContext::COOKIE);
    }

    public function test_les_boutons_des_stores_reprennent_la_source_de_la_pub(): void
    {
        config(['deeplinks.app_store_provider_token' => '123456']);
        $this->withCookie('territoire', 'reunion')->get('/?utm_source=meta&utm_medium=paid&utm_campaign=Inscription_RE')->assertOk();

        $this->assertSame(['source' => 'meta', 'medium' => 'paid', 'campaign' => 'Inscription_RE'], session(LiensStores::SESSION));
        $this->assertStringContainsString(rawurlencode('utm_source=meta&utm_medium=paid&utm_campaign=Inscription_RE'), LiensStores::android());
        $this->assertStringContainsString('pt=123456&ct=meta_Inscription_RE&mt=8', LiensStores::ios());

        // Sans pub : la source par défaut du bandeau.
        $this->flushSession();
        $this->assertStringContainsString(rawurlencode('utm_source=swapiles.com&utm_medium=bandeau'), LiensStores::android());
        $this->assertSame(config('deeplinks.stores.ios'), LiensStores::ios());
    }

    public function test_annonce_publiee_envoie_un_evenement_pixel(): void
    {
        Storage::fake('public');
        Mail::fake();
        Queue::fake();
        $lea = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        $this->actingAs($lea)->post(route('account.listings.store'), [
            'submission_token' => 'jeton-pixel', 'title' => 'Robe fleurie', 'description' => 'Robe fleurie portée deux fois, très bon état.',
            'listing_type' => 'achat', 'price' => 12, 'territoire' => 'La Réunion', 'category_level1' => 'femme',
            'pickup_city' => 'Saint-Denis', 'pickup_postal_code' => '97400', 'allows_hand_delivery' => '1',
            'images' => [UploadedFile::fake()->image('robe.jpg', 800, 800)],
        ])->assertSessionHas('pixel_event', ['event' => 'Lead', 'params' => ['content_name' => 'annonce_publiee', 'content_category' => 'femme']]);

        $this->assertSame('published', Listing::firstOrFail()->status);
    }
}
