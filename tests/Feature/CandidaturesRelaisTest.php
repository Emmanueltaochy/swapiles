<?php

namespace Tests\Feature;

use App\Filament\Resources\CandidaturesRelais\Pages\ListCandidaturesRelais;
use App\Models\CandidatureRelais;
use App\Models\Notification;
use App\Models\RelayPoint;
use App\Models\User;
use App\Support\CandidaturesRelaisBoite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Commerçants qui veulent devenir point relais : enregistrés dans l'admin
 * (séparés des membres qui veulent un relais près de chez eux), récupérés
 * depuis les anciens e-mails de contact@, transformés en point relais.
 */
class CandidaturesRelaisTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Emmanuel', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
    }

    private function formulaire(array $autres = []): array
    {
        return array_merge(['business' => 'Hortensia', 'name' => 'Sanquer', 'city' => 'Deshaies', 'phone' => '0690 00 00 00',
            'email' => 'hortensia@ex.com', 'hours' => 'Lundi au samedi 9h-18h', 'message' => ''], $autres);
    }

    public function test_le_formulaire_enregistre_la_candidature_et_previent_emmanuel(): void
    {
        $admin = $this->admin();
        $compte = User::create(['name' => 'Catherine', 'email' => 'hortensia@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'Guadeloupe']);

        $this->post(route('relay.partner.contact'), $this->formulaire())->assertSessionHas('relay_contact_status');
        $this->post(route('relay.partner.contact'), $this->formulaire()); // envoyé deux fois : pas de doublon

        $c = CandidatureRelais::sole();
        $this->assertSame('Hortensia', $c->commerce);
        $this->assertSame('Deshaies', $c->ville);
        $this->assertSame($compte->id, $c->user_id);
        $this->assertSame('Guadeloupe', $c->territoire);
        $this->assertSame('nouvelle', $c->statut);
        $this->assertSame(1, Notification::where('user_id', $admin->id)->where('type', 'candidature_relais')->count());
    }

    public function test_les_anciennes_demandes_recues_par_mail_sont_recuperees(): void
    {
        $brut = "From: Swap'Îles <contact@swapiles.com>\r\nTo: contact@swapiles.com\r\nSubject: =?UTF-8?Q?Point_relais_partenaire_=E2=80=94_Hortensia_(deshaies)?=\r\n"
            . "Message-ID: <abc123@swapiles.com>\r\nDate: Wed, 11 Nov 2026 17:58:00 +0400\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n"
            . "Nouvelle demande de point relais partenaire :\r\n\r\nCommerce : Hortensia\r\nContact : sanquer\r\nVille : deshaies\r\nTéléphone : 0690 00 00 00\r\n"
            . "E-mail : Hortensia@Ex.com\r\nHoraires : Lundi\r\n\r\nMessage : —\r\n";

        $this->assertTrue(CandidaturesRelaisBoite::depuisMessage($brut));
        $this->assertFalse(CandidaturesRelaisBoite::depuisMessage($brut)); // déjà connue

        $c = CandidatureRelais::sole();
        $this->assertSame('Hortensia', $c->commerce);
        $this->assertSame('hortensia@ex.com', $c->email);
        $this->assertSame('Lundi', $c->horaires);
        $this->assertNull($c->message);
        $this->assertSame('boite_mail', $c->source);
        $this->assertSame('2026-11-11', $c->created_at->toDateString());

        // Un mail qui n'est pas une demande de partenaire est ignoré.
        $this->assertFalse(CandidaturesRelaisBoite::depuisMessage("Subject: Bonjour\r\n\r\nUn autre message"));
    }

    public function test_creer_le_point_relais_depuis_l_admin(): void
    {
        $this->actingAs($this->admin());
        $commercante = User::create(['name' => 'Catherine', 'email' => 'hortensia@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'Guadeloupe']);
        $c = CandidatureRelais::create(['commerce' => 'Hortensia', 'ville' => 'Deshaies', 'territoire' => 'Guadeloupe', 'user_id' => $commercante->id,
            'telephone' => '0690 00 00 00', 'email' => 'hortensia@ex.com']);

        $this->get('/admin/candidatures-relais')->assertOk()->assertSee('Hortensia')->assertSee('Commerces candidats');
        $this->get('/admin/bureau-du-ceo')->assertOk()->assertSee('Commerces qui veulent devenir point relais (1)')->assertSee('tel:0690000000', false);

        Livewire::test(ListCandidaturesRelais::class)
            ->callTableAction('creerRelais', $c, [
                'name' => 'Hortensia', 'compte_email' => 'Hortensia@ex.com', 'territoire' => 'Guadeloupe',
                'address' => '1 rue du Port', 'postal_code' => '97126', 'city' => 'Deshaies', 'opening_hours' => 'Lun-sam 9h-18h', 'is_active' => true,
            ])->assertHasNoTableActionErrors();

        $relais = RelayPoint::sole();
        $this->assertSame($commercante->id, $relais->manager_user_id);
        $this->assertSame('point_relais_cree', $c->fresh()->statut);
        $this->assertSame($relais->id, $c->fresh()->relay_point_id);
        $this->get('/admin/bureau-du-ceo')->assertDontSee('Commerces qui veulent devenir point relais');
    }
}
