<?php

namespace Tests\Feature;

use App\Filament\Pages\PushBroadcast;
use App\Filament\Pages\PushDiagnostic;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Page d'envoi des notifications : l'essentiel seulement (téléphones prêts,
 * formulaire). Le diagnostic (services, appareils, test vers un compte) a
 * sa propre page dans Système.
 */
class PushAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'A', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        // Prêts : vus récemment. Obsolète : plus vu depuis des mois.
        DeviceToken::create(['token' => str_repeat('a', 64), 'platform' => 'ios', 'last_seen_at' => now()->subDays(2)]);
        DeviceToken::create(['token' => 'fcm-android-1:' . str_repeat('b', 100), 'platform' => 'android', 'last_seen_at' => now()]);
        DeviceToken::create(['token' => 'fcm-android-2:' . str_repeat('c', 100), 'platform' => 'android', 'last_seen_at' => now()->subDays(5)]);
        DeviceToken::create(['token' => 'fcm-android-3:' . str_repeat('d', 100), 'platform' => 'android', 'last_seen_at' => now()->subMonths(4)]);
    }

    public function test_la_page_d_envoi_ne_montre_que_l_essentiel(): void
    {
        $this->actingAs($this->admin)->get(PushBroadcast::getUrl())
            ->assertOk()
            ->assertSeeInOrder(['iPhone / iPad', '1', 'Android', '2', 'Total prêts à recevoir', '3'])
            ->assertSee('Nouvelle notification')
            ->assertSee('Envoyer à tous')
            ->assertDontSee('Pourquoi ce chiffre diffère')
            ->assertDontSee('jetons obsolètes')
            ->assertDontSee('Tester l’envoi', false);
    }

    public function test_le_diagnostic_a_sa_page(): void
    {
        $this->actingAs($this->admin)->get(PushDiagnostic::getUrl())
            ->assertOk()
            ->assertSee('État de l’envoi', false)
            ->assertSee('jetons obsolètes')
            ->assertSee('Tester l’envoi', false);
    }

    public function test_l_apercu_sans_telephone_admin_ne_part_chez_personne(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(PushBroadcast::class)
            ->fillForm(['title' => "Swap'Îles", 'body' => 'Bonjour'])
            ->call('apercu')
            ->assertNotified('Aucun téléphone à votre nom');
    }
}
