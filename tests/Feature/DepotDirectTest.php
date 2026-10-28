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
 * Publication depuis la page de dépôt sans rechargement (en-tête X-Depot-Direct) :
 * succès -> adresse de l'annonce, erreur -> JSON, pour que la page garde les photos.
 */
class DepotDirectTest extends TestCase
{
    use RefreshDatabase;

    private function vendeur(): User
    {
        return User::create(['name' => 'Maëva', 'email' => 'maeva' . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function champs(array $autres = []): array
    {
        return array_merge([
            'submission_token' => 'jeton-direct-' . uniqid(),
            'title' => 'Robe jaune',
            'description' => 'Robe jaune en coton, portée deux fois.',
            'listing_type' => 'achat',
            'price' => 15,
            'territoire' => 'La Réunion',
            'category_level1' => 'Mode',
            'pickup_city' => 'Saint-Pierre',
            'pickup_postal_code' => '97410',
            'allows_hand_delivery' => '1',
            'images' => [UploadedFile::fake()->image('robe.jpg', 800, 800)],
        ], $autres);
    }

    private function envoyer(User $vendeur, array $champs)
    {
        return $this->actingAs($vendeur)->post(route('account.listings.store'), $champs, ['X-Depot-Direct' => '1', 'Accept' => 'application/json']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        Queue::fake();
    }

    public function test_succes_renvoie_l_annonce_et_garde_les_messages_pour_la_page_suivante(): void
    {
        $vendeur = $this->vendeur();

        $reponse = $this->envoyer($vendeur, $this->champs())->assertOk();

        $annonce = Listing::where('user_id', $vendeur->id)->sole();
        $reponse->assertJson(['redirect' => route('listings.show', $annonce)]);
        $this->assertSame('Votre annonce a bien été publiée.', session('status'));
        $this->assertTrue(session('just_published'));
        $this->assertSame('Lead', session('pixel_event')['event']);
    }

    public function test_erreur_metier_en_json_sans_rester_en_session(): void
    {
        $vendeur = $this->vendeur();

        $this->envoyer($vendeur, $this->champs(['pickup_city' => '', 'pickup_postal_code' => '']))
            ->assertStatus(422)
            ->assertJsonPath('errors.pickup_city.0', 'Indiquez la ville et le code postal de remise en main propre. Votre adresse exacte ne sera jamais affichée.');

        $this->assertSame(0, Listing::count());
        // Déjà affichée dans la page : l'erreur ne doit pas réapparaître au chargement suivant.
        $this->assertNull(session('errors'));
        $this->assertNull(session('_old_input'));
    }

    public function test_champ_manquant_en_json(): void
    {
        $this->envoyer($this->vendeur(), $this->champs(['title' => '', 'images' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'images']);
    }

    public function test_envoi_classique_inchange(): void
    {
        $vendeur = $this->vendeur();

        $this->actingAs($vendeur)->post(route('account.listings.store'), $this->champs(['pickup_city' => '']))
            ->assertRedirect()
            ->assertSessionHasErrors('pickup_city');

        $this->actingAs($vendeur)->post(route('account.listings.store'), $this->champs())->assertRedirect();
        $this->assertSame(1, Listing::where('user_id', $vendeur->id)->count());
    }

    public function test_page_de_depot_verifie_avant_envoi(): void
    {
        $this->actingAs($this->vendeur())->get(route('account.listings.create'))->assertOk()
            ->assertSee('id="depot-erreurs"', false)
            ->assertSee('novalidate', false)
            ->assertSee('X-Depot-Direct', false);
    }
}
