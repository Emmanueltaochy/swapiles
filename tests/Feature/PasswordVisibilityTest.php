<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bouton « afficher / masquer » sur les champs de mot de passe.
 *
 * Saisir un mot de passe à l'aveugle sur un téléphone fait échouer connexions
 * et inscriptions. Le composant s'applique à tous les champs de mot de passe
 * du site, y compris ceux ajoutés plus tard.
 */
class PasswordVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_composant_est_charge_sur_les_pages_d_authentification(): void
    {
        foreach (['/connexion', '/inscription'] as $page) {
            $this->get($page)
                ->assertOk()
                ->assertSee('js/password-eye.js', false);
        }
    }

    public function test_les_pages_d_authentification_ont_bien_un_champ_mot_de_passe(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('type="password"', false);
        $this->get('/inscription')->assertOk()->assertSee('type="password"', false);
    }

    public function test_le_champ_reste_masque_par_defaut(): void
    {
        // Le dévoilement est un choix de l'utilisateur, jamais l'état initial.
        $js = file_get_contents(public_path('js/password-eye.js'));

        $this->assertStringContainsString("aria-label', 'Afficher le mot de passe'", $js);
        $this->assertStringNotContainsString("champ.type = 'text';", $js);
    }

    public function test_le_composant_couvre_aussi_les_champs_ajoutes_apres_coup(): void
    {
        $js = file_get_contents(public_path('js/password-eye.js'));

        $this->assertStringContainsString('MutationObserver', $js);
        $this->assertStringContainsString('input[type="password"]', $js);
    }

    public function test_le_bouton_est_accessible_au_lecteur_d_ecran(): void
    {
        $js = file_get_contents(public_path('js/password-eye.js'));

        $this->assertStringContainsString('aria-pressed', $js);
        $this->assertStringContainsString("bouton.type = 'button'", $js);
    }
}
