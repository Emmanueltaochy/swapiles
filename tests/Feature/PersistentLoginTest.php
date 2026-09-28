<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Connexion persistante.
 *
 * Une cliente revenue 15 minutes plus tard devait se reconnecter. La session
 * seule ne suffit pas : sans mémorisation, le membre est déconnecté dès
 * qu'elle expire. Les applications grand public gardent la connexion.
 */
class PersistentLoginTest extends TestCase
{
    use RefreshDatabase;

    /** Nom du cookie de mémorisation posé par Laravel. */
    private function cookieMemorisation(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    private function membre(): User
    {
        return User::create([
            'name' => 'Cliente',
            'email' => 'cliente@ex.com',
            'password' => Hash::make('motdepasse123'),
            'territoire' => 'La Réunion',
        ]);
    }

    public function test_la_connexion_est_memorisee_par_defaut(): void
    {
        $this->membre();

        $reponse = $this->post('/connexion', [
            'email' => 'cliente@ex.com',
            'password' => 'motdepasse123',
            'remember' => '1',
        ]);

        $reponse->assertRedirect();
        $this->assertAuthenticated();
        $this->assertNotNull(
            $reponse->getCookie($this->cookieMemorisation(), false),
            'Le cookie de mémorisation doit être posé.'
        );
    }

    public function test_le_membre_peut_refuser_la_memorisation(): void
    {
        // Utile sur un ordinateur partagé : la case reste décochable.
        $this->membre();

        $reponse = $this->post('/connexion', [
            'email' => 'cliente@ex.com',
            'password' => 'motdepasse123',
            'remember' => '0',
        ]);

        $this->assertAuthenticated();
        $this->assertNull($reponse->getCookie($this->cookieMemorisation(), false));
    }

    public function test_le_formulaire_propose_de_rester_connecte_case_cochee(): void
    {
        $this->get('/connexion')
            ->assertOk()
            ->assertSee('Rester connecté')
            ->assertSee('value="1" checked', false);
    }

    public function test_une_inscription_reste_connectee(): void
    {
        $reponse = $this->post('/inscription', [
            'name' => 'Nouvelle',
            'email' => 'nouvelle@ex.com',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'territoire' => 'La Réunion',
        ]);

        $reponse->assertRedirect();
        $this->assertAuthenticated();
        $this->assertNotNull($reponse->getCookie($this->cookieMemorisation(), false));
    }

    public function test_le_jeton_de_memorisation_est_enregistre_sur_le_membre(): void
    {
        $membre = $this->membre();

        $this->post('/connexion', [
            'email' => 'cliente@ex.com',
            'password' => 'motdepasse123',
            'remember' => '1',
        ]);

        $this->assertNotNull(
            $membre->fresh()->getRememberToken(),
            'Sans jeton en base, la reconnexion automatique est impossible.'
        );
    }

    public function test_la_duree_de_session_par_defaut_est_longue(): void
    {
        // 30 jours. Une session de deux heures deconnectait les membres entre
        // deux visites. On verifie la valeur de repli du fichier de config :
        // la valeur effective depend de l'environnement, et le deploiement
        // l'ecrit explicitement en production.
        $config = file_get_contents(config_path('session.php'));

        preg_match("/env\('SESSION_LIFETIME',\s*(\d+)\)/", $config, $m);
        $this->assertNotEmpty($m, 'Valeur de repli de SESSION_LIFETIME introuvable.');
        $this->assertGreaterThanOrEqual(43200, (int) $m[1]);

        // Et le deploiement doit la poser en production.
        $this->assertStringContainsString(
            'set-env.sh SESSION_LIFETIME',
            file_get_contents(base_path('.github/workflows/deploy.yml'))
        );
    }
}
