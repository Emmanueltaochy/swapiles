<?php

namespace Tests\Feature;

use App\Console\Commands\EnvoyerProspectionRelais;
use App\Console\Commands\ProspecterRelais;
use App\Models\Listing;
use App\Models\RelaisProspect;
use App\Models\RelayPoint;
use App\Models\User;
use App\Support\ProspectionRelais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Prospection des points relais : commerces OpenStreetMap, e-mail du site, mail de présentation. */
class ProspectionRelaisTest extends TestCase
{
    use RefreshDatabase;

    private function fauxOpenStreetMap(): void
    {
        Http::fake([
            'overpass-api.de/*' => Http::response(['elements' => [
                ['type' => 'node', 'id' => 1, 'lat' => -21.3, 'lon' => 55.4, 'tags' => [
                    'shop' => 'tobacco', 'name' => 'Tabac du Port', 'addr:city' => 'Saint-Pierre', 'addr:street' => 'Rue Désiré Barquisseau',
                    'phone' => '0262 11 22 33', 'email' => 'tabacduport@exemple-re.fr',
                ]],
                ['type' => 'way', 'id' => 2, 'center' => ['lat' => -21.0, 'lon' => 55.3], 'tags' => [
                    'shop' => 'laundry', 'name' => 'Laverie Bleue', 'addr:city' => 'Saint-Paul', 'website' => 'laverie-bleue.re',
                ]],
                ['type' => 'node', 'id' => 3, 'lat' => -21.1, 'lon' => 55.5, 'tags' => ['amenity' => 'fuel', 'name' => 'Station Relais Déjà']],
                ['type' => 'node', 'id' => 4, 'lat' => -21.1, 'lon' => 55.5, 'tags' => ['shop' => 'convenience']], // sans nom : ignoré
            ]]),
            'laverie-bleue.re/*' => Http::response('<html><a href="mailto:contact@laverie-bleue.re">Écrivez-nous</a><img src="logo@2x.png"></html>'),
            'laverie-bleue.re' => Http::response('<html><a href="mailto:contact@laverie-bleue.re">Écrivez-nous</a></html>'),
        ]);
    }

    public function test_import_sans_doublon_et_email_du_site(): void
    {
        $this->fauxOpenStreetMap();
        RelayPoint::create(['name' => 'Station Relais Déjà', 'territoire' => 'La Réunion', 'address' => '1 rue', 'city' => 'Saint-Denis', 'is_active' => true]);

        $r = ProspectionRelais::importer('La Réunion');
        $this->assertSame(['trouves' => 4, 'nouveaux' => 3], $r);
        // Une deuxième recherche ne crée pas de doublon.
        $this->assertSame(0, ProspectionRelais::importer('La Réunion')['nouveaux']);

        $tabac = RelaisProspect::where('nom', 'Tabac du Port')->firstOrFail();
        $this->assertSame('tabacduport@exemple-re.fr', $tabac->email);
        $this->assertSame('Saint-Pierre', $tabac->ville);
        $this->assertSame('relais', RelaisProspect::where('nom', 'Station Relais Déjà')->value('statut'));

        $this->assertSame(1, ProspectionRelais::chercherEmails());
        $this->assertSame('contact@laverie-bleue.re', RelaisProspect::where('nom', 'Laverie Bleue')->value('email'));
    }

    public function test_emails_invalides_ecartes(): void
    {
        $this->assertNull(ProspectionRelais::emailValide('logo@2x.png'));
        $this->assertNull(ProspectionRelais::emailValide('nom@example.com'));
        $this->assertNull(ProspectionRelais::emailValide('pas-un-email'));
        $this->assertSame('boutique@commerce.re', ProspectionRelais::emailValide(' Boutique@Commerce.re. '));
    }

    public function test_envoi_par_ville_la_plus_demandee_plafond_et_desinscription(): void
    {
        Mail::fake();
        config()->set('relais.prospection_par_jour', 1);
        $vendeur = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        Listing::create(['user_id' => $vendeur->id, 'title' => 'Robe', 'description' => 'Description de test assez longue.', 'price' => 10,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'pickup_city' => 'Saint-Paul']);

        $calme = RelaisProspect::create(['source_id' => 'osm:node/10', 'nom' => 'Épicerie Calme', 'ville' => 'Cilaos', 'territoire' => 'La Réunion', 'email' => 'calme@commerce.re']);
        $demandee = RelaisProspect::create(['source_id' => 'osm:node/11', 'nom' => 'Laverie Bleue', 'ville' => 'Saint-Paul', 'territoire' => 'La Réunion', 'email' => 'bleue@commerce.re']);

        $this->artisan('relais:prospection-envoyer')->assertSuccessful();

        // Plafond de 1 : seule la ville où il y a des annonces est contactée.
        $this->assertSame('contacte', $demandee->fresh()->statut);
        $this->assertSame('nouveau', $calme->fresh()->statut);
        $this->assertSame(1, ProspectionRelais::envoyesAujourdhui());
        $this->assertFalse(ProspectionRelais::envoyer($calme->fresh()));
        // Jamais deux fois le même commerce.
        config()->set('relais.prospection_par_jour', 10);
        $this->assertFalse(ProspectionRelais::envoyer($demandee->fresh()));

        // Lien « Ne plus être contacté ».
        $this->get(route('relais.prospection.desinscription', $calme->jeton))->assertOk()->assertSee('C’est noté', false);
        $this->assertSame('desinscrit', $calme->fresh()->statut);
        $this->assertFalse(ProspectionRelais::envoyer($calme->fresh()));
    }

    public function test_le_mail_contient_l_offre_et_le_lien_de_desinscription(): void
    {
        $p = RelaisProspect::create(['source_id' => 'osm:node/12', 'nom' => 'Tabac du Port', 'ville' => 'Saint-Pierre', 'territoire' => 'La Réunion', 'email' => 'tabac@commerce.re']);

        $html = view('emails.prospection-relais', [
            'p' => $p, 'commission' => 1.0, 'garde' => 10, 'lienPartenaire' => route('relay.partner'),
            'lienDesinscription' => route('relais.prospection.desinscription', $p->jeton), 'contact' => config('partenaires.contact'),
        ])->render();

        $this->assertStringContainsString('Tabac du Port', $html);
        $this->assertStringContainsString('1 € par colis', $html);
        $this->assertStringContainsString('Ne plus être contacté', $html);
        $this->assertStringContainsString($p->jeton, $html);
    }

    public function test_pause_et_recherche_demandee_depuis_l_admin(): void
    {
        Mail::fake();
        RelaisProspect::create(['source_id' => 'osm:node/13', 'nom' => 'Presse', 'ville' => 'Saint-Denis', 'territoire' => 'La Réunion', 'email' => 'presse@commerce.re']);

        Cache::forever(EnvoyerProspectionRelais::PAUSE, true);
        $this->artisan('relais:prospection-envoyer')->assertSuccessful();
        $this->assertSame(0, ProspectionRelais::envoyesAujourdhui());
        // Le bouton de l'admin envoie quand même.
        $this->artisan('relais:prospection-envoyer', ['--force' => true])->assertSuccessful();
        $this->assertSame(1, ProspectionRelais::envoyesAujourdhui());

        // Sans demande, la vérification de chaque minute ne fait rien.
        $this->fauxOpenStreetMap();
        $this->artisan('relais:prospects', ['--si-demande' => true])->assertSuccessful();
        Http::assertNothingSent();

        Cache::put(ProspecterRelais::DEMANDE, 'La Réunion', now()->addHour());
        $this->artisan('relais:prospects', ['--si-demande' => true])->assertSuccessful();
        $this->assertTrue(RelaisProspect::where('nom', 'Tabac du Port')->exists());
        $this->assertNull(Cache::get(ProspecterRelais::DEMANDE));
    }

    public function test_demande_triable_ville_retrouvee_par_la_position(): void
    {
        $vendeur = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        foreach (['St Pierre', 'Saint-Pierre', 'saint pierre'] as $ville) {
            Listing::create(['user_id' => $vendeur->id, 'title' => 'Robe', 'description' => 'Description de test assez longue.', 'price' => 10,
                'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'pickup_city' => $ville]);
        }
        // Commerce sans adresse, situé près du centre de Saint-Pierre.
        $sansVille = RelaisProspect::create(['source_id' => 'osm:node/20', 'nom' => 'Supérette du front de mer', 'territoire' => 'La Réunion', 'latitude' => -21.340, 'longitude' => 55.480]);
        $ailleurs = RelaisProspect::create(['source_id' => 'osm:node/21', 'nom' => 'Épicerie Cilaos', 'ville' => 'Cilaos', 'territoire' => 'La Réunion']);

        ProspectionRelais::recalculerDemande();

        $this->assertSame('Saint-Pierre', $sansVille->fresh()->ville);
        $this->assertSame(3, $sansVille->fresh()->demande);
        $this->assertSame(0, $ailleurs->fresh()->demande);
        $this->assertSame(['Supérette du front de mer', 'Épicerie Cilaos'], RelaisProspect::orderByDesc('demande')->pluck('nom')->all());
    }

    public function test_page_admin(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        RelaisProspect::create(['source_id' => 'osm:node/14', 'nom' => 'Laverie Bleue', 'ville' => 'Saint-Paul', 'territoire' => 'La Réunion', 'email' => 'bleue@commerce.re', 'type' => 'laundry']);

        $this->actingAs($admin)->get('/admin/relais-prospects')->assertOk()
            ->assertSee('Laverie Bleue')->assertSee('Laverie')->assertSee('Rechercher des commerces');
    }
}
