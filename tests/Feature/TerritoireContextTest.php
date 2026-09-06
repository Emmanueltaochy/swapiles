<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TerritoireContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug corrigé : un membre de La Réunion se retrouvait sur une autre île.
 * Le cookie de choix d'île (1 an) était partagé par tous les comptes utilisés
 * sur le même appareil et écrasait l'île du profil.
 */
class TerritoireContextTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $territoire, string $email = 'membre@swapiles.test'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make('motdepasse123'),
            'territoire' => $territoire,
        ]);
    }

    public function test_la_connexion_recale_le_cookie_sur_l_ile_du_profil(): void
    {
        $this->membre('La Réunion');

        // Cookie laissé par un autre compte sur le même appareil.
        $response = $this->withUnencryptedCookie(TerritoireContext::COOKIE, 'Martinique')
            ->post('/connexion', [
                'email' => 'membre@swapiles.test',
                'password' => 'motdepasse123',
            ]);

        $response->assertCookie(TerritoireContext::COOKIE, 'La Réunion');
    }

    public function test_l_accueil_affiche_l_ile_du_profil_apres_connexion(): void
    {
        $this->membre('La Réunion');

        $this->post('/connexion', [
            'email' => 'membre@swapiles.test',
            'password' => 'motdepasse123',
        ]);

        // Le cookie recalé par la connexion est réutilisé par la requête suivante.
        $this->get('/')->assertOk()->assertSee('La Réunion');
    }

    public function test_un_membre_sans_ile_ne_garde_pas_le_choix_d_un_autre_compte(): void
    {
        $this->membre('', 'sansile@swapiles.test');

        $response = $this->withUnencryptedCookie(TerritoireContext::COOKIE, 'Mayotte')
            ->post('/connexion', [
                'email' => 'sansile@swapiles.test',
                'password' => 'motdepasse123',
            ]);

        // Cookie effacé (valeur vide + expiration passée) : on retombe sur le profil.
        $response->assertCookieExpired(TerritoireContext::COOKIE);
    }

    public function test_le_changement_d_ile_reste_actif_pour_la_navigation(): void
    {
        $this->get(route('territoire.switch', 'martinique'))
            ->assertRedirect(route('home'))
            ->assertCookie(TerritoireContext::COOKIE, 'Martinique');
    }

    public function test_resolve_donne_la_priorite_au_cookie_puis_au_profil(): void
    {
        $membre = $this->membre('Guadeloupe');

        // Visiteur sans cookie : île par défaut.
        $request = \Illuminate\Http\Request::create('/');
        $this->assertSame('La Réunion', TerritoireContext::resolve($request));
        $this->assertFalse(TerritoireContext::isKnown($request));

        // Membre connecté sans cookie : son île de profil.
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $membre);
        $this->assertSame('Guadeloupe', TerritoireContext::resolve($request));
        $this->assertTrue(TerritoireContext::isKnown($request));

        // Choix de navigation en cours : il prime.
        $request = \Illuminate\Http\Request::create('/', 'GET', [], [TerritoireContext::COOKIE => 'Guyane']);
        $request->setUserResolver(fn () => $membre);
        $this->assertSame('Guyane', TerritoireContext::resolve($request));
    }

    public function test_un_cookie_invalide_est_ignore(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', [], [TerritoireContext::COOKIE => 'Paris']);

        $this->assertSame('La Réunion', TerritoireContext::resolve($request));
        $this->assertFalse(TerritoireContext::isKnown($request));
    }

    public function test_la_mise_a_jour_du_profil_recale_le_cookie(): void
    {
        $membre = $this->membre('La Réunion');

        $response = $this->actingAs($membre)
            ->withUnencryptedCookie(TerritoireContext::COOKIE, 'La Réunion')
            ->put(route('account.profile.update'), [
                'name' => $membre->name,
                'territoire' => 'Martinique',
                'country_code' => 'FR',
            ]);

        $response->assertCookie(TerritoireContext::COOKIE, 'Martinique');
        $this->assertSame('Martinique', $membre->fresh()->territoire);
    }
}
