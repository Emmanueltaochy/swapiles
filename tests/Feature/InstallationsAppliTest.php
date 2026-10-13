<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\InstallationsAppli;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nombre réel d'installations de l'appli iPhone / Android : chaque lancement
 * est signalé avec un identifiant anonyme tiré au hasard sur le téléphone.
 */
class InstallationsAppliTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'aaaaaaaa-1111-4111-8111-000000000001';

    private const ANDROID_1 = 'bbbbbbbb-2222-4222-8222-000000000002';

    private const ANDROID_2 = 'cccccccc-3333-4333-8333-000000000003';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ouvrir(string $id, string $plateforme)
    {
        return $this->postJson('/appli/installation', ['id' => $id, 'plateforme' => $plateforme]);
    }

    public function test_une_installation_compte_une_fois_meme_ouverte_souvent(): void
    {
        $this->ouvrir(self::IPHONE, 'ios')->assertNoContent();
        $this->ouvrir(self::IPHONE, 'ios')->assertNoContent();
        $this->ouvrir(self::ANDROID_1, 'android');
        $this->ouvrir(self::ANDROID_2, 'android');

        $c = InstallationsAppli::chiffres();
        $this->assertSame(1, $c['ios']['total']);
        $this->assertSame(2, $c['android']['total']);
        $this->assertSame(3, $c['toutes']['total']);
        $this->assertSame(2, DB::table('app_installations')->where('install_id', self::IPHONE)->value('ouvertures'));
    }

    public function test_installations_actives(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00');
        $this->ouvrir(self::ANDROID_1, 'android');
        $this->ouvrir(self::ANDROID_2, 'android');

        Carbon::setTestNow('2026-10-10 10:00:00');
        $this->ouvrir(self::ANDROID_2, 'android');
        $this->ouvrir(self::IPHONE, 'ios');

        Carbon::setTestNow('2026-10-13 09:00:00');
        $this->ouvrir(self::IPHONE, 'ios');

        $c = InstallationsAppli::chiffres();
        $this->assertSame(2, $c['android']['total']);
        $this->assertSame(1, $c['android']['actives_30j'], 'Une des deux n’a plus été ouverte depuis septembre.');
        $this->assertSame(2, $c['toutes']['actives_7j']);
        $this->assertSame(1, $c['toutes']['aujourdhui']);
        $this->assertSame(1, $c['toutes']['nouvelles_7j']);

        $serie = InstallationsAppli::nouvellesParJour(5);
        $this->assertSame([0, 1, 0, 0, 0], $serie['ios'], '09, 10, 11, 12, 13 octobre.');
        $this->assertSame([0, 0, 0, 0, 0], $serie['android']);
    }

    public function test_notifications_activees_par_plateforme(): void
    {
        DB::table('device_tokens')->insert([
            ['token' => 't1', 'platform' => 'ios', 'created_at' => now(), 'updated_at' => now()],
            ['token' => 't2', 'platform' => 'android', 'created_at' => now(), 'updated_at' => now()],
            ['token' => 't3', 'platform' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $c = InstallationsAppli::chiffres();
        $this->assertSame(1, $c['ios']['notifications']);
        $this->assertSame(2, $c['toutes']['notifications'], 'Les navigateurs ne comptent pas.');
    }

    public function test_donnees_refusees(): void
    {
        $this->ouvrir('court', 'ios')->assertStatus(422);
        $this->ouvrir(self::IPHONE, 'web')->assertStatus(422);
        $this->ouvrir("x'; drop table users; --aaaaaaaa", 'ios')->assertStatus(422);

        $this->assertSame(0, DB::table('app_installations')->count());
    }

    public function test_le_script_ne_fait_rien_sur_le_site(): void
    {
        $this->assertMatchesRegularExpression('#src="[^"]*/js/app-install\.js#', $this->get('/')->getContent());

        $js = file_get_contents(public_path('js/app-install.js'));
        $this->assertStringContainsString('cap.isNativePlatform()', $js);
        $this->assertStringContainsString("if (plateforme !== 'ios' && plateforme !== 'android') return;", $js);
    }

    public function test_la_page_admin(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $this->ouvrir(self::IPHONE, 'ios');

        $this->actingAs($admin)->get('/admin/app-installations')
            ->assertOk()
            ->assertSee('iPhone / iPad')
            ->assertSee('Android')
            ->assertSee('installations ouvertes au moins une fois')
            ->assertSee('App Store Connect');
    }
}
