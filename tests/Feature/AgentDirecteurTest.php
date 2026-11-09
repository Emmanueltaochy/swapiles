<?php

namespace Tests\Feature;

use App\Filament\Pages\BureauDuCeo;
use App\Models\AgentRapport;
use App\Models\AgentReponse;
use App\Models\Notification;
use App\Models\ProspectRse;
use App\Models\RelaisProspect;
use App\Models\Reglage;
use App\Models\User;
use App\Support\Agent\BoiteReponses;
use App\Support\Agent\Courriel;
use App\Support\Agent\Prospection;
use App\Support\Agent\ProspectionRse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Agent directeur : prospection (boîte no-reply, montée en douceur,
 * collectivités après validation, une relance), réponses triées, résumé
 * quotidien et Bureau du CEO.
 */
class AgentDirecteurTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Lundi 8 h 05 à La Réunion : début de la journée de prospection.
        Carbon::setTestNow(Carbon::parse('2026-11-16 08:05:00', 'Indian/Reunion'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Boîte no-reply@ branchée, envois capturés en mémoire. */
    private function boiteNoReply(): void
    {
        config()->set('mail.mailers.prospection', ['transport' => 'array', 'username' => 'no-reply@swapiles.com', 'password' => 'secret']);
        config()->set('agent.expediteur.adresse', 'no-reply@swapiles.com');
    }

    /** @return \Symfony\Component\Mime\Email[] */
    private function mailsProspection(): array
    {
        return app('mail.manager')->mailer('prospection')->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage())->all();
    }

    private function fauxClaude(array $reponse): void
    {
        $objet = (object) [
            'stopReason' => 'end_turn',
            'usage' => (object) ['inputTokens' => 3000, 'outputTokens' => 600, 'cacheCreationInputTokens' => 0, 'cacheReadInputTokens' => 0],
            'content' => [(object) ['type' => 'text', 'text' => json_encode($reponse)]],
        ];
        $this->app->instance('agent.client', (object) ['messages' => new class($objet) {
            public function __construct(private object $r) {}

            public function create(...$args): object
            {
                return $this->r;
            }
        }]);
    }

    private function admin(): User
    {
        return User::create(['name' => 'Emmanuel', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
    }

    private function organisme(array $autres = []): ProspectRse
    {
        static $n = 0;
        $n++;

        return ProspectRse::create(array_merge(['source_id' => 'test-' . $n, 'nom' => 'CCAS de Saint-Paul', 'type' => 'ccas', 'territoire' => 'La Réunion',
            'ville' => 'Saint-Paul', 'email' => "ccas{$n}@mairie-saintpaul.fr"], $autres));
    }

    public function test_plafond_progressif_de_la_nouvelle_boite_et_repartition_sur_la_journee(): void
    {
        config()->set('relais.prospection_par_jour', 40);
        $this->assertSame(40, Prospection::plafondJour()); // sans boîte séparée : comme avant

        $this->boiteNoReply();
        $this->assertSame(20, Prospection::plafondJour());
        Reglage::ecrire(Prospection::DEBUT, now()->subDays(8)->toDateString());
        $this->assertSame(40, Prospection::plafondJour());
        Reglage::ecrire(Prospection::DEBUT, now()->subDays(20)->toDateString());
        $this->assertSame(80, Prospection::plafondJour());

        // 80 à envoyer de 8 h à 16 h (9 tranches) : 9 maintenant, rien après 16 h.
        $this->assertSame(9, Prospection::partDeCetteHeure());
        $this->assertSame(0, Prospection::partDeCetteHeure(now()->setTime(17, 5)));
        $this->assertSame(0, Prospection::partDeCetteHeure(now()->setTime(7, 5)));
    }

    public function test_mail_aux_collectivites_depuis_no_reply_reponses_sur_contact(): void
    {
        $this->boiteNoReply();
        $p = $this->organisme();

        $this->assertTrue(ProspectionRse::envoyer($p));

        $mail = $this->mailsProspection()[0];
        $this->assertSame('no-reply@swapiles.com', $mail->getFrom()[0]->getAddress());
        $this->assertSame('Emmanuel – Swap’Îles', $mail->getFrom()[0]->getName());
        $this->assertSame('contact@swapiles.com', $mail->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString($p->jeton, $mail->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertStringContainsString('Pouvoir d’achat à Saint-Paul', $mail->getSubject());
        $this->assertStringContainsString('familles que vous accompagnez', $mail->getHtmlBody());
        $this->assertStringContainsString('annuaire officiel de l’administration', $mail->getHtmlBody());
        $this->assertSame('contacte', $p->fresh()->statut);
        $this->assertNotNull(Reglage::lire(Prospection::DEBUT)); // la montée en douceur commence

        // Jamais deux fois le premier mail.
        $this->assertFalse(ProspectionRse::envoyer($p->fresh()));
    }

    public function test_import_de_l_annuaire_officiel(): void
    {
        Http::fake(['api-lannuaire.service-public.fr/*' => Http::response(['total_count' => 3, 'results' => [
            ['id' => 'a1', 'nom' => 'Centre communal d\'action sociale (CCAS) - Le Port', 'adresse_courriel' => 'ccas@ville-port.re',
                'pivot' => '[{"type_service_local": "ccas", "code_insee_commune": ["97407"]}]',
                'adresse' => '[{"code_postal": "97420", "nom_commune": "Le Port Cedex"}]', 'site_internet' => null, 'telephone' => '[{"valeur": "02 62 00 00 00"}]'],
            ['id' => 'a2', 'nom' => 'Communauté d\'agglomération - TCO', 'adresse_courriel' => 'courrier@tco.re',
                'pivot' => '[{"type_service_local": "epci"}]', 'adresse' => null, 'site_internet' => null, 'telephone' => null],
            ['id' => 'a3', 'nom' => 'Service sans intérêt', 'adresse_courriel' => 'x@y.re', 'pivot' => '[{"type_service_local": "tribunal"}]'],
        ]])]);

        $r = ProspectionRse::importer('La Réunion');

        $this->assertSame(2, $r['nouveaux']);
        $ccas = ProspectRse::where('source_id', 'a1')->first();
        $this->assertSame('ccas', $ccas->type);
        $this->assertSame('Le Port', $ccas->ville);
        $this->assertSame('ccas@ville-port.re', $ccas->email);
        // Une 2e recherche ne crée pas de doublon et ne touche pas au suivi.
        $ccas->forceFill(['statut' => 'contacte'])->save();
        ProspectionRse::importer('La Réunion');
        $this->assertSame(2, ProspectRse::count());
        $this->assertSame('contacte', $ccas->fresh()->statut);
    }

    public function test_journee_de_prospection_commerces_puis_collectivites_apres_validation(): void
    {
        $this->boiteNoReply();
        $relais = RelaisProspect::create(['source_id' => 'osm:node/1', 'nom' => 'Épicerie', 'ville' => 'Saint-Paul', 'territoire' => 'La Réunion', 'email' => 'epicerie@commerce.re']);
        $ccas = $this->organisme();

        // Modèle collectivités pas encore validé : seulement les commerces.
        $this->artisan('agent:directeur', ['mission' => 'prospecter'])->assertSuccessful();
        $this->assertSame('contacte', $relais->fresh()->statut);
        $this->assertSame('nouveau', $ccas->fresh()->statut);

        // Pause depuis le Bureau du CEO : plus rien ne part.
        Reglage::ecrire(Prospection::PAUSE, true);
        Reglage::ecrire(Prospection::RSE_VALIDE, true);
        Carbon::setTestNow(now()->addHour());
        $this->artisan('agent:directeur', ['mission' => 'prospecter'])->assertSuccessful();
        $this->assertSame('nouveau', $ccas->fresh()->statut);

        Reglage::ecrire(Prospection::PAUSE, false);
        $this->artisan('agent:directeur', ['mission' => 'prospecter'])->assertSuccessful();
        $this->assertSame('contacte', $ccas->fresh()->statut);
        $this->assertCount(2, $this->mailsProspection());
    }

    public function test_une_seule_relance_au_bout_de_7_jours_sans_reponse(): void
    {
        $this->boiteNoReply();
        Reglage::ecrire(Prospection::RSE_VALIDE, true);
        $vieux = $this->organisme(['statut' => 'contacte', 'contacte_at' => now()->subDays(8), 'nb_envois' => 1]);
        $recent = $this->organisme(['statut' => 'contacte', 'contacte_at' => now()->subDays(3), 'nb_envois' => 1]);
        $repondu = $this->organisme(['statut' => 'repondu', 'contacte_at' => now()->subDays(9), 'nb_envois' => 1, 'repondu_at' => now()->subDays(2)]);

        $this->artisan('agent:directeur', ['mission' => 'prospecter'])->assertSuccessful();

        $this->assertSame('relance', $vieux->fresh()->statut);
        $this->assertSame(2, $vieux->fresh()->nb_envois);
        $this->assertSame('contacte', $recent->fresh()->statut);
        $this->assertSame('repondu', $repondu->fresh()->statut);
        $this->assertStringStartsWith('Re : ', $this->mailsProspection()[0]->getSubject());
        $this->assertStringContainsString('dernier message', $this->mailsProspection()[0]->getHtmlBody());
        $this->assertFalse(ProspectionRse::relancer($vieux->fresh())); // jamais une 2e relance
    }

    public function test_reponse_d_un_organisme_triee_et_alerte(): void
    {
        $admin = $this->admin();
        $p = $this->organisme(['statut' => 'contacte', 'contacte_at' => now()->subDay(), 'nb_envois' => 1, 'email' => 'courrier@tco.re', 'type' => 'epci', 'nom' => 'TCO']);
        $this->fauxClaude(['categorie' => 'interesse', 'resume' => 'Le service déchets veut un rendez-vous la semaine prochaine.', 'brouillon' => "Bonjour,\nAvec plaisir…\nEmmanuel Taochy, fondateur de Swap'Îles"]);

        // Réponse d'une autre personne du même organisme (même domaine).
        $brut = "From: =?UTF-8?Q?Jean_Hoarau?= <j.hoarau@tco.re>\r\nSubject: Re: =?UTF-8?Q?R=C3=A9emploi?=\r\nMessage-ID: <abc@tco.re>\r\nDate: Mon, 16 Nov 2026 07:30:00 +0400\r\n"
            . "Content-Type: multipart/alternative; boundary=\"b1\"\r\n\r\n--b1\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . "Bonjour, nous sommes int=C3=A9ress=C3=A9s.\r\n\r\nLe lun. 15 nov. 2026, Emmanuel a =C3=A9crit :\r\n> Bonjour, je m'appelle Emmanuel\r\n--b1--\r\n";
        $entetes = Courriel::entetes($brut);
        $trouve = BoiteReponses::prospectDe(Courriel::adresse($entetes['from']));
        $this->assertTrue($trouve->is($p));

        $this->assertTrue(BoiteReponses::traiter($trouve, $entetes, fn () => $brut));
        $this->assertFalse(BoiteReponses::traiter($trouve, $entetes, fn () => $brut)); // déjà vue

        $r = AgentReponse::sole();
        $this->assertSame('interesse', $r->categorie);
        $this->assertSame('Bonjour, nous sommes intéressés.', $r->extrait); // sans notre message cité
        $this->assertSame('interesse', $p->fresh()->statut);
        $this->assertNotNull($p->fresh()->repondu_at);
        $this->assertSame(1, Notification::where('user_id', $admin->id)->where('type', 'agent_reponse')->count());
    }

    public function test_desinscription_par_reponse_sans_ia_et_messages_des_membres_jamais_lus(): void
    {
        config()->set('ia_depot.cle', null);
        $relais = RelaisProspect::create(['source_id' => 'osm:node/2', 'nom' => 'Tabac', 'territoire' => 'La Réunion', 'email' => 'tabac@gmail.com', 'statut' => 'contacte', 'contacte_at' => now()->subDays(2)]);

        // Un membre qui écrit depuis Gmail n'est jamais rapproché par le domaine.
        $this->assertNull(BoiteReponses::prospectDe('marie.payet@gmail.com'));
        $this->assertNull(BoiteReponses::prospectDe('quelquun@inconnu.re'));
        $this->assertNull(BoiteReponses::prospectDe('no-reply@swapiles.com'));

        $p = BoiteReponses::prospectDe('tabac@gmail.com');
        $brut = "From: tabac@gmail.com\r\nSubject: Re: point relais\r\nMessage-ID: <x1@gmail.com>\r\n\r\nMerci de ne plus me contacter.\r\n";
        BoiteReponses::traiter($p, Courriel::entetes($brut), fn () => $brut);

        $this->assertSame('desinscription', AgentReponse::sole()->categorie);
        $this->assertSame('desinscrit', $relais->fresh()->statut);
    }

    public function test_resume_quotidien_et_bureau_du_ceo(): void
    {
        $admin = $this->admin();
        $this->fauxClaude(['resume' => 'Hier : 3 inscriptions. Bonne semaine.', 'actions' => [
            ['titre' => 'Relancer les annonces sans vente', 'pourquoi' => 'Beaucoup de vues.', 'comment' => '1. …', 'domaine' => 'produit'],
            ['titre' => 'Mettre en avant la Guadeloupe', 'pourquoi' => 'Île calme.', 'comment' => '1. …', 'domaine' => 'marketing'],
            ['titre' => 'Appeler le TCO', 'pourquoi' => 'Intéressé.', 'comment' => '1. …', 'domaine' => 'prospection'],
            ['titre' => 'Quatrième de trop', 'pourquoi' => '-', 'comment' => '-', 'domaine' => 'technique'],
        ]]);

        $this->artisan('agent:directeur', ['mission' => 'rapport'])->assertSuccessful();

        $r = AgentRapport::sole();
        $this->assertCount(3, $r->actions);
        $this->assertSame('Hier : 3 inscriptions. Bonne semaine.', $r->texte);
        $this->assertArrayHasKey('hier', $r->chiffres);
        $this->assertSame(1, Notification::where('user_id', $admin->id)->where('type', 'agent_rapport')->count());

        $this->actingAs($admin)->get('/admin/bureau-du-ceo')->assertOk()
            ->assertSee('Relancer les annonces sans vente')->assertSee('Valider le mail et écrire aux collectivités')
            ->assertSee('Parler au CEO')->assertSee('Lis-moi le résumé');

        Livewire::test(BureauDuCeo::class)
            ->call('decider', $r->id, 2, 'go')
            ->call('decider', $r->id, 3, 'non')
            ->call('validerRse')
            ->call('basculerPause');
        $actions = $r->fresh()->actions;
        $this->assertSame('go', $actions[1]['decision']);
        $this->assertSame('non', $actions[2]['decision']);
        $this->assertNull($actions[0]['decision']);
        $this->assertTrue(Prospection::rseValidee());
        $this->assertTrue(Prospection::enPause());

        // Les décisions servent au résumé suivant.
        $r->fresh()->forceFill(['jour' => now()->subDay()->toDateString()])->save();
        $decisions = collect(\App\Support\Agent\RapportQuotidien::chiffres()['decisions_recentes']);
        $this->assertSame('go', $decisions->firstWhere('action', 'Mettre en avant la Guadeloupe')['decision']);
        $this->assertSame('sans réponse', $decisions->firstWhere('action', 'Relancer les annonces sans vente')['decision']);
    }

    public function test_parler_au_ceo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->fauxClaude(['reponse' => 'Cette semaine, trois ventes. Je te conseille de relancer les vendeurs.']);

        $page = Livewire::test(BureauDuCeo::class)
            ->call('demander', 'Combien de ventes cette semaine ?')
            ->assertReturned('Cette semaine, trois ventes. Je te conseille de relancer les vendeurs.');
        $this->assertCount(1, $page->get('echanges'));

        // Rien entendu : pas d'appel à l'IA.
        $page->call('demander', '   ')->assertReturned('Je n’ai rien entendu. Tu peux répéter ?');

        // Sans IA disponible : une phrase claire, jamais d'erreur.
        $this->app->forgetInstance('agent.client');
        $this->app->offsetUnset('agent.client');
        config()->set('ia_depot.cle', null);
        $this->assertStringContainsString('pas disponible', \App\Support\Agent\Conversation::repondre('Ça va ?'));
    }

    public function test_liens_du_mail_aux_collectivites(): void
    {
        $p = $this->organisme();

        $this->get(route('rse.prospection.clic', $p->jeton))->assertRedirect(route('institutions.reemploi'));
        $this->assertNotNull($p->fresh()->clique_at);

        // Désinscription en un clic depuis la messagerie (POST sans jeton de formulaire).
        $this->post(route('rse.prospection.desinscription', $p->jeton))->assertOk()->assertSee('C’est noté', false);
        $this->assertSame('desinscrit', $p->fresh()->statut);
    }

    public function test_etat_anonyme_pour_les_journaux_publics(): void
    {
        $this->organisme(['nom' => 'CCAS Secret', 'email' => 'secret@ccas.re']);

        $this->artisan('agent:directeur', ['mission' => 'etat'])
            ->expectsOutputToContain('Organismes publics : 1 repérés')
            ->doesntExpectOutputToContain('secret@ccas.re')
            ->doesntExpectOutputToContain('CCAS Secret')
            ->assertSuccessful();
    }
}
