<?php

namespace Tests\Feature;

use App\Filament\Pages\PushBroadcast;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le bouton « Envoyer un test » de l'administration ne doit JAMAIS toucher
 * les membres : il partait aux 10 derniers appareils enregistrés, donc sur
 * les téléphones de vrais utilisateurs.
 */
class PushTestButtonTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $email): User
    {
        return User::create([
            'name' => 'Membre',
            'email' => $email,
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    private function appareil(User $membre, string $suffixe): DeviceToken
    {
        return DeviceToken::create([
            'user_id' => $membre->id,
            'token' => 'fMEQ:APA91b' . $suffixe . str_repeat('x', 40),
            'platform' => 'android',
            'last_seen_at' => now(),
        ]);
    }

    public function test_le_test_ne_part_que_sur_les_appareils_de_l_administrateur(): void
    {
        Http::fake(['*' => Http::response(['name' => 'ok'], 200)]);

        $admin = $this->membre('cabinet@taochyconsulting.fr');
        $monTelephone = $this->appareil($admin, 'admin');

        $membres = collect(range(1, 12))->map(fn ($i) => $this->appareil($this->membre("m{$i}@ex.com"), "m{$i}"));

        $this->actingAs($admin);
        Livewire::test(PushBroadcast::class)->call('testerEnvoi');

        $this->assertNotNull($monTelephone->fresh()->last_sent_at, 'Le téléphone de l’administrateur reçoit le test.');

        foreach ($membres as $appareil) {
            $this->assertNull($appareil->fresh()->last_sent_at, 'Un membre a reçu la notification de test.');
        }
    }

    public function test_sans_appareil_a_son_nom_rien_ne_part(): void
    {
        Http::fake(['*' => Http::response(['name' => 'ok'], 200)]);

        $admin = $this->membre('cabinet@taochyconsulting.fr');
        $appareilMembre = $this->appareil($this->membre('m@ex.com'), 'm');

        $this->actingAs($admin);
        Livewire::test(PushBroadcast::class)
            ->call('testerEnvoi')
            ->assertNotified('Aucun appareil à votre nom');

        $this->assertNull($appareilMembre->fresh()->last_sent_at);
        Http::assertNothingSent();
    }
}
