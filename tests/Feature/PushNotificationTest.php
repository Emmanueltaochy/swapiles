<?php

namespace Tests\Feature;

use App\Jobs\SendPushBroadcast;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U ' . $email, 'email' => $email, 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    public function test_enregistre_un_jeton_anonyme(): void
    {
        $this->post(route('push.register'), [
            'token' => 'abc-token-123',
            'platform' => 'android',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'abc-token-123',
            'platform' => 'android',
            'user_id' => null,
        ]);
    }

    public function test_enregistre_un_jeton_lie_a_l_utilisateur_connecte(): void
    {
        $user = $this->user('push@ex.com');

        $this->actingAs($user)->post(route('push.register'), [
            'token' => 'tok-user',
            'platform' => 'ios',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'tok-user',
            'user_id' => $user->id,
        ]);
    }

    public function test_re_enregistrer_le_meme_jeton_ne_cree_pas_de_doublon(): void
    {
        $this->post(route('push.register'), ['token' => 'same', 'platform' => 'android'])->assertOk();
        $this->post(route('push.register'), ['token' => 'same', 'platform' => 'android'])->assertOk();

        $this->assertSame(1, DeviceToken::where('token', 'same')->count());
    }

    public function test_jeton_obligatoire(): void
    {
        $this->post(route('push.register'), ['platform' => 'android'])
            ->assertSessionHasErrors('token');
    }

    public function test_non_configure_par_defaut(): void
    {
        $this->assertFalse(FcmService::configured());

        DeviceToken::create(['token' => 'x', 'platform' => 'android']);
        (new SendPushBroadcast('Titre', 'Corps'))->handle(app(FcmService::class));

        $this->assertDatabaseHas('device_tokens', ['token' => 'x']);
    }

    public function test_envoi_vers_un_jeton_sans_config_renvoie_skipped(): void
    {
        $result = app(FcmService::class)->sendToToken('tok', 'T', 'B', null);

        $this->assertSame('skipped', $result);
    }
}
