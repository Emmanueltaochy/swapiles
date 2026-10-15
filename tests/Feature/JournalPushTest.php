<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\PushEnvoi;
use App\Models\User;
use App\Support\PushSuivi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Journal des notifications push (admin) : chaque notification est notée
 * avec son sort, et un clic dessus est attribué au bon compte.
 */
class JournalPushTest extends TestCase
{
    use RefreshDatabase;

    private User $membre;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $cle = tempnam(sys_get_temp_dir(), 'apns') . '.p8';
        openssl_pkey_export(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']), $pem);
        file_put_contents($cle, $pem);
        config([
            'push.apns.key_path' => $cle, 'push.apns.key_id' => 'ABCD123456', 'push.apns.team_id' => 'Z68H8V9222',
            'push.apns.bundle_id' => 'com.swapiles.app', 'push.apns.production' => true,
            'push.silence.debut' => 22, 'push.silence.fin' => 8,
        ]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Carbon::setTestNow(Carbon::parse('2026-10-15 15:00', 'Indian/Reunion'));

        $this->membre = User::create(['name' => 'Nina', 'email' => 'nina@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        DeviceToken::create(['user_id' => $this->membre->id, 'token' => str_repeat('a1b2c3d4', 8), 'platform' => 'ios', 'last_seen_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_une_notification_automatique_est_journalisee_puis_son_clic_attribue(): void
    {
        $this->membre->forceFill(['notification_prefs' => null])->save();
        Notification::create(['user_id' => $this->membre->id, 'type' => 'message_received', 'title' => 'Nouveau message', 'message' => 'Paul : dispo ?', 'url' => '/messages/7']);

        $envoi = PushEnvoi::sole();
        $this->assertSame('envoye', $envoi->statut);
        $this->assertSame(1, $envoi->appareils_ok);
        $this->assertSame('auto', $envoi->origine);

        // Le lien de la notification porte le repère de suivi.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'push.apple.com') && ($r->data()['url'] ?? null) === '/messages/7?pn=' . $envoi->id);

        // Le membre touche la notification : clic noté, puis page propre.
        $this->actingAs($this->membre)->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')->get('/?pn=' . $envoi->id . '&x=1')->assertRedirect(url('/?x=1'));
        $this->actingAs($this->membre)->get('/?pn=' . $envoi->id);

        $envoi->refresh();
        $this->assertSame(1, $envoi->clics, 'Un seul clic compté par personne.');
        $this->assertSame($this->membre->id, $envoi->clicsDetail()->sole()->user_id);
        $this->assertSame('ios', $envoi->clicsDetail()->sole()->plateforme);
    }

    public function test_les_notifications_retenues_sont_notees_avec_la_raison(): void
    {
        // La nuit : différée (animation) ; un live : non envoyée (périmée).
        \Illuminate\Support\Facades\Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-15 23:30', 'Indian/Reunion'));
        Notification::create(['user_id' => $this->membre->id, 'type' => 'favorite_reminder', 'title' => 'Ton favori', 'message' => 'M', 'url' => '/a/1']);
        Notification::create(['user_id' => $this->membre->id, 'type' => 'live_started', 'title' => 'Live', 'message' => 'M', 'url' => '/live/1']);

        $this->assertSame('differe', PushEnvoi::where('type', 'favorite_reminder')->value('statut'));
        $this->assertNotNull(PushEnvoi::where('type', 'favorite_reminder')->value('prevu_at'));
        $this->assertSame('ignore', PushEnvoi::where('type', 'live_started')->value('statut'));
    }

    public function test_bilan_et_page_admin(): void
    {
        Notification::create(['user_id' => $this->membre->id, 'type' => 'message_received', 'title' => 'Message', 'message' => 'M', 'url' => '/m/1']);
        Notification::create(['user_id' => $this->membre->id, 'type' => 'message_received', 'title' => 'Message 2', 'message' => 'M', 'url' => '/m/2']);
        $premier = PushEnvoi::orderBy('id')->first();
        $this->actingAs($this->membre)->get('/?pn=' . $premier->id);

        $bilan = PushSuivi::bilan(now()->subDay(), now()->addMinute());
        $this->assertSame(2, $bilan['parties']);
        $this->assertSame(1, $bilan['ouvertes']);
        $this->assertEquals(50.0, $bilan['taux']);
        $this->assertSame(1, $bilan['membres_cliqueurs']);

        $ligne = collect(PushSuivi::parType(now()->subDay(), now()->addMinute()))->firstWhere('type', 'message_received');
        $this->assertSame(['parties' => 2, 'ouvertes' => 1, 'taux' => 50.0], ['parties' => $ligne['parties'], 'ouvertes' => $ligne['ouvertes'], 'taux' => $ligne['taux']]);

        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $this->actingAs($admin)->get('/admin/push-envois')->assertOk()
            ->assertSee('Notifications envoyées')->assertSee('Message reçu')->assertSee('Nina');
    }

    public function test_envoi_manuel_depuis_l_admin_journalise(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\PushBroadcast::class)
            ->fillForm(['title' => 'Nouveau point relais', 'body' => 'Saint-Gilles !', 'url' => 'https://swapiles.com/points-relais'])
            ->call('send');

        $envoi = PushEnvoi::where('origine', 'manuel')->sole();
        $this->assertSame('envoye', $envoi->statut);
        $this->assertNull($envoi->user_id);
        Http::assertSent(fn ($r) => ($r->data()['url'] ?? null) === 'https://swapiles.com/points-relais?pn=' . $envoi->id);
    }

    public function test_lien_de_suivi(): void
    {
        $this->assertSame('/annonce/3?pn=9', PushSuivi::lien('/annonce/3', 9));
        $this->assertSame('/annonce/3?a=1&pn=9#avis', PushSuivi::lien('/annonce/3?a=1#avis', 9));
        $this->assertSame('/?pn=9', PushSuivi::lien(null, 9));
    }
}
