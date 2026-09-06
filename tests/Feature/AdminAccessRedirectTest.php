<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un membre ordinaire ne doit JAMAIS se retrouver bloqué sur l'espace
 * d'administration : il était déconnecté et coincé sur un « 403 Forbidden ».
 */
class AdminAccessRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U', 'email' => $email, 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    public function test_un_membre_non_admin_est_renvoye_vers_le_site_et_reste_connecte(): void
    {
        $user = $this->user('membre@ex.com');

        $this->actingAs($user)->get('/admin')
            ->assertRedirect(route('home'));

        // Il ne doit PAS avoir été déconnecté au passage.
        $this->assertAuthenticatedAs($user);
    }

    public function test_apres_connexion_un_non_admin_n_est_pas_renvoye_vers_admin(): void
    {
        $this->user('membre2@ex.com');

        // Destination /admin mémorisée (ex. visite préalable de l'espace admin).
        $response = $this->withSession(['url.intended' => config('app.url') . '/admin'])
            ->post(route('login.store'), [
                'email' => 'membre2@ex.com',
                'password' => 'secret1234',
            ]);

        $response->assertRedirect(route('account.dashboard'));
    }
}
