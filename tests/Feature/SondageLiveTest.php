<?php

namespace Tests\Feature;

use App\Filament\Pages\DemandesLives;
use App\Models\Notification;
use App\Models\User;
use App\Support\InteretLive;
use App\Support\LiveAcces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Sondage « Aimeriez-vous faire du live shopping ? » et demandes d'accès aux lives. */
class SondageLiveTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $nom = 'Lea', string $email = 'lea@ex.com'): User
    {
        return User::create(['name' => $nom, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
    }

    public function test_le_sondage_s_affiche_puis_disparait_une_fois_repondu(): void
    {
        $lea = $this->membre();

        $this->actingAs($lea)->get('/')->assertOk()->assertSee('Aimeriez-vous faire du live shopping ?');
        $this->actingAs($lea)->postJson(route('live.interet'), ['choix' => 'regarder'])
            ->assertOk()->assertJson(['etape' => 'merci']);

        $this->assertSame('regarder', $lea->fresh()->live_interet);
        $this->actingAs($lea)->get('/')->assertDontSee('Aimeriez-vous faire du live shopping ?');
        // Sur « Mon compte », un lien reste pour demander à organiser des lives.
        $this->actingAs($lea)->get(route('account.dashboard'))->assertSee('Demander à faire des lives');

        // Les visiteurs non connectés ne le voient pas.
        auth()->logout();
        $this->get('/')->assertDontSee('Aimeriez-vous faire du live shopping ?');
    }

    public function test_pas_pour_l_instant_revient_apres_deux_mois(): void
    {
        $lea = $this->membre();
        $this->actingAs($lea)->postJson(route('live.interet'), ['choix' => 'non'])->assertJson(['etape' => 'merci']);
        $this->assertFalse(InteretLive::aDemander($lea->fresh()));

        $lea->forceFill(['live_interet_at' => now()->subDays(61)])->save();
        $this->assertTrue(InteretLive::aDemander($lea->fresh()));
    }

    public function test_demande_pour_organiser_acceptee_depuis_l_admin(): void
    {
        $admin = $this->membre('Emmanuel', 'cabinet@taochyconsulting.fr');
        $lea = $this->membre();

        $this->actingAs($lea)->postJson(route('live.interet'), ['choix' => 'les_deux'])->assertJson(['etape' => 'demande']);
        $this->actingAs($lea)->postJson(route('live.demande'), ['message' => 'Vide-dressing enfant'])->assertJson(['etape' => 'merci']);

        $lea->refresh();
        $this->assertSame('en_attente', $lea->live_demande_statut);
        $this->assertSame('Vide-dressing enfant', $lea->live_demande_message);
        $this->assertFalse(LiveAcces::peutAnimer($lea));
        $this->assertSame(1, Notification::where('user_id', $admin->id)->where('type', 'live_demande')->count());
        $this->actingAs($lea)->get(route('account.dashboard'))->assertSee('en cours d’examen');

        $this->actingAs($admin)->get('/admin/demandes-lives')->assertOk()->assertSee('Lea')->assertSee('Vide-dressing enfant');
        Livewire::test(DemandesLives::class)->call('accepter', $lea->id);

        $lea->refresh();
        $this->assertTrue(LiveAcces::peutAnimer($lea));
        $this->assertSame('acceptee', $lea->live_demande_statut);
        $this->assertSame(1, Notification::where('user_id', $lea->id)->where('type', 'live_demande_acceptee')->count());
    }

    public function test_demande_refusee_et_choix_invalide(): void
    {
        $this->actingAs($this->membre('Emmanuel', 'cabinet@taochyconsulting.fr'));
        $paul = $this->membre('Paul', 'paul@ex.com');
        InteretLive::demander($paul, null);

        Livewire::test(DemandesLives::class)->call('refuser', $paul->id);
        $this->assertSame('refusee', $paul->fresh()->live_demande_statut);
        $this->assertFalse(LiveAcces::peutAnimer($paul->fresh()));

        $this->actingAs($paul)->postJson(route('live.interet'), ['choix' => 'nimporte'])->assertStatus(422);
    }

    public function test_l_admin_voit_qui_a_vote_quoi_et_peut_filtrer(): void
    {
        $admin = $this->membre('Emmanuel', 'cabinet@taochyconsulting.fr');
        $lea = $this->membre('Lea', 'lea@ex.com');
        $paul = $this->membre('Paul', 'paul@ex.com');
        $this->actingAs($lea)->postJson(route('live.interet'), ['choix' => 'regarder']);
        $this->actingAs($paul)->postJson(route('live.interet'), ['choix' => 'non']);

        $this->actingAs($admin)->get('/admin/demandes-lives')->assertOk()
            ->assertSee('Qui a voté quoi (2)')->assertSee('lea@ex.com')->assertSee('paul@ex.com');

        Livewire::test(DemandesLives::class)
            ->call('filtrer', 'regarder')
            ->assertSee('lea@ex.com')->assertDontSee('paul@ex.com')
            ->call('filtrer', 'regarder')
            ->assertSee('paul@ex.com');
    }
}
