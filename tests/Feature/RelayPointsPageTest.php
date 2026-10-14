<?php

namespace Tests\Feature;

use App\Models\RelayPoint;
use App\Models\RelayPointRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Page publique « Nos points relais » et demande d'un point relais plus proche.
 */
class RelayPointsPageTest extends TestCase
{
    use RefreshDatabase;

    private function point(string $nom, string $ile = 'La Réunion', bool $actif = true, string $ville = 'Saint-Gilles'): RelayPoint
    {
        return RelayPoint::create([
            'name' => $nom, 'territoire' => $ile, 'city' => $ville, 'address' => '4 rue de la Plage',
            'postal_code' => '97434', 'opening_hours' => 'Lun-Sam 10h-18h', 'is_active' => $actif,
        ]);
    }

    private function demande(array $plus = [])
    {
        return $this->post(route('relay.points.request'), $plus + [
            'territoire' => 'La Réunion', 'city' => 'Saint-Leu', 'business_name' => 'Épicerie du Port',
            'business_address' => 'face à la mairie', 'already_relay' => 'oui', 'networks' => ['mondial_relay', 'pickup'],
            'name' => 'Léa', 'email' => 'lea@ex.com',
        ]);
    }

    public function test_la_page_liste_les_points_actifs_de_l_ile(): void
    {
        $this->point('Tao Bijoux');
        $this->point('Boutique fermée', actif: false);
        $this->point('Relais Fort-de-France', 'Martinique', ville: 'Fort-de-France');

        $this->get(route('relay.points', ['ile' => 'La Réunion']))
            ->assertOk()
            ->assertSee('Nos points relais')
            ->assertSee('Tao Bijoux')
            ->assertSee('Lun-Sam 10h-18h')
            ->assertSee('Itinéraire')
            ->assertDontSee('Boutique fermée')
            ->assertDontSee('Relais Fort-de-France')
            ->assertSee('Vous souhaitez un point relais plus proche de chez vous ?');
    }

    public function test_une_ile_sans_point_relais_invite_a_demander(): void
    {
        $this->get(route('relay.points', ['ile' => 'Mayotte']))
            ->assertOk()
            ->assertSee('Pas encore de point relais à Mayotte')
            ->assertSee('Faire la demande');
    }

    public function test_la_demande_est_enregistree_et_l_equipe_prevenue(): void
    {
        Mail::fake();

        $this->demande()->assertRedirect(route('relay.points', ['ile' => 'La Réunion']) . '#demande')
            ->assertSessionHas('relay_request_status');

        $d = RelayPointRequest::sole();
        $this->assertSame('Saint-Leu', $d->city);
        $this->assertSame(['mondial_relay', 'pickup'], $d->networks);
        $this->assertSame('Mondial Relay, Pickup (Chronopost / DPD)', $d->reseauxLisibles());
        $this->assertSame('nouvelle', $d->status);
    }

    public function test_les_reseaux_ne_sont_gardes_que_si_le_commerce_est_deja_relais(): void
    {
        $this->demande(['already_relay' => 'non']);

        $this->assertNull(RelayPointRequest::sole()->networks);
    }

    public function test_champs_obligatoires_et_valeurs_inconnues(): void
    {
        $this->demande(['city' => '', 'email' => 'pas-un-email', 'territoire' => 'Paris', 'networks' => ['inconnu']])
            ->assertSessionHasErrors(['city', 'email', 'territoire', 'networks.0']);

        $this->assertSame(0, RelayPointRequest::count());
    }

    public function test_les_robots_sont_ignores(): void
    {
        $this->demande(['website' => 'http://spam.example'])->assertRedirect();

        $this->assertSame(0, RelayPointRequest::count());
    }

    public function test_un_membre_connecte_est_rattache_et_pre_rempli(): void
    {
        $membre = User::create(['name' => 'Paul', 'email' => 'paul@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'city' => 'Saint-Pierre']);

        $this->actingAs($membre)->get(route('relay.points'))->assertSee('value="paul@ex.com"', false)->assertSee('value="Saint-Pierre"', false);
        $this->actingAs($membre)->demande();

        $this->assertSame($membre->id, RelayPointRequest::sole()->user_id);
    }

    public function test_liens_dans_le_pied_de_page_et_le_menu(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertSame(2, substr_count($html, 'href="' . route('relay.points') . '"'), 'Pied de page + menu hamburger.');
        $this->assertStringContainsString('📍 Nos points relais', $html);
    }

    public function test_l_admin_voit_les_demandes(): void
    {
        $this->demande();
        $admin = User::create(['name' => 'A', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        $this->actingAs($admin)->get('/admin/relay-point-requests')
            ->assertOk()
            ->assertSee('Saint-Leu')
            ->assertSee('Épicerie du Port')
            ->assertSee('Mondial Relay');
    }
}
