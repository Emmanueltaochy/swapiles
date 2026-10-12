<?php

namespace Tests\Feature;

use App\Models\LiveVisit;
use App\Models\Presence;
use App\Models\User;
use App\Support\VisiteursEnDirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * « En ligne maintenant » ne compte que les humains confirmés par leur
 * navigateur (public/js/presence.js). Le reste du trafic (robots déguisés,
 * vérification des publicités, aperçus de liens) est montré à part.
 */
class VisiteursEnDirectTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private const PC = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function battement(array $donnees = [], string $ua = self::IPHONE)
    {
        // Même navigateur d'un battement à l'autre (cookie visiteur).
        return $this->withHeader('User-Agent', $ua)->withCookie('swp_vid', 'VisiteurDeTest0123456789')->withCredentials()
            ->postJson('/presence', $donnees + ['path' => '/']);
    }

    public function test_un_battement_enregistre_un_humain(): void
    {
        $this->battement(['path' => '/recherche', 'utm' => 'fb', 'page' => true])->assertNoContent();

        $p = Presence::sole();
        $this->assertSame('/recherche', $p->path);
        $this->assertSame('mobile', $p->appareil);
        $this->assertSame('facebook', $p->source);
        $this->assertFalse($p->appli);
        $this->assertSame(1, VisiteursEnDirect::compte()['humains']);
    }

    public function test_visiteurs_uniques_par_jour_humains_seulement(): void
    {
        Carbon::setTestNow('2026-10-12 09:00:00');
        $this->battement();
        Carbon::setTestNow('2026-10-12 18:00:00');
        $this->battement(['page' => true]);
        $this->battement([], 'Mozilla/5.0 (compatible; Googlebot/2.1)');

        Carbon::setTestNow('2026-10-13 08:00:00');
        $this->battement();
        $this->withHeader('User-Agent', self::PC)->withCookie('swp_vid', 'AutreVisiteur0123456789X')->withCredentials()
            ->postJson('/presence', ['path' => '/']);
        // Des pages vues sans présence confirmée (robot sans cookie) : jamais comptées.
        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('User-Agent', self::IPHONE)->get('/');
        }

        $this->assertSame([1, 2], \App\Support\TableauDeBord::visiteursParJour(2));
    }

    public function test_le_rattrapage_compte_les_presences_deja_enregistrees(): void
    {
        Presence::create(['cle' => 'a', 'ip_hash' => 'a', 'debut_at' => '2026-10-12 09:00:00', 'last_seen_at' => '2026-10-12 09:20:00']);
        Presence::create(['cle' => 'b', 'ip_hash' => 'b', 'debut_at' => '2026-10-12 23:50:00', 'last_seen_at' => '2026-10-13 00:10:00']);
        \Illuminate\Support\Facades\DB::table('visiteurs_jours')->insert(['jour' => '2026-10-12', 'cle' => 'a']);

        (require database_path('migrations/2026_10_13_100000_backfill_visiteurs_jours_from_presences.php'))->up();

        Carbon::setTestNow('2026-10-13 12:00:00');
        $this->assertSame([2, 1], \App\Support\TableauDeBord::visiteursParJour(2), 'Sans doublon, chaque jour de présence compté.');
    }

    public function test_les_robots_ne_sont_jamais_comptes(): void
    {
        $this->battement([], 'facebookexternalhit/1.1')->assertNoContent();
        $this->battement([], '')->assertNoContent();

        $this->assertSame(0, Presence::count());
    }

    public function test_appli_et_changement_de_page(): void
    {
        $this->battement(['path' => '/', 'appli' => true]);
        $this->battement(['path' => '/annonce/3', 'appli' => true, 'page' => true]);
        $this->battement(['path' => '/annonce/3', 'appli' => true]);

        $p = Presence::sole();
        $this->assertSame(2, $p->pages, 'Un même chemin ne compte qu’une fois.');
        $this->assertSame('appli', $p->appareil);
        $this->assertSame(1, VisiteursEnDirect::compte()['appli']);
    }

    public function test_la_source_d_arrivee_est_gardee_pour_la_visite(): void
    {
        $this->battement(['utm' => 'instagram']);
        $this->battement(['ref' => 'https://www.google.com/']);

        $this->assertSame('instagram', Presence::sole()->source);
    }

    public function test_un_visiteur_parti_n_est_plus_en_ligne(): void
    {
        Carbon::setTestNow('2026-10-12 10:00:00');
        $this->battement();

        Carbon::setTestNow('2026-10-12 10:02:59');
        $this->assertSame(1, VisiteursEnDirect::compte()['humains']);

        Carbon::setTestNow('2026-10-12 10:03:01');
        $this->assertSame(0, VisiteursEnDirect::compte()['humains']);
    }

    public function test_trafic_non_confirme_separe_des_humains(): void
    {
        // Le serveur voit trois adresses ; une seule confirme sa présence.
        $this->withHeader('User-Agent', self::IPHONE)->get('/')->assertOk();
        $this->battement();
        foreach (['10.0.0.1', '10.0.0.2'] as $ip) {
            LiveVisit::create(['ip_hash' => hash('sha256', $ip . config('app.key')), 'path' => '/', 'last_seen_at' => now()]);
        }

        $compte = VisiteursEnDirect::compte();
        $this->assertSame(1, $compte['humains']);
        $this->assertSame(2, $compte['non_confirmes']);
    }

    public function test_membre_connecte_et_appareils(): void
    {
        $membre = User::create(['name' => 'Léa', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'city' => 'Saint-Pierre']);

        $this->actingAs($membre)->battement([], self::PC);

        $compte = VisiteursEnDirect::compte();
        $this->assertSame(1, $compte['membres']);
        $this->assertSame(1, $compte['ordinateur']);

        // Sur la carte : placé sur sa commune, pas au hasard.
        $points = VisiteursEnDirect::pointsCarte(VisiteursEnDirect::enLigne());
        $this->assertSame('Saint-Pierre', $points[0]['libelle']);
        $this->assertSame('membre', $points[0]['type']);
    }

    public function test_les_visiteurs_anonymes_sont_regroupes_par_ile(): void
    {
        foreach (['a', 'b'] as $cle) {
            Presence::create(['cle' => $cle, 'ip_hash' => $cle, 'territoire' => 'Martinique', 'debut_at' => now(), 'last_seen_at' => now()]);
        }

        $points = VisiteursEnDirect::pointsCarte(VisiteursEnDirect::enLigne());

        $this->assertCount(1, $points);
        $this->assertSame(['Martinique', 2, 'ile'], [$points[0]['libelle'], $points[0]['nombre'], $points[0]['type']]);
    }

    public static function sources(): array
    {
        return [
            'pub facebook' => ['fb', null, self::IPHONE, 'facebook'],
            'fbclid dans instagram' => ['fbclid', null, 'Mozilla/5.0 (iPhone) Instagram 300.0', 'instagram'],
            'navigateur facebook' => [null, null, 'Mozilla/5.0 (iPhone) [FBAN/FBIOS;FBAV/450.0]', 'facebook'],
            'google' => [null, 'https://www.google.fr/', self::PC, 'google'],
            'gclid' => ['gclid', null, self::PC, 'google'],
            'tiktok' => ['ttclid', null, self::IPHONE, 'tiktok'],
            'bing' => [null, 'https://www.bing.com/search?q=x', self::PC, 'moteur'],
            'autre site' => [null, 'https://www.leboncoin.fr/', self::PC, 'site'],
            'notre propre site' => [null, 'https://swapiles.com/recherche', self::PC, 'direct'],
            'rien' => [null, null, self::PC, 'direct'],
            'digital n est pas instagram' => ['digital', null, self::PC, 'site'],
        ];
    }

    #[DataProvider('sources')]
    public function test_provenance(?string $utm, ?string $ref, string $ua, string $attendu): void
    {
        $this->assertSame($attendu, VisiteursEnDirect::source($utm, $ref, $ua, 'swapiles.com'));
    }

    public function test_appareil(): void
    {
        $this->assertSame('mobile', VisiteursEnDirect::appareil(self::IPHONE));
        $this->assertSame('ordinateur', VisiteursEnDirect::appareil(self::PC));
        $this->assertSame('tablette', VisiteursEnDirect::appareil('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)'));
        $this->assertSame('tablette', VisiteursEnDirect::appareil('Mozilla/5.0 (Linux; Android 14; SM-X200) Safari/537.36'));
    }

    public function test_le_script_est_charge_et_attend_un_geste(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#src="[^"]*/js/presence\.js#', $html);

        $js = file_get_contents(public_path('js/presence.js'));
        $this->assertStringContainsString("['pointerdown', 'touchstart', 'scroll', 'keydown', 'mousemove']", $js);
        $this->assertStringContainsString("document.visibilityState !== 'visible'", $js);
    }

    public function test_la_page_en_direct_s_affiche(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $this->battement(['utm' => 'fb']);

        $this->actingAs($admin)->get('/admin/live-visitors')
            ->assertOk()
            ->assertSee('Humains en ligne')
            ->assertSee('Trafic non confirmé')
            ->assertSee('Carte en direct')
            ->assertSee('Facebook');
    }
}
