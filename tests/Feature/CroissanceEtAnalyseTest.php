<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Listing;
use App\Models\LiveVisit;
use App\Models\Presence;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AnalyticsMetrics;
use App\Support\Croissance;
use App\Support\TableauDeBord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CroissanceEtAnalyseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-12-20 15:00', 'Indian/Reunion'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $nom, ?string $le = null): User
    {
        $u = User::create(['name' => $nom, 'email' => strtolower($nom) . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        if ($le) {
            $u->forceFill(['created_at' => Carbon::parse($le)])->save();
        }

        return $u;
    }

    private function annonce(User $vendeur, string $le, string $statut = 'published'): Listing
    {
        $a = Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => $statut, 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
        $a->forceFill(['created_at' => Carbon::parse($le), 'updated_at' => Carbon::parse($le)])->save();

        return $a;
    }

    /** Vente payée en ligne : 20 € + 2 € de protection. */
    private function vente(Listing $annonce, User $acheteur, string $le, array $plus = []): Transaction
    {
        $t = Transaction::create($plus + [
            'listing_id' => $annonce->id, 'seller_id' => $annonce->user_id, 'buyer_id' => $acheteur->id,
            'amount' => 22, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2, 'shipping_fee' => 0,
            'currency' => 'EUR', 'payment_method' => 'cb', 'delivery_method' => 'hand_delivery', 'status' => 'paid',
            'shipping_status' => 'pending', 'stripe_payment_intent_id' => 'pi_' . uniqid(),
        ]);
        $t->forceFill(['created_at' => Carbon::parse($le)])->save();

        return $t;
    }

    private function visiteurHumain(string $vid, string $jour, array $plus = []): void
    {
        DB::table('visiteurs_jours')->insert($plus + [
            'jour' => $jour,
            'cle' => hash('sha256', 'presence|' . $vid . config('app.key')),
            'source' => 'facebook', 'territoire' => 'La Réunion', 'appli' => false,
            'arrivee_at' => Carbon::parse($jour . ' 18:10'),
        ]);
    }

    private function pageVue(string $vid, string $le, string $page = 'Accueil'): void
    {
        AnalyticsEvent::create([
            'visitor_id' => $vid, 'session_id' => 's' . $vid, 'ip_address' => '10.0.0.1', 'method' => 'GET',
            'path' => '/', 'page_name' => $page, 'created_at' => Carbon::parse($le),
        ]);
    }

    public function test_analyse_ne_compte_que_les_humains_confirmes(): void
    {
        $this->visiteurHumain('humainA', '2026-12-18');
        $this->visiteurHumain('humainA', '2026-12-19');
        $this->visiteurHumain('humainB', '2026-12-19', ['source' => 'google', 'appli' => true]);
        $this->pageVue('humainA', '2026-12-18 18:11');
        $this->pageVue('humainA', '2026-12-18 18:12', 'Page annonce');
        $this->pageVue('humainB', '2026-12-19 09:00', 'Page annonce');
        // 40 robots déguisés : des pages vues, jamais de présence confirmée.
        for ($i = 0; $i < 40; $i++) {
            $this->pageVue('robot' . $i, '2026-12-19 03:00');
        }

        $m = AnalyticsMetrics::advanced('7j');

        $this->assertSame(2, $m['visiteurs']['uniques']);
        $this->assertSame(1, $m['visiteurs']['fideles'], 'A est venu 2 jours.');
        $this->assertSame(1, $m['visiteurs']['appli']);
        $this->assertSame(['Facebook' => 2, 'Google' => 1], $m['visiteurs']['sources']);
        $this->assertSame(3, $m['visiteurs']['par_heure'][18]);
        $this->assertSame(3, $m['pages']['vues'], 'Les 40 pages des robots sont exclues.');
        $this->assertSame(1.5, $m['pages']['par_visite']);
        $this->assertSame(['Page annonce' => 2, 'Accueil' => 1], $m['pages']['top']);
    }

    public function test_argent_identique_au_tableau_de_bord_et_entonnoir_par_cohorte(): void
    {
        $ancien = $this->membre('Ancien', '2026-06-01');
        $lea = $this->membre('Lea', '2026-12-10');
        $tom = $this->membre('Tom', '2026-12-11');
        $this->membre('Zoe', '2026-12-12');

        $this->vente($this->annonce($lea, '2026-12-10'), $tom, '2026-12-12');
        // L'ancien membre vend aussi, mais il ne fait pas partie des inscrits de la période.
        $this->vente($this->annonce($ancien, '2026-12-01'), $tom, '2026-12-13');
        // Main propre déclarée : comptée à part, ne rapporte rien.
        $this->vente($this->annonce($ancien, '2026-12-02'), $lea, '2026-12-14', ['payment_method' => 'especes', 'stripe_payment_intent_id' => null, 'buyer_protection_fee' => 0, 'amount' => 20]);

        $m = AnalyticsMetrics::advanced('30j');
        $tdb = TableauDeBord::periode('30j');

        $this->assertEqualsWithDelta($tdb['volume'], $m['gmv'], 0.001);
        $this->assertEqualsWithDelta($tdb['revenus'], $m['netRevenue'], 0.001);
        $this->assertSame(2, $m['paidCount']);
        $this->assertEqualsWithDelta(44.0, $m['gmv'], 0.001);
        $this->assertEqualsWithDelta(4.0, $m['netRevenue'], 0.001);
        $this->assertSame(1, $m['ventesDeclarees']);

        $this->assertSame(['visiteurs' => 0, 'inscrits' => 3, 'publie' => 1, 'vendu' => 1, 'achete' => 1], $m['funnel']);
    }

    public function test_croissance_mois_par_mois(): void
    {
        $lea = $this->membre('Lea', '2026-09-05');
        $tom = $this->membre('Tom', '2026-09-06');
        $zoe = $this->membre('Zoe', '2026-10-01');

        // Septembre : 1 vente. Octobre : 2 ventes, dont Tom qui revient.
        $vendueVite = $this->annonce($lea, '2026-09-10');
        $this->vente($vendueVite, $tom, '2026-09-20');
        $this->annonce($lea, '2026-09-11'); // jamais vendue
        $this->vente($this->annonce($lea, '2026-10-02'), $tom, '2026-10-03');
        $this->vente($this->annonce($lea, '2026-10-04'), $zoe, '2026-10-05', ['credit_utilise' => 1, 'amount' => 21]);

        $mois = collect(Croissance::mois())->keyBy(fn ($m) => $m['mois']->format('Y-m'));

        $sept = $mois['2026-09'];
        $this->assertSame(1, $sept['ventes']);
        $this->assertEqualsWithDelta(22.0, $sept['volume'], 0.001);
        $this->assertEqualsWithDelta(2.0, $sept['revenus'], 0.001);
        $this->assertSame(50.0, $sept['liquidite'], '1 annonce sur 2 vendue en moins de 60 jours.');
        $this->assertNull($sept['visiteurs'], 'Avant le comptage fiable : pas de chiffre.');

        $oct = $mois['2026-10'];
        $this->assertSame(2, $oct['ventes']);
        $this->assertEqualsWithDelta(44.0, $oct['volume'], 0.001, 'Carte 21 € + crédit 1 € + 22 €.');
        $this->assertEqualsWithDelta(3.0, $oct['revenus'], 0.001, '2 × 2 € de protection − 1 € de crédit.');
        $this->assertEqualsWithDelta(22.0, $oct['panier_moyen'], 0.001);
        $this->assertSame(50.0, $oct['fidelite'], 'Tom revient, Zoé achète pour la première fois.');
        $this->assertSame(100, $oct['evolution_volume']);
        $this->assertNull($oct['liquidite'], 'Annonces d’octobre : délai de 60 jours pas encore écoulé.');

        $this->assertNull($mois['2026-12']['liquidite'], 'Délai de 60 jours pas écoulé.');
        $this->assertTrue($mois['2026-12']['en_cours']);

        $csv = Croissance::csv($mois->values()->all());
        $this->assertStringContainsString('"Octobre 2026";"0";"0";"1";"2";"2";"44";"3";"22"', $csv);
    }

    public function test_paliers(): void
    {
        $this->assertSame(1, Croissance::palier(['ventes' => 120, 'liquidite' => 30, 'revenus' => 300, 'visiteurs_jour' => 200], [])['niveau']);

        $p = Croissance::palier(['ventes' => 350, 'liquidite' => 25, 'revenus' => 900, 'visiteurs_jour' => 250], []);
        $this->assertSame(2, $p['niveau']);
        $this->assertSame('Plateforme sérieuse', $p['suivant']['nom']);
        $this->assertSame([11, 25, 18], array_column($p['progression'], 'pct'));
    }

    public function test_pic_de_connectes_ne_compte_que_les_humains(): void
    {
        Presence::create(['cle' => 'p1', 'ip_hash' => 'ip1', 'path' => '/', 'appareil' => 'mobile', 'source' => 'direct', 'debut_at' => now(), 'last_seen_at' => now()]);
        foreach (range(1, 5) as $i) {
            LiveVisit::create(['ip_hash' => 'robot' . $i, 'path' => '/', 'last_seen_at' => now()]);
        }

        $this->artisan('traffic:snapshot')->assertSuccessful();

        $this->assertSame(1, (int) DB::table('visitor_snapshots')->value('live_count'));
        $this->assertSame(1, AnalyticsMetrics::connectesDuJour()['pic']['nombre']);
    }

    public function test_pages_admin_s_affichent(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $this->visiteurHumain('humainA', '2026-12-19');

        $this->actingAs($admin);
        $this->get('/admin/croissance')->assertOk()->assertSee('Palier actuel')->assertSee('Mois par mois')->assertSee('Décembre 2026');
        $this->get('/admin/advanced-analytics')->assertOk()->assertSee('Du visiteur au vendeur')->assertSee('Visiteurs uniques');
        $this->get('/admin/advanced-analytics?period=tout')->assertOk()->assertSee('Visiteurs comptés depuis le 12/10/2026');
    }
}
