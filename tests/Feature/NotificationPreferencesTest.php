<?php

namespace Tests\Feature;

use App\Jobs\SendPushBroadcast;
use App\Models\AccountDeletionReason;
use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Réglages de notification et motif de départ.
 *
 * Un membre ne pouvait couper AUCUNE notification : le seul moyen d'arrêter
 * d'être sollicité était de supprimer son compte. Et rien n'enregistrait la
 * raison des départs.
 */
class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function membre(?array $prefs = null): User
    {
        return User::create([
            'name' => 'Membre',
            'email' => 'm' . uniqid() . '@ex.com',
            'password' => Hash::make('motdepasse123'),
            'territoire' => 'La Réunion',
            'notification_prefs' => $prefs,
        ]);
    }

    private function configurerPush(): void
    {
        config(['push.fcm.project_id' => 'swap-iles']);
    }

    public function test_tout_est_recu_par_defaut(): void
    {
        $membre = $this->membre();

        $this->assertTrue($membre->accepteNotification('favorite_added', 'push'));
        $this->assertTrue($membre->accepteNotification('message_received', 'email'));
    }

    public function test_une_categorie_coupee_bloque_le_push(): void
    {
        Queue::fake();
        $this->configurerPush();

        $membre = $this->membre([
            'favoris' => ['push' => false, 'email' => true],
        ]);

        Notification::create([
            'user_id' => $membre->id,
            'type' => 'favorite_added',
            'title' => 'Nouveau favori',
            'message' => 'Quelqu’un a aimé votre annonce.',
        ]);

        Queue::assertNotPushed(SendPushBroadcast::class);
    }

    public function test_une_categorie_active_laisse_passer_le_push(): void
    {
        Queue::fake();
        $this->configurerPush();

        $membre = $this->membre([
            'favoris' => ['push' => true, 'email' => false],
        ]);

        Notification::create([
            'user_id' => $membre->id,
            'type' => 'favorite_added',
            'title' => 'Nouveau favori',
            'message' => 'Quelqu’un a aimé votre annonce.',
        ]);

        Queue::assertPushed(SendPushBroadcast::class);
    }

    public function test_les_notifications_de_vente_passent_toujours(): void
    {
        Queue::fake();
        $this->configurerPush();

        // Tout coupé : une notification de paiement doit quand même partir.
        $prefs = [];
        foreach (array_keys(NotificationPreferences::CATEGORIES) as $cle) {
            $prefs[$cle] = ['push' => false, 'email' => false];
        }
        $membre = $this->membre($prefs);

        Notification::create([
            'user_id' => $membre->id,
            'type' => 'transaction_paid_seller',
            'title' => 'Paiement reçu',
            'message' => 'Votre vente est payée.',
        ]);

        Queue::assertPushed(SendPushBroadcast::class);
    }

    public function test_un_type_inconnu_n_est_jamais_fait_taire_par_accident(): void
    {
        $membre = $this->membre(['favoris' => ['push' => false, 'email' => false]]);

        $this->assertTrue($membre->accepteNotification('type_totalement_nouveau', 'push'));
        $this->assertNull(NotificationPreferences::categorieDuType('type_totalement_nouveau'));
    }

    public function test_le_membre_enregistre_ses_preferences(): void
    {
        $membre = $this->membre();

        $this->actingAs($membre)->put(route('account.profile.update'), [
            'name' => $membre->name,
            'country_code' => 'FR',
            'notification_prefs_submitted' => '1',
            'notification_prefs' => [
                'favoris' => ['push' => '1'],       // e-mail décoché
                'messages' => ['push' => '1', 'email' => '1'],
            ],
        ])->assertRedirect();

        $prefs = $membre->fresh()->notification_prefs;

        $this->assertTrue($prefs['favoris']['push']);
        $this->assertFalse($prefs['favoris']['email']);
        $this->assertTrue($prefs['messages']['email']);
        // Catégorie absente du formulaire = tout décoché, pas d'oubli silencieux.
        $this->assertFalse($prefs['vendeurs_suivis']['push']);
    }

    public function test_une_mise_a_jour_de_profil_ordinaire_ne_touche_pas_aux_preferences(): void
    {
        $membre = $this->membre(['favoris' => ['push' => false, 'email' => false]]);

        $this->actingAs($membre)->put(route('account.profile.update'), [
            'name' => 'Nouveau nom',
            'country_code' => 'FR',
        ])->assertRedirect();

        $this->assertFalse($membre->fresh()->notification_prefs['favoris']['push']);
    }

    public function test_le_motif_de_depart_est_enregistre_sans_donnee_personnelle(): void
    {
        $membre = $this->membre();

        $this->actingAs($membre)->delete(route('account.delete'), [
            'confirmation' => 'SUPPRIMER',
            'password' => 'motdepasse123',
            'reason' => 'trop_notifications',
            'reason_details' => 'Je recevais dix notifications par jour.',
        ])->assertRedirect();

        $motif = AccountDeletionReason::firstOrFail();

        $this->assertSame('trop_notifications', $motif->reason);
        $this->assertSame('Je recevais dix notifications par jour.', $motif->details);
        $this->assertFalse($motif->had_sales);

        // Aucune colonne ne relie le motif au membre.
        $this->assertArrayNotHasKey('user_id', $motif->getAttributes());
        $this->assertArrayNotHasKey('email', $motif->getAttributes());
    }

    public function test_un_depart_sans_reponse_est_quand_meme_comptabilise(): void
    {
        // Sans ça, le compteur affichait zéro départ alors que des membres
        // étaient réellement partis.
        $membre = $this->membre();

        $this->actingAs($membre)->delete(route('account.delete'), [
            'confirmation' => 'SUPPRIMER',
            'password' => 'motdepasse123',
        ])->assertRedirect();

        $motif = AccountDeletionReason::firstOrFail();
        $this->assertSame(AccountDeletionReason::NON_RENSEIGNE, $motif->reason);
        $this->assertSame('Sans réponse', $motif->motifLabel());
        $this->assertNull($motif->details);

        // La suppression n'est jamais empêchée par la collecte du motif.
        $this->assertNull(User::find($membre->id));
    }

    public function test_les_motifs_sont_visibles_et_non_caches_dans_un_menu(): void
    {
        // Un menu déroulant fermé sur « je préfère ne pas répondre » ne recueille
        // quasiment aucune réponse : les motifs doivent être affichés.
        $vue = file_get_contents(resource_path('views/account-deletion.blade.php'));

        $this->assertStringContainsString('type="radio" name="reason"', $vue);
        $this->assertStringNotContainsString('<select id="reason"', $vue);
    }

    public function test_un_motif_invente_est_refuse(): void
    {
        $membre = $this->membre();

        $this->actingAs($membre)->delete(route('account.delete'), [
            'confirmation' => 'SUPPRIMER',
            'password' => 'motdepasse123',
            'reason' => 'motif-inexistant',
        ])->assertSessionHasErrors('reason');

        $this->assertNotNull(User::find($membre->id));
    }

    public function test_la_page_de_suppression_propose_de_regler_les_notifications(): void
    {
        $membre = $this->membre();

        $this->actingAs($membre)
            ->get(route('account.deletion.info'))
            ->assertOk()
            ->assertSee('Régler mes notifications')
            ->assertSee('Pourquoi partez-vous ?', false);
    }
}
