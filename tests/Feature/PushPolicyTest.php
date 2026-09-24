<?php

namespace Tests\Feature;

use App\Jobs\SendPushBroadcast;
use App\Models\Notification;
use App\Models\User;
use App\Support\PushPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Garde-fous des notifications push.
 *
 * Le serveur tourne à l'heure de La Réunion, mais les îles s'étalent sur
 * 8 heures de décalage : quand il est 9 h à Saint-Denis, il est 1 h du matin
 * à Fort-de-France. Les rappels du matin réveillaient donc les Antillais en
 * pleine nuit.
 */
class PushPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['push.fcm.project_id' => 'swap-iles']);
        config(['push.silence.debut' => 22, 'push.silence.fin' => 8]);
        config(['push.plafonds.social' => 10, 'push.plafonds.animation' => 3]);
    }

    private function membre(string $territoire): User
    {
        return User::create([
            'name' => 'Membre',
            'email' => 'm' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => $territoire,
        ]);
    }

    public function test_le_fuseau_suit_l_ile_du_membre(): void
    {
        $this->assertSame('Indian/Reunion', PushPolicy::fuseau($this->membre('La Réunion')));
        $this->assertSame('America/Martinique', PushPolicy::fuseau($this->membre('Martinique')));
        $this->assertSame('America/Cayenne', PushPolicy::fuseau($this->membre('Guyane')));
        $this->assertSame('Indian/Mayotte', PushPolicy::fuseau($this->membre('Mayotte')));
    }

    public function test_un_membre_sans_ile_retombe_sur_le_fuseau_par_defaut(): void
    {
        $this->assertSame('Indian/Reunion', PushPolicy::fuseau($this->membre('')));
        $this->assertSame('Indian/Reunion', PushPolicy::fuseau(null));
    }

    public function test_le_rappel_de_9h_reunion_ne_reveille_pas_la_martinique(): void
    {
        // 9 h à La Réunion = 1 h du matin en Martinique : c'est le cas réel du
        // rappel « n'oubliez pas votre favori », planifié à 9 h.
        $maintenant = Carbon::parse('2026-09-24 09:00:00', 'Indian/Reunion');

        $reunionnais = PushPolicy::decider($this->membre('La Réunion'), 'favorite_added', $maintenant);
        $this->assertSame('envoyer', $reunionnais['action']);

        $martiniquais = PushPolicy::decider($this->membre('Martinique'), 'favorite_added', $maintenant);
        $this->assertSame('differer', $martiniquais['action']);

        // Différé au réveil local, à 8 h heure de Martinique.
        $envoiA = $martiniquais['envoi_a']->copy()->setTimezone('America/Martinique');
        $this->assertSame(8, (int) $envoiA->format('G'));
        $this->assertTrue($envoiA->greaterThan($maintenant));
    }

    public function test_une_notification_du_soir_est_reportee_au_lendemain_matin(): void
    {
        // 23 h à La Réunion, pour un Réunionnais : on ne sonne pas.
        $maintenant = Carbon::parse('2026-09-24 23:00:00', 'Indian/Reunion');

        $decision = PushPolicy::decider($this->membre('La Réunion'), 'favorite_added', $maintenant);

        $this->assertSame('differer', $decision['action']);

        $envoiA = $decision['envoi_a']->copy()->setTimezone('Indian/Reunion');
        $this->assertSame(8, (int) $envoiA->format('G'));
        $this->assertSame('2026-09-25', $envoiA->toDateString());
    }

    public function test_en_pleine_journee_tout_part_immediatement(): void
    {
        $maintenant = Carbon::parse('2026-09-24 14:00:00', 'Indian/Reunion');

        $this->assertSame(
            'envoyer',
            PushPolicy::decider($this->membre('La Réunion'), 'favorite_added', $maintenant)['action']
        );
    }

    public function test_une_vente_part_meme_en_pleine_nuit(): void
    {
        $maintenant = Carbon::parse('2026-09-24 03:00:00', 'Indian/Reunion');

        $decision = PushPolicy::decider($this->membre('La Réunion'), 'transaction_paid_seller', $maintenant);

        $this->assertSame('envoyer', $decision['action']);
    }

    public function test_le_plafond_d_animation_coupe_le_signal_sonore(): void
    {
        $maintenant = Carbon::parse('2026-09-24 14:00:00', 'Indian/Reunion');
        $membre = $this->membre('La Réunion');

        for ($i = 1; $i <= 3; $i++) {
            $this->assertSame(
                'envoyer',
                PushPolicy::decider($membre, 'favorite_added', $maintenant)['action'],
                "Le favori n°{$i} devrait passer."
            );
        }

        $quatrieme = PushPolicy::decider($membre, 'favorite_added', $maintenant);
        $this->assertSame('ignorer', $quatrieme['action']);
    }

    public function test_les_messages_ont_un_plafond_plus_large_que_l_animation(): void
    {
        $maintenant = Carbon::parse('2026-09-24 14:00:00', 'Indian/Reunion');
        $membre = $this->membre('La Réunion');

        // Le plafond « animation » est atteint, mais les messages passent encore.
        for ($i = 0; $i < 5; $i++) {
            PushPolicy::decider($membre, 'favorite_added', $maintenant);
        }

        $this->assertSame(
            'envoyer',
            PushPolicy::decider($membre, 'message_received', $maintenant)['action']
        );
    }

    public function test_le_plafond_ne_bloque_jamais_une_vente(): void
    {
        $maintenant = Carbon::parse('2026-09-24 14:00:00', 'Indian/Reunion');
        $membre = $this->membre('La Réunion');

        for ($i = 0; $i < 20; $i++) {
            PushPolicy::decider($membre, 'favorite_added', $maintenant);
        }

        $this->assertSame(
            'envoyer',
            PushPolicy::decider($membre, 'transaction_paid_seller', $maintenant)['action']
        );
    }

    public function test_un_type_inconnu_est_traite_comme_transactionnel(): void
    {
        // On ne retient jamais quelque chose qu'on ne sait pas classer.
        $this->assertSame('transactionnel', PushPolicy::niveau('type_tout_neuf'));
        $this->assertSame('transactionnel', PushPolicy::niveau(null));
    }

    public function test_la_notification_reste_visible_dans_l_app_meme_sans_push(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-24 14:00:00', 'Indian/Reunion'));

        $membre = $this->membre('La Réunion');

        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'user_id' => $membre->id,
                'type' => 'favorite_added',
                'title' => 'Nouveau favori',
                'message' => 'Quelqu’un a aimé votre annonce.',
            ]);
        }

        // Les 5 notifications existent, mais seules 3 ont fait sonner le téléphone.
        $this->assertSame(5, Notification::where('user_id', $membre->id)->count());
        Queue::assertPushed(SendPushBroadcast::class, 3);

        Carbon::setTestNow();
    }
}
