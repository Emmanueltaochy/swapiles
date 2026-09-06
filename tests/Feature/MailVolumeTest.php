<?php

namespace Tests\Feature;

use App\Jobs\SendAdminEventEmail;
use App\Jobs\SendListingViewedEmail;
use App\Models\Listing;
use App\Models\User;
use App\Support\AdminEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Garde-fous de volume d'envoi : la boîte d'envoi est plafonnée à 1 000
 * e-mails par jour. Au-delà, les e-mails importants (confirmation d'adresse,
 * mot de passe, vente) arrivent en retard ou en indésirable.
 */
class MailVolumeTest extends TestCase
{
    use RefreshDatabase;

    private function vendeur(): User
    {
        return User::create([
            'name' => 'Vendeur',
            'email' => 'v' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    private function annonce(User $vendeur, string $titre = 'Robe'): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id,
            'title' => $titre,
            'description' => 'Description de test suffisamment longue.',
            'price' => 10,
            'currency' => 'EUR',
            'listing_type' => 'achat',
            'status' => 'published',
            'territoire' => 'La Réunion',
            'category_level1' => 'Mode',
        ]);
    }

    public function test_les_alertes_admin_courantes_n_envoient_plus_d_email(): void
    {
        Queue::fake();
        config(['admin_alerts.favorite_added' => false]);

        AdminEvent::notify('Annonce ajoutée en favori', 'Test', null, 'favorite_added');

        Queue::assertNotPushed(SendAdminEventEmail::class);
    }

    public function test_les_alertes_admin_importantes_partent_toujours(): void
    {
        Queue::fake();

        AdminEvent::notify('Nouvelle vente validée', 'Test', null, 'sale_completed');

        Queue::assertPushed(SendAdminEventEmail::class);
    }

    public function test_une_alerte_sans_cle_part_toujours(): void
    {
        Queue::fake();

        AdminEvent::notify('Évènement non classé', 'Test');

        Queue::assertPushed(SendAdminEventEmail::class);
    }

    public function test_une_alerte_reactivee_dans_la_config_repart(): void
    {
        Queue::fake();
        config(['admin_alerts.favorite_added' => true]);

        AdminEvent::notify('Annonce ajoutée en favori', 'Test', null, 'favorite_added');

        Queue::assertPushed(SendAdminEventEmail::class);
    }

    public function test_une_annonce_ne_declenche_qu_un_email_de_vue_par_fenetre(): void
    {
        Queue::fake();
        Cache::flush();

        $annonce = $this->annonce($this->vendeur());

        // Deux visiteurs différents sur la même annonce.
        $this->get(route('listings.show', $annonce));
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->get(route('listings.show', $annonce));

        Queue::assertPushed(SendListingViewedEmail::class, 1);
    }

    public function test_un_vendeur_ne_recoit_pas_plus_que_le_plafond_quotidien(): void
    {
        Queue::fake();
        Cache::flush();
        config(['mail_limits.listing_view.par_vendeur_par_jour' => 2]);

        $vendeur = $this->vendeur();

        // Quatre annonces différentes du même vendeur, vues le même jour.
        foreach (['A', 'B', 'C', 'D'] as $titre) {
            $this->get(route('listings.show', $this->annonce($vendeur, $titre)));
        }

        Queue::assertPushed(SendListingViewedEmail::class, 2);
    }

    public function test_le_vendeur_n_est_pas_prevenu_de_ses_propres_vues(): void
    {
        Queue::fake();
        Cache::flush();

        $vendeur = $this->vendeur();
        $annonce = $this->annonce($vendeur);

        $this->actingAs($vendeur)->get(route('listings.show', $annonce));

        Queue::assertNotPushed(SendListingViewedEmail::class);
    }
}
