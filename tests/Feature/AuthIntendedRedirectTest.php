<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Améliration UX #1 : après connexion/inscription, revenir sur la page
 * consultée avant (la fiche annonce, presque toujours une intention d'achat).
 */
class AuthIntendedRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_connexion_memorise_la_fiche_precedente(): void
    {
        $listingUrl = url('/annonce/un-joli-sac');

        $this->get('/connexion', ['referer' => $listingUrl]);

        $this->assertEquals($listingUrl, session('url.intended'));
    }

    public function test_ne_memorise_pas_une_page_d_auth(): void
    {
        $this->get('/connexion', ['referer' => url('/inscription')]);

        $this->assertNull(session('url.intended'));
    }

    public function test_connexion_renvoie_sur_la_page_memorisee(): void
    {
        $user = User::create([
            'name' => 'A', 'email' => 'a@ex.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
        $listingUrl = url('/annonce/un-joli-sac');

        $response = $this->withSession(['url.intended' => $listingUrl])
            ->post(route('login.store'), ['email' => 'a@ex.com', 'password' => 'secret1234']);

        $response->assertRedirect($listingUrl);
    }

    public function test_inscription_renvoie_sur_la_page_memorisee(): void
    {
        $listingUrl = url('/annonce/un-joli-sac');

        $response = $this->withSession(['url.intended' => $listingUrl])
            ->post(route('register.store'), [
                'name' => 'Nouveau', 'email' => 'new@ex.com',
                'password' => 'secret1234', 'password_confirmation' => 'secret1234',
                'territoire' => 'La Réunion',
            ]);

        $response->assertRedirect($listingUrl);
    }
}
