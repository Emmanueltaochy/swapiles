<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sommaire des réglages.
 *
 * Identité, adresse d'expédition, mot de passe, points relais, préférences de
 * notification et suppression de compte vivaient tous dans une seule page
 * « Modifier mon profil », sur plus de deux écrans. Les préférences de
 * notification, tout en bas, étaient introuvables : le seul moyen d'arrêter
 * d'être sollicité était de supprimer son compte.
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function membre(array $attributs = []): User
    {
        return User::create(array_merge([
            'name' => 'Marie',
            'email' => 'marie' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ], $attributs));
    }

    public function test_les_reglages_demandent_une_connexion(): void
    {
        $this->get(route('account.settings'))->assertRedirect(route('login'));
        $this->get(route('account.notifications.preferences'))->assertRedirect(route('login'));
    }

    public function test_les_reglages_listent_chaque_entree(): void
    {
        $reponse = $this->actingAs($this->membre())->get(route('account.settings'));

        $reponse->assertOk()
            ->assertSee('Identité et photo')
            ->assertSee('Mot de passe')
            ->assertSee('Préférences de notification')
            ->assertSee('Mes adresses de livraison')
            ->assertSee("Adresse d'expédition")
            ->assertSee('Recevoir mes paiements')
            ->assertSee('Supprimer mon compte');
    }

    public function test_les_reglages_signalent_ce_qui_manque(): void
    {
        $html = $this->actingAs($this->membre())->get(route('account.settings'))
            ->assertOk()->getContent();

        // Adresse d'expédition vide et virements non activés : deux alertes.
        $this->assertSame(2, substr_count($html, 'À compléter') + substr_count($html, 'À activer'));
    }

    public function test_une_adresse_complete_ne_declenche_plus_d_alerte(): void
    {
        $membre = $this->membre([
            'address_line1' => '10 rue des Manguiers',
            'postal_code' => '97410',
            'city' => 'Saint-Pierre',
        ]);

        $html = $this->actingAs($membre)->get(route('account.settings'))->assertOk()->getContent();

        $this->assertStringNotContainsString('À compléter', $html);
    }

    public function test_les_preferences_de_notification_ont_leur_propre_page(): void
    {
        $reponse = $this->actingAs($this->membre())->get(route('account.notifications.preferences'));

        $reponse->assertOk()->assertSee('Préférences de notification');

        foreach (\App\Support\NotificationPreferences::CATEGORIES as $categorie) {
            $reponse->assertSee($categorie['label'], false);
        }
    }

    public function test_enregistrer_ses_preferences_ne_vide_pas_le_profil(): void
    {
        $membre = $this->membre([
            'phone' => '0692000000',
            'address_line1' => '10 rue des Manguiers',
            'postal_code' => '97410',
            'city' => 'Saint-Pierre',
        ]);

        $this->actingAs($membre)->put(route('account.profile.update'), [
            'name' => $membre->name,
            'notification_prefs_submitted' => '1',
            'notification_prefs' => ['favoris' => ['push' => '1']],
        ])->assertRedirect();

        $membre->refresh();

        // Le formulaire des préférences n'envoie que « name » : le reste du
        // profil ne doit surtout pas être effacé au passage.
        $this->assertSame('0692000000', $membre->phone);
        $this->assertSame('10 rue des Manguiers', $membre->address_line1);
        $this->assertSame('Saint-Pierre', $membre->city);
        $this->assertTrue($membre->notification_prefs['favoris']['push']);
        $this->assertFalse($membre->notification_prefs['favoris']['email']);
    }

    public function test_le_tableau_de_bord_mene_aux_reglages(): void
    {
        $this->actingAs($this->membre())->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee(route('account.settings'), false);
    }

    public function test_la_page_profil_ne_porte_plus_les_preferences(): void
    {
        $html = $this->actingAs($this->membre())->get(route('account.profile.edit'))
            ->assertOk()->getContent();

        // Le tableau de cases à cocher a déménagé ; il ne reste qu'un lien.
        $this->assertStringNotContainsString('notification_prefs_submitted', $html);
        $this->assertStringContainsString(route('account.notifications.preferences'), $html);

        // Les ancres permettent d'arriver directement sur la bonne section.
        foreach (['id="expedition"', 'id="mot-de-passe"'] as $ancre) {
            $this->assertStringContainsString($ancre, $html, "Ancre manquante : {$ancre}");
        }
    }
}
