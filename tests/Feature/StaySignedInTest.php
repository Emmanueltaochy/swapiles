<?php

namespace Tests\Feature;

use App\Http\Middleware\KeepMemberSignedIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Rester connecté, comme sur Instagram.
 *
 * Trois causes de déconnexion relevées dans le code :
 *  - se déconnecter sur un appareil invalidait la mémorisation de TOUS les
 *    autres (Auth::logout change le jeton du compte) ;
 *  - le cookie « Rester connecté » expirait 400 jours après la connexion,
 *    même pour un membre actif tous les jours ;
 *  - une page ouverte depuis des jours tombait sur « 419 Page expirée » au
 *    premier envoi, ce que les membres prenaient pour une déconnexion.
 */
class StaySignedInTest extends TestCase
{
    use RefreshDatabase;

    private function membre(): User
    {
        $membre = User::create([
            'name' => 'Marie',
            'email' => 'marie' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
        $membre->forceFill(['email_verified_at' => now()])->save();

        return $membre;
    }

    private function nomCookie(): string
    {
        return Auth::guard()->getRecallerName();
    }

    public function test_se_deconnecter_ici_ne_deconnecte_pas_les_autres_appareils(): void
    {
        $membre = $this->membre();
        $membre->setRememberToken('jeton-du-telephone');
        $membre->save();

        // Déconnexion depuis l'ordinateur.
        $this->actingAs($membre)->post(route('logout'))->assertRedirect(route('home'));

        // Le jeton qui garde le téléphone connecté n'a pas changé.
        $this->assertSame('jeton-du-telephone', $membre->fresh()->getRememberToken());
        $this->assertGuest();
    }

    public function test_un_membre_actif_voit_sa_memorisation_renouvelee(): void
    {
        $membre = $this->membre();

        $reponse = $this->actingAs($membre)->get(route('search'))->assertOk();

        $cookie = $reponse->getCookie($this->nomCookie(), false);
        $this->assertNotNull($cookie, 'Le cookie « Rester connecté » doit être posé.');

        // Environ 400 jours : la durée maximale qu'acceptent les navigateurs.
        $this->assertGreaterThan(now()->addDays(390)->getTimestamp(), $cookie->getExpiresTime());
    }

    public function test_le_renouvellement_n_a_lieu_qu_une_fois_par_jour(): void
    {
        $membre = $this->membre();

        $premiere = $this->actingAs($membre)->get(route('search'));
        $valeur = $premiere->getCookie($this->nomCookie())->getValue();

        // Dans les tests, l'application n'est pas recréée entre deux requêtes :
        // les cookies mis en file par la première ressortiraient avec la
        // suivante. Sur le serveur, chaque requête repart de zéro.
        $this->app['cookie']->flushQueuedCookies();

        // Même session, même jour, cookie déjà présent : rien à renvoyer.
        $this->withSession([KeepMemberSignedIn::RENOUVELE_LE => now()->toDateString()])
            ->withCookie($this->nomCookie(), $valeur)
            ->get(route('search'))
            ->assertCookieMissing($this->nomCookie());

        // Le lendemain, même session : renouvelé.
        $hier = now()->toDateString();
        $this->app['cookie']->flushQueuedCookies();
        $this->travel(1)->days();
        $this->withSession([KeepMemberSignedIn::RENOUVELE_LE => $hier])
            ->withCookie($this->nomCookie(), $valeur)
            ->get(route('search'))
            ->assertCookie($this->nomCookie());
    }

    public function test_un_compte_sans_jeton_en_recoit_un(): void
    {
        $membre = $this->membre();
        $this->assertEmpty($membre->getRememberToken());

        $this->actingAs($membre)->get(route('search'))->assertOk();

        $this->assertNotEmpty($membre->fresh()->getRememberToken());
    }

    public function test_le_cookie_suffit_a_reconnecter_le_telephone(): void
    {
        // Le cas qui compte : la session a disparu (expirée, effacée), il ne
        // reste au téléphone que son cookie. Il doit être reconnu tout seul.
        $membre = $this->membre();

        $valeur = $this->actingAs($membre)->get(route('search'))
            ->getCookie($this->nomCookie())->getValue();

        Auth::forgetGuards();
        $this->app['session']->flush();

        $this->withCookie($this->nomCookie(), $valeur)
            ->get(route('account.dashboard'))
            ->assertOk();

        $this->assertAuthenticatedAs($membre->fresh());
    }

    public function test_le_choix_de_ne_pas_rester_connecte_est_respecte(): void
    {
        $membre = $this->membre();

        $this->actingAs($membre)
            ->withSession([KeepMemberSignedIn::SANS_MEMORISATION => true])
            ->get(route('search'))
            ->assertCookieMissing($this->nomCookie());
    }

    public function test_la_case_decochee_a_la_connexion_est_memorisee(): void
    {
        $membre = $this->membre();

        $this->post('/connexion', [
            'email' => $membre->email,
            'password' => 'secret1234',
            'remember' => '0',
        ])->assertRedirect();

        $this->assertTrue((bool) session(KeepMemberSignedIn::SANS_MEMORISATION));
    }

    public function test_un_visiteur_ne_recoit_rien(): void
    {
        $this->get(route('search'))->assertOk()->assertCookieMissing($this->nomCookie());
    }

    public function test_un_jeton_frais_est_disponible(): void
    {
        $reponse = $this->get(route('csrf.refresh'))->assertOk();

        $this->assertNotEmpty($reponse->json('jeton'));
        $this->assertStringContainsString('no-store', $reponse->headers->get('Cache-Control'));
    }

    public function test_un_jeton_perime_ne_montre_plus_page_expiree(): void
    {
        Route::middleware('web')->post('/__test-jeton-perime', fn () => throw new TokenMismatchException());

        $this->from(route('search'))
            ->post('/__test-jeton-perime', ['message' => 'Bonjour', 'password' => 'secret'])
            ->assertRedirectContains('/recherche')
            ->assertSessionHas('swp_info')
            // La saisie est conservée… sauf le mot de passe.
            ->assertSessionHasInput('message', 'Bonjour')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_un_jeton_perime_en_javascript_recoit_un_jeton_neuf(): void
    {
        Route::middleware('web')->post('/__test-jeton-perime-js', fn () => throw new TokenMismatchException());

        $reponse = $this->postJson('/__test-jeton-perime-js')->assertStatus(419);

        $this->assertNotEmpty($reponse->json('jeton'));
    }

    public function test_le_message_global_s_affiche(): void
    {
        $this->withSession(['swp_info' => 'Votre page était ouverte depuis longtemps.'])
            ->get(route('search'))
            ->assertSee('Votre page était ouverte depuis longtemps.');
    }

    public function test_le_correctif_android_est_branche_dans_les_builds(): void
    {
        $codemagic = file_get_contents(base_path('codemagic.yaml'));

        // Les deux workflows Android (validation et publication).
        $this->assertSame(2, substr_count($codemagic, 'python3 scripts/android-cookies-flush.py'));
        $this->assertFileExists(base_path('mobile/scripts/android-cookies-flush.py'));
    }
}
