<?php

namespace Tests\Feature;

use App\Jobs\SendPushBroadcast;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Trois ameliorations d'experience :
 *  - brouillon local du formulaire de depot ;
 *  - favoris sans rechargement de page ;
 *  - favoris du jour regroupes en une seule notification.
 */
class UxImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Le push est « configuré » pour que les notifications internes mettent
        // bien un envoi en file — mais la file est simulée : sans cela, le test
        // tenterait un vrai appel reseau vers Google et resterait bloque.
        config(['push.fcm.project_id' => 'swap-iles']);
        Queue::fake();
    }

    private function membre(string $nom = 'Membre'): User
    {
        return User::create([
            'name' => $nom,
            'email' => strtolower($nom) . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    private function annonce(User $vendeur, string $titre = 'Robe fleurie'): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id,
            'title' => $titre,
            'description' => 'Description de test suffisamment longue.',
            'price' => 20,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'femme',
        ]);
    }

    // --- Brouillon du formulaire de depot -----------------------------------

    public function test_le_formulaire_de_depot_sauvegarde_la_saisie(): void
    {
        $vendeur = $this->membre('Vendeur');

        $this->actingAs($vendeur)
            ->get(route('account.listings.create'))
            ->assertOk()
            ->assertSee('data-brouillon="depot-annonce"', false)
            ->assertSee('js/form-draft.js', false);
    }

    public function test_le_brouillon_ne_conserve_aucune_donnee_sensible(): void
    {
        $js = file_get_contents(public_path('js/form-draft.js'));

        // Mots de passe, fichiers et jetons ne doivent jamais etre stockes.
        $this->assertStringContainsString("champ.type === 'password'", $js);
        $this->assertStringContainsString("champ.type === 'file'", $js);
        $this->assertStringContainsString("'_token'", $js);
        $this->assertStringContainsString('submission_token', $js);

        // Et le brouillon reste local : aucun envoi au serveur.
        $this->assertStringNotContainsString('fetch(', $js);
    }

    public function test_le_brouillon_est_efface_a_la_publication(): void
    {
        $js = file_get_contents(public_path('js/form-draft.js'));

        $this->assertMatchesRegularExpression(
            "/addEventListener\('submit'.*?removeItem/s",
            $js,
            'Le brouillon doit disparaitre une fois le formulaire envoye.'
        );
    }

    // --- Favoris sans rechargement ------------------------------------------

    public function test_le_bouton_favori_n_utilise_plus_de_rechargement(): void
    {
        $vendeur = $this->membre('Vendeur');
        $visiteur = $this->membre('Visiteur');
        $this->annonce($vendeur);

        $html = $this->actingAs($visiteur)->get(route('search'))->assertOk()->getContent();

        $this->assertStringContainsString('data-favori-url', $html);
        $this->assertStringNotContainsString('favoris/1/toggle\';', $html);
    }

    public function test_le_favori_repond_en_json(): void
    {
        $vendeur = $this->membre('Vendeur');
        $visiteur = $this->membre('Visiteur');
        $annonce = $this->annonce($vendeur);

        $this->actingAs($visiteur)
            ->postJson(route('account.favorites.toggle', $annonce))
            ->assertOk()
            ->assertJson(['favorited' => true, 'count' => 1]);
    }

    public function test_l_affichage_revient_en_arriere_si_le_serveur_refuse(): void
    {
        $js = file_get_contents(public_path('js/favorite.js'));

        $this->assertMatchesRegularExpression(
            '/catch\(function \(\) \{.*?peindre\(bouton, avant\)/s',
            $js,
            'Un echec doit remettre l’etat precedent, jamais laisser un affichage faux.'
        );
    }

    // --- Regroupement des notifications de favoris --------------------------

    public function test_le_premier_favori_du_jour_nomme_l_article(): void
    {
        $vendeur = $this->membre('Vendeur');
        $visiteur = $this->membre('Visiteur');
        $annonce = $this->annonce($vendeur, 'Robe fleurie');

        $this->actingAs($visiteur)->postJson(route('account.favorites.toggle', $annonce));

        $notif = Notification::where('user_id', $vendeur->id)->firstOrFail();
        $this->assertStringContainsString('Robe fleurie', $notif->message);
        Queue::assertPushed(SendPushBroadcast::class, 1);
    }

    public function test_les_favoris_suivants_du_jour_sont_regroupes(): void
    {
        $vendeur = $this->membre('Vendeur');
        $annonce1 = $this->annonce($vendeur, 'Robe fleurie');
        $annonce2 = $this->annonce($vendeur, 'Sac en paille');

        foreach ([$annonce1, $annonce2] as $i => $annonce) {
            $this->actingAs($this->membre('Visiteur' . $i))
                ->postJson(route('account.favorites.toggle', $annonce));
        }

        // Une seule notification, et une seule sonnerie.
        $this->assertSame(1, Notification::where('user_id', $vendeur->id)->count());
        Queue::assertPushed(SendPushBroadcast::class, 1);

        $notif = Notification::where('user_id', $vendeur->id)->firstOrFail();
        $this->assertStringContainsString('2 personnes', $notif->message);
    }

    public function test_le_regroupement_remet_la_notification_en_non_lue(): void
    {
        $vendeur = $this->membre('Vendeur');
        $annonce1 = $this->annonce($vendeur, 'Robe fleurie');
        $annonce2 = $this->annonce($vendeur, 'Sac en paille');

        $this->actingAs($this->membre('A'))->postJson(route('account.favorites.toggle', $annonce1));

        Notification::where('user_id', $vendeur->id)->update(['read_at' => now()]);

        $this->actingAs($this->membre('B'))->postJson(route('account.favorites.toggle', $annonce2));

        $this->assertNull(Notification::where('user_id', $vendeur->id)->firstOrFail()->read_at);
    }

    public function test_le_vendeur_n_est_pas_notifie_de_ses_propres_favoris(): void
    {
        $vendeur = $this->membre('Vendeur');
        $annonce = $this->annonce($vendeur);

        $this->actingAs($vendeur)->postJson(route('account.favorites.toggle', $annonce));

        $this->assertSame(0, Notification::where('user_id', $vendeur->id)->count());
    }
}
