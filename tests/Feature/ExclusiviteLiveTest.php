<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\LiveSession;
use App\Models\User;
use App\Support\ExclusiviteLive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExclusiviteLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        Queue::fake();
        config(['live.ouvert_a_tous' => false, 'live.testeurs' => ['testeuse@ex.com']]);
    }

    private function membre(string $email, string $nom = 'Lea'): User
    {
        return User::create(['name' => $nom, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function champs(array $plus = []): array
    {
        return $plus + [
            'submission_token' => 'jeton-' . uniqid(),
            'title' => 'Robe exclusive soie',
            'description' => 'Robe en soie jamais portée, à découvrir en direct.',
            'listing_type' => 'achat',
            'price' => 30,
            'territoire' => 'La Réunion',
            'category_level1' => 'Mode',
            'pickup_city' => 'Saint-Denis',
            'pickup_postal_code' => '97400',
            'allows_hand_delivery' => '1',
            'images' => [UploadedFile::fake()->image('robe.jpg', 800, 800)],
        ];
    }

    public function test_option_reservee_aux_testeurs_du_live(): void
    {
        $this->actingAs($this->membre('membre@ex.com'))->get(route('account.listings.create'))
            ->assertOk()->assertDontSee('Exclusivité live');

        // Même en forçant le champ, un membre hors test publie normalement.
        $this->post(route('account.listings.store'), $this->champs(['exclusivite_live' => '1']))->assertRedirect();
        $this->assertSame('published', Listing::firstOrFail()->status);

        $this->actingAs($this->membre('testeuse@ex.com'))->get(route('account.listings.create'))
            ->assertOk()->assertSee('Exclusivité live : cachée jusqu’à mon live', false);
    }

    public function test_exclusivite_cachee_partout_sauf_pour_le_vendeur(): void
    {
        $vendeuse = $this->membre('testeuse@ex.com');
        $this->actingAs($vendeuse)->post(route('account.listings.store'), $this->champs(['exclusivite_live' => '1']))->assertRedirect();
        $robe = Listing::firstOrFail();
        $this->assertSame(ExclusiviteLive::STATUT, $robe->status);

        $this->get(route('listings.show', $robe))->assertOk()->assertSee('vous seul voyez cet article');
        $this->get(route('account.dashboard'))->assertOk()->assertSee('🎬 Exclusivité live');

        $curieux = $this->membre('curieux@ex.com', 'Tom');
        $this->actingAs($curieux)->get(route('listings.show', $robe))->assertNotFound();
        $this->get(route('search', ['q' => 'soie']))->assertOk()->assertDontSee('Robe exclusive soie');
        $this->get(route('profiles.show', $vendeuse))->assertOk()->assertDontSee('Robe exclusive soie');
    }

    public function test_ajout_depuis_creer_un_live_puis_devoilement_et_fin_du_live(): void
    {
        $vendeuse = $this->membre('testeuse@ex.com');
        $this->actingAs($vendeuse)->get(route('live.create'))->assertOk()->assertSee('Ajouter une exclusivité');

        // Depuis « Créer un live » : retour sur l'écran, article déjà coché.
        $reponse = $this->post(route('account.listings.store'), $this->champs(['exclusivite_live' => '1', 'pour_live' => '1']));
        $robe = Listing::firstOrFail();
        $reponse->assertRedirect(route('live.create', ['ajoute' => $robe->id]));
        $this->get(route('live.create', ['ajoute' => $robe->id]))->assertOk()->assertSee('🎬 Exclusivité');

        $this->post(route('account.listings.store'), $this->champs(['exclusivite_live' => '1', 'title' => 'Sac exclusif raphia']));
        $sac = Listing::where('title', 'Sac exclusif raphia')->firstOrFail();

        $this->post(route('live.store'), ['titre' => 'Nouveautés du jeudi', 'articles' => [$robe->id, $sac->id]])->assertRedirect();
        $live = LiveSession::firstOrFail();
        $this->post(route('live.demarrer', $live));

        // Dévoilée : en ligne et achetable par les spectateurs.
        $this->postJson(route('live.visibilite', [$live, $robe]), ['visible' => true])->assertOk();
        $this->assertSame('published', $robe->fresh()->status);
        $this->assertSame(ExclusiviteLive::STATUT, $sac->fresh()->status);

        // Fin du live : celle qui n'a pas été dévoilée passe en ligne.
        $this->post(route('live.terminer', $live))->assertSessionHas('status', fn ($s) => str_contains($s, '1 exclusivité(s) non dévoilée(s)'));
        $this->assertSame('published', $sac->fresh()->status);
    }
}
