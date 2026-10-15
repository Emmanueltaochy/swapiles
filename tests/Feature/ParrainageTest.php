<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\Parrainage;
use App\Models\ParrainageEvenement;
use App\Models\PortefeuilleMouvement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripePaymentIntentService;
use App\Support\CodeParrainage;
use App\Support\FicheTransaction;
use App\Support\ParrainageService;
use App\Support\Portefeuille;
use App\Support\TableauDeBord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Programme de parrainage : codes, lien d'invitation (et lien différé),
 * protection offerte, crédit au checkout, récompense unique, anti-fraude,
 * plafond, expiration, admin.
 */
class ParrainageTest extends TestCase
{
    use RefreshDatabase;

    private object $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'referral.enabled' => true, 'referral.testeurs' => [], 'referral.reward_amount' => 5,
            'referral.credit_validity_days' => 90, 'referral.max_rewards_per_month' => 10,
            'pricing.protection_rate' => 0.10, 'pricing.protection_floor' => 0.50, 'pricing.protection_cap' => 15.00,
        ]);

        $this->stripe = new class extends StripePaymentIntentService
        {
            public int $dernierMontant = -1;

            public array $empreintes = [];

            public function create(int $amountCents, array $metadata, array $options = [], string $currency = 'eur'): object
            {
                $this->dernierMontant = $amountCents;

                return (object) ['id' => 'pi_' . uniqid(), 'client_secret' => 'cs_test'];
            }

            public function resolveCustomerId(User $buyer): ?string
            {
                return 'cus_' . $buyer->id;
            }

            public function carteEmpreinte(string $paymentIntentId): ?string
            {
                return $this->empreintes[$paymentIntentId] ?? null;
            }

            public function annuler(string $paymentIntentId): void {}
        };
        $this->app->instance(StripePaymentIntentService::class, $this->stripe);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $nom, string $email, array $plus = []): User
    {
        return User::create($plus + ['name' => $nom, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function vendeur(): User
    {
        return $this->membre('Vendeur', 'vendeur' . uniqid() . '@ex.com', [
            'stripe_account_id' => 'acct_test', 'stripe_charges_enabled' => true, 'stripe_payouts_enabled' => true, 'stripe_details_submitted' => true,
        ]);
    }

    private function annonce(float $prix = 20, ?User $vendeur = null): Listing
    {
        return Listing::create([
            'user_id' => ($vendeur ?? $this->vendeur())->id, 'title' => 'Robe ' . $prix, 'description' => 'Description de test assez longue.',
            'price' => $prix, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    /** Achat en ligne déjà créé (statut au choix), comme après le checkout. */
    private function achat(User $acheteur, string $statut = 'pending', array $plus = []): Transaction
    {
        $annonce = $this->annonce();

        return Transaction::create($plus + [
            'listing_id' => $annonce->id, 'seller_id' => $annonce->user_id, 'buyer_id' => $acheteur->id,
            'amount' => 22, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2, 'shipping_fee' => 0,
            'currency' => 'EUR', 'payment_method' => 'cb', 'delivery_method' => 'hand_delivery', 'status' => $statut,
            'shipping_status' => 'pending', 'stripe_payment_intent_id' => 'pi_' . uniqid(),
        ]);
    }

    /** Filleul déjà rattaché à son parrain. */
    private function couple(string $emailFilleul = 'tom@ex.com'): array
    {
        $parrain = $this->membre('Léa Hoarau', 'lea@ex.com');
        $filleul = $this->membre('Tom Payet', $emailFilleul);
        $this->assertNull(ParrainageService::attacher($filleul, $parrain->code_parrainage, 'lien'));

        return [$parrain, $filleul->fresh()];
    }

    private function terminer(Transaction $t): void
    {
        $t->update(['status' => 'paid']);
        $t->update(['status' => 'completed', 'completed_at' => now()]);
    }

    // --- Codes ---------------------------------------------------------------------

    public function test_code_genere_a_la_creation_lisible_et_unique(): void
    {
        $lea = $this->membre('Léa Hoarau', 'lea@ex.com');
        $lea2 = $this->membre('Léa Grondin', 'lea2@ex.com');
        $marc = $this->membre('Marc', 'marc@ex.com', ['territoire' => 'Martinique']);

        $this->assertSame('LEA974', $lea->code_parrainage);
        $this->assertSame('LEA974B', $lea2->code_parrainage);
        $this->assertSame('MARC972', $marc->code_parrainage);
        $this->assertSame($lea->id, ParrainageService::parrainDuCode(' lea974 ')->id, 'Insensible à la casse et aux espaces.');
    }

    public function test_personnaliser_son_code_une_seule_fois(): void
    {
        $lea = $this->membre('Léa', 'lea@ex.com');
        $this->membre('Léa', 'lea2@ex.com'); // LEA974B

        $this->actingAs($lea)->post('/mon-compte/parrainage/personnaliser', ['nouveau_code' => 'm3rde974'])->assertSessionHasErrors('nouveau_code');
        $this->actingAs($lea)->post('/mon-compte/parrainage/personnaliser', ['nouveau_code' => 'lea974b'])->assertSessionHasErrors('nouveau_code');
        $this->actingAs($lea)->post('/mon-compte/parrainage/personnaliser', ['nouveau_code' => 'ab'])->assertSessionHasErrors('nouveau_code');
        $this->actingAs($lea)->post('/mon-compte/parrainage/personnaliser', ['nouveau_code' => 'Léa-St-Pierre'])->assertSessionHasNoErrors();

        $lea->refresh();
        $this->assertSame('LEASTPIERRE', $lea->code_parrainage);
        $this->assertSame('LEA974', $lea->code_parrainage_ancien);
        // Les liens déjà partagés marchent encore.
        $this->get('/invite/LEA974')->assertOk()->assertSee('LEASTPIERRE');

        $this->actingAs($lea)->post('/mon-compte/parrainage/personnaliser', ['nouveau_code' => 'LEAREUNION'])->assertForbidden();
        $this->assertTrue(CodeParrainage::interdit('SAL0PE'));
        $this->assertFalse(CodeParrainage::interdit('VERONIQUE974'), 'Pas de faux positif sur un prénom.');
    }

    // --- Lien d'invitation et inscription ------------------------------------------------

    public function test_lien_d_invitation_puis_inscription_avec_le_code_pre_rempli(): void
    {
        $lea = $this->membre('Léa Hoarau', 'lea@ex.com');

        $this->get('/invite/lea974')->assertOk()->assertSee('Léa H. t’invite', false)->assertCookie('swp_parrain', 'LEA974');
        $this->assertSame(1, DB::table('invitations_ouvertes')->count());
        $this->get('/inscription')->assertOk()->assertSee('value="LEA974"', false)->assertSee('protection seront', false);

        $this->post('/inscription', [
            'name' => 'Tom Payet', 'email' => 'tom@ex.com', 'password' => 'secret1234', 'password_confirmation' => 'secret1234',
            'territoire' => 'La Réunion', 'code_parrainage' => 'LEA974',
        ])->assertRedirect();

        $tom = User::where('email', 'tom@ex.com')->sole();
        $this->assertSame($lea->id, $tom->parrain_id);
        $this->assertSame('lien', Parrainage::sole()->source);
        $this->assertSame('🎉 Tom P. a rejoint Swap’Îles grâce à toi !', Notification::where('user_id', $lea->id)->where('type', 'referral_joined')->value('title'));
        $this->assertSame('Tes frais de protection sont offerts sur ton 1er achat 🌴', Notification::where('user_id', $tom->id)->where('type', 'referral_welcome')->value('message'));
        $this->assertSame(1, ParrainageEvenement::where('evenement', 'referral_signup')->count());
    }

    public function test_code_inconnu_a_l_inscription(): void
    {
        $this->post('/inscription', [
            'name' => 'Tom', 'email' => 'tom@ex.com', 'password' => 'secret1234', 'password_confirmation' => 'secret1234',
            'territoire' => 'La Réunion', 'code_parrainage' => 'NEXISTEPAS',
        ])->assertSessionHasErrors('code_parrainage');
        $this->assertSame(0, User::count());
    }

    public function test_lien_differe_a_la_premiere_ouverture_de_l_appli(): void
    {
        $this->membre('Léa', 'lea@ex.com');
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)';

        $this->withServerVariables(['REMOTE_ADDR' => '41.213.1.1'])->withHeader('User-Agent', $iphone)->get('/invite/LEA974')->assertOk();

        // L'appli fraîchement installée : autre navigateur, ni cookie ni session.
        $this->flushSession();
        $this->withServerVariables(['REMOTE_ADDR' => '41.213.1.1'])->withHeader('User-Agent', $iphone)
            ->postJson('/appli/installation', ['id' => 'install-0123456789abcdef', 'plateforme' => 'ios'])->assertNoContent();
        $this->get('/inscription')->assertSee('value="LEA974"', false);

        // Une autre connexion n'est pas appariée.
        $this->flushSession();
        $this->withServerVariables(['REMOTE_ADDR' => '102.35.9.9'])->withHeader('User-Agent', $iphone)
            ->postJson('/appli/installation', ['id' => 'install-fedcba9876543210', 'plateforme' => 'ios']);
        $this->get('/inscription')->assertDontSee('value="LEA974"', false);
    }

    public function test_programme_desactive_rien_n_apparait_sauf_pour_les_testeurs(): void
    {
        config(['referral.enabled' => false]);
        $lea = $this->membre('Léa', 'lea@ex.com');

        $this->get('/invite/LEA974')->assertNotFound();
        $this->get('/inscription')->assertDontSee('Code de parrainage');
        $this->actingAs($lea)->get('/mon-compte/parrainage')->assertNotFound();
        $this->get('/conditions-parrainage')->assertNotFound();

        config(['referral.testeurs' => ['LEA@ex.com']]);
        auth()->logout();
        $this->get('/invite/LEA974')->assertOk();
        $this->get('/inscription')->assertSee('Code de parrainage');
        $this->actingAs($lea)->get('/mon-compte/parrainage')->assertOk()->assertSee('Mode test');
    }

    public function test_saisie_manuelle_dans_les_7_jours_et_regles(): void
    {
        $lea = $this->membre('Léa', 'lea@ex.com');
        $tom = $this->membre('Tom', 'tom@ex.com');

        $this->actingAs($tom)->post('/mon-compte/parrainage/code', ['code' => $tom->code_parrainage])->assertSessionHasErrors('code');
        $this->actingAs($tom)->post('/mon-compte/parrainage/code', ['code' => 'LEA974'])->assertSessionHasNoErrors();
        $this->assertSame($lea->id, $tom->fresh()->parrain_id);
        $this->assertSame('saisie', Parrainage::sole()->source);
        $this->actingAs($tom)->post('/mon-compte/parrainage/code', ['code' => 'LEA974'])->assertSessionHasErrors('code');

        Carbon::setTestNow(now()->addDays(8));
        $vieux = $this->membre('Zoé', 'zoe@ex.com', []);
        $vieux->forceFill(['created_at' => now()->subDays(8)])->save();
        $this->actingAs($vieux)->post('/mon-compte/parrainage/code', ['code' => 'LEA974'])->assertSessionHasErrors('code');

        // Même adresse e-mail (alias Gmail) : refusé.
        $jean = $this->membre('Jean', 'jean.dupont@gmail.com');
        $alias = $this->membre('Jeanne', 'jeandupont+swap@googlemail.com');
        $this->assertNotNull(ParrainageService::attacher($alias, $jean->code_parrainage, 'saisie'));
    }

    // --- Checkout ---------------------------------------------------------------------

    public function test_protection_offerte_au_premier_achat_du_filleul(): void
    {
        [, $tom] = $this->couple();
        $annonce = $this->annonce(20);

        $this->actingAs($tom)->get(route('checkout.start', $annonce))->assertSee('offerte (parrainage)', false);
        $this->actingAs($tom)->post(route('checkout.start', $annonce), ['delivery_method' => 'hand_delivery'])
            ->assertOk()->assertSee('offerte (parrainage)', false);

        $this->assertSame(2000, $this->stripe->dernierMontant, '20 € sans les 2 € de protection.');
        $t = Transaction::latest('id')->first();
        $this->assertEquals(0, $t->buyer_protection_fee);
        $this->assertEquals(2.00, $t->protection_offerte);
        $this->assertEquals(20.00, $t->amount);

        // Un 2e achat pendant que le 1er est en cours : protection normale.
        $t->update(['status' => 'paid']);
        $this->actingAs($tom)->post(route('checkout.start', $this->annonce(20)), ['delivery_method' => 'hand_delivery']);
        $this->assertSame(2200, $this->stripe->dernierMontant);
    }

    public function test_credit_deduit_du_total_avec_minimum_stripe(): void
    {
        $lea = $this->membre('Léa', 'lea@ex.com');
        Portefeuille::crediter($lea, 500, 'credit_parrainage', now()->addDays(90));

        $this->actingAs($lea)->get(route('checkout.start', $this->annonce(20)))->assertSee('5,00 € disponibles', false);
        $this->actingAs($lea)->post(route('checkout.start', $this->annonce(20)), ['delivery_method' => 'hand_delivery'])
            ->assertOk()->assertSee('− 5,00 €', false);
        $this->assertSame(1700, $this->stripe->dernierMontant, '20 + 2 de protection − 5 de crédit.');
        $t = Transaction::latest('id')->first();
        $this->assertEquals(5.00, $t->credit_utilise);
        $this->assertEquals(17.00, $t->amount);
        $this->assertEquals(20.00, $t->seller_amount, 'Le vendeur touche toujours son prix.');
        $this->assertSame(0, Portefeuille::solde($lea));

        // Crédit supérieur au total : il reste 0,50 € par carte.
        $paul = $this->membre('Paul', 'paul@ex.com');
        Portefeuille::crediter($paul, 3000, 'credit_parrainage', now()->addDays(90));
        $this->actingAs($paul)->post(route('checkout.start', $this->annonce(10)), ['delivery_method' => 'hand_delivery']);
        $this->assertSame(50, $this->stripe->dernierMontant);
        $this->assertSame(3000 - 1050, Portefeuille::solde($paul));

        // Case décochée : pas de crédit.
        $this->actingAs($paul)->post(route('checkout.start', $this->annonce(10)), ['delivery_method' => 'hand_delivery', 'utiliser_credit' => '0']);
        $this->assertSame(1100, $this->stripe->dernierMontant);
    }

    public function test_credit_rendu_si_la_commande_est_annulee_ou_remboursee(): void
    {
        $lea = $this->membre('Léa', 'lea@ex.com');
        Portefeuille::crediter($lea, 500, 'credit_parrainage', now()->addDays(90));
        $this->actingAs($lea)->post(route('checkout.start', $this->annonce(20)), ['delivery_method' => 'hand_delivery']);
        $t = Transaction::latest('id')->first();
        $this->assertSame(0, Portefeuille::solde($lea));

        $t->update(['status' => 'cancelled']);
        $this->assertSame(500, Portefeuille::solde($lea));
        $t->update(['status' => 'refunded']); // rejoué : pas de double restitution
        $this->assertSame(500, Portefeuille::solde($lea));
        $this->assertEquals(17.00, $t->fresh()->refundAmountEuros(), 'Seule la part carte est remboursée par Stripe.');
    }

    public function test_les_revenus_deduisent_le_credit(): void
    {
        $acheteur = $this->membre('Paul', 'paul@ex.com');
        $t = $this->achat($acheteur, 'paid', ['amount' => 17, 'credit_utilise' => 5]);

        $this->assertEquals(-3.00, TableauDeBord::entre(now()->subDay(), now()->addDay())['revenus'], '2 € de protection − 5 € de crédit.');
        $argent = FicheTransaction::argent($t);
        $this->assertEquals(5.00, $argent['credit_utilise']);
        $this->assertEquals(-3.00, $argent['revenu_swapiles']);
        $this->assertEquals(0.00, $argent['ecart']);
    }

    // --- Récompense ------------------------------------------------------------------------

    public function test_recompense_unique_quand_le_premier_achat_est_termine(): void
    {
        [$lea, $tom] = $this->couple();
        $t = $this->achat($tom);

        $t->update(['status' => 'paid']);
        $this->assertSame('achat_en_cours', Parrainage::sole()->statut);
        $this->assertSame(0, Portefeuille::solde($lea), 'Rien tant que la commande n’est pas terminée.');

        $t->update(['status' => 'completed']);
        $p = Parrainage::sole();
        $this->assertSame('valide', $p->statut);
        $this->assertSame($t->id, $p->transaction_id);
        $this->assertSame(500, Portefeuille::solde($lea));
        $lot = PortefeuilleMouvement::where('user_id', $lea->id)->sole();
        $this->assertTrue($lot->expire_at->isSameDay(now()->addDays(90)));
        $this->assertSame('💚 Tom P. a fait son 1er achat', Notification::where('user_id', $lea->id)->where('type', 'referral_reward')->value('title'));

        // Rejeux (webhook, auto-validation, 2e achat) : jamais deux récompenses.
        ParrainageService::apresFinCommande($t->fresh());
        $t->update(['status' => 'paid']);
        $t->update(['status' => 'completed']);
        $this->terminer($this->achat($tom));
        $this->assertSame(500, Portefeuille::solde($lea));
        $this->assertSame(1, ParrainageEvenement::where('evenement', 'referral_reward_granted')->count());
    }

    public function test_pas_de_recompense_si_rembourse_avant_la_fin(): void
    {
        [$lea, $tom] = $this->couple();
        $t = $this->achat($tom);
        $t->update(['status' => 'paid']);
        $t->update(['status' => 'refunded']);

        $this->assertSame('inscrit', Parrainage::sole()->statut);
        $this->assertSame(0, Portefeuille::solde($lea));
        $this->assertTrue(ParrainageService::protectionOfferte($tom), 'La protection reste offerte pour l’achat suivant.');

        // Remboursement par le webhook Stripe (mise à jour directe en base).
        $t2 = $this->achat($tom, 'pending');
        \App\Support\TransactionPayment::markPaidOnce($t2);
        $this->assertSame('achat_en_cours', Parrainage::sole()->statut);
        Transaction::where('id', $t2->id)->update(['status' => 'refunded']);
        ParrainageService::surStatut($t2->fresh());
        $this->assertSame('inscrit', Parrainage::sole()->statut);
    }

    public function test_vente_declaree_en_main_propre_ne_compte_pas(): void
    {
        [$lea, $tom] = $this->couple();
        $this->terminer($this->achat($tom, 'pending', ['payment_method' => 'especes', 'buyer_protection_fee' => 0]));

        $this->assertSame('inscrit', Parrainage::sole()->statut);
        $this->assertSame(0, Portefeuille::solde($lea));
    }

    public function test_anti_fraude_meme_appareil(): void
    {
        [$lea, $tom] = $this->couple();
        foreach ([$lea, $tom] as $u) {
            DB::table('user_sessions')->insert(['user_id' => $u->id, 'device_fingerprint' => 'abcdef0123456789', 'event_type' => 'login', 'created_at' => now()]);
        }
        $this->terminer($this->achat($tom));

        $p = Parrainage::sole();
        $this->assertSame('refuse', $p->statut);
        $this->assertSame('meme_appareil', $p->motif_refus);
        $this->assertSame(0, Portefeuille::solde($lea));
        $this->assertSame('meme_appareil', ParrainageEvenement::where('evenement', 'referral_reward_rejected')->value('motif'));
    }

    public function test_anti_fraude_meme_carte(): void
    {
        [$lea, $tom] = $this->couple();
        $this->achat($lea, 'completed', ['carte_empreinte' => 'fp_carte_lea']);
        $t = $this->achat($tom);
        $this->stripe->empreintes[$t->stripe_payment_intent_id] = 'fp_carte_lea';

        $this->terminer($t);

        $this->assertSame('fp_carte_lea', $t->fresh()->carte_empreinte);
        $this->assertSame('meme_carte', Parrainage::sole()->motif_refus);
    }

    public function test_anti_fraude_meme_telephone_et_meme_email(): void
    {
        [$lea, $tom] = $this->couple();
        $lea->forceFill(['phone' => '0692 12 34 56'])->save();
        $this->terminer($this->achat($tom, 'pending', ['buyer_phone' => '+262 692 12 34 56']));
        $this->assertSame('meme_telephone', Parrainage::sole()->motif_refus);

        // E-mail : alias Gmail rattaché « de force » (contrôle à la récompense aussi).
        $jean = $this->membre('Jean', 'jean.dupont@gmail.com');
        $alias = $this->membre('Alias', 'j.e.a.n.dupont+promo@gmail.com');
        $alias->forceFill(['parrain_id' => $jean->id])->save();
        Parrainage::create(['parrain_id' => $jean->id, 'filleul_id' => $alias->id, 'code' => $jean->code_parrainage, 'statut' => 'inscrit']);
        $this->terminer($this->achat($alias));
        $this->assertSame('meme_email', Parrainage::where('filleul_id', $alias->id)->value('motif_refus'));
    }

    public function test_plafond_mensuel_par_parrain(): void
    {
        config(['referral.max_rewards_per_month' => 2]);
        $lea = $this->membre('Léa', 'lea@ex.com');
        $filleuls = collect(range(1, 3))->map(function ($i) use ($lea) {
            $f = $this->membre('Ami' . $i, "ami{$i}@ex.com");
            ParrainageService::attacher($f, 'LEA974', 'lien');

            return $f;
        });

        $filleuls->each(fn ($f) => $this->terminer($this->achat($f)));
        $this->assertSame(2, Parrainage::where('statut', 'valide')->count());
        $this->assertSame('plafond_mensuel', Parrainage::where('filleul_id', $filleuls[2]->id)->value('motif_refus'));
        $this->assertSame(1000, Portefeuille::solde($lea));

        // Le mois suivant, le compteur repart.
        Carbon::setTestNow(now()->addMonth()->startOfMonth()->addDay());
        $nouveau = $this->membre('Ami4', 'ami4@ex.com');
        ParrainageService::attacher($nouveau, 'LEA974', 'lien');
        $this->terminer($this->achat($nouveau));
        $this->assertSame('valide', Parrainage::where('filleul_id', $nouveau->id)->value('statut'));
    }

    public function test_paliers_ambassadeur_et_mise_en_avant(): void
    {
        config(['referral.palier_mise_en_avant' => 4]);
        $lea = $this->membre('Léa', 'lea@ex.com');
        foreach (range(1, 4) as $i) {
            $f = $this->membre('Ami' . $i, "ami{$i}@ex.com");
            ParrainageService::attacher($f, 'LEA974', 'lien');
            $this->terminer($this->achat($f));
            if ($i === 2) {
                $this->assertNull($lea->fresh()->ambassadeur_at);
            }
        }

        $lea->refresh();
        $this->assertNotNull($lea->ambassadeur_at);
        $this->assertNotNull($lea->a_mettre_en_avant_at);
        $this->get('/profil/' . $lea->id)->assertSee('💚 Ambassadeur');
    }

    // --- Portefeuille : expiration, rappel, purge ---------------------------------------

    public function test_expiration_rappel_j7_et_purge_des_invitations(): void
    {
        $lea = $this->membre('Léa', 'lea@ex.com');
        Portefeuille::crediter($lea, 500, 'credit_parrainage', now()->addDays(6));
        Portefeuille::crediter($lea, 300, 'credit_parrainage', now()->addDays(60));
        DB::table('invitations_ouvertes')->insert([
            ['code' => 'LEA974', 'parrain_id' => $lea->id, 'ip_hash' => 'a', 'plateforme' => 'ios', 'created_at' => now()->subDays(8)],
            ['code' => 'LEA974', 'parrain_id' => $lea->id, 'ip_hash' => 'b', 'plateforme' => 'ios', 'created_at' => now()->subDays(2)],
        ]);

        $this->artisan('parrainage:entretien')->assertSuccessful();
        $this->artisan('parrainage:entretien')->assertSuccessful();
        $this->assertSame(1, Notification::where('user_id', $lea->id)->where('type', 'wallet_expiring')->count(), 'Un seul rappel.');
        $this->assertSame(1, DB::table('invitations_ouvertes')->count(), 'Les ouvertures de plus de 7 jours sont effacées.');
        $this->assertSame(800, Portefeuille::solde($lea));

        Carbon::setTestNow(now()->addDays(7));
        $this->artisan('parrainage:entretien')->assertSuccessful();
        $this->assertSame(300, Portefeuille::solde($lea));
        $this->assertSame(-500, (int) PortefeuilleMouvement::where('type', 'expiration')->sum('montant_centimes'));
    }

    public function test_on_depense_d_abord_le_credit_qui_expire_le_plus_tot(): void
    {
        $lea = $this->membre('Léa', 'lea@ex.com');
        $tard = Portefeuille::crediter($lea, 500, 'credit_parrainage', now()->addDays(80));
        $tot = Portefeuille::crediter($lea, 300, 'credit_parrainage', now()->addDays(10));

        $this->assertSame(400, Portefeuille::utiliser($lea, 400, $this->achat($lea)));
        $this->assertSame(0, $tot->fresh()->restant_centimes);
        $this->assertSame(400, $tard->fresh()->restant_centimes);
    }

    // --- Pages et admin -----------------------------------------------------------------

    public function test_pages_du_membre(): void
    {
        [$lea, $tom] = $this->couple();
        $this->terminer($this->achat($tom));

        $this->actingAs($lea)->get('/mon-compte/parrainage')->assertOk()
            ->assertSee('LEA974')->assertSee('wa.me', false)->assertSee('Tom P.')->assertSee('Validé ✅', false)->assertSee('1 / 3 amis validés');
        $this->actingAs($lea)->get('/mon-compte/portefeuille')->assertOk()->assertSee('5,00 €', false)->assertSee('Crédit parrainage');
        $this->get('/conditions-parrainage')->assertOk()->assertSee('pas convertible en argent', false)->assertSee('10 par parrain', false);
        $this->get('/confidentialite')->assertSee('effacées automatiquement après 7 jours', false);
        $this->actingAs($lea)->postJson('/mon-compte/parrainage/partage', ['canal' => 'whatsapp'])->assertNoContent();
        $this->assertSame('whatsapp', ParrainageEvenement::where('evenement', 'referral_link_shared')->value('canal'));
    }

    public function test_mon_compte_met_le_parrainage_en_avant(): void
    {
        config(['referral.reward_amount' => 3]);
        $lea = $this->membre('Léa', 'lea@ex.com');

        $this->actingAs($lea)->get('/mon-compte')->assertOk()
            ->assertSee('Gagne 3 € pour chaque ami invité', false)
            ->assertSee('Inviter mes amis')
            ->assertSee('💚 Inviter des amis · 3 € offerts', false);
        $this->actingAs($lea)->get('/mon-compte/parrainage')->assertSee('Gagne 3 € par ami invité', false);
        $this->assertSame('2,50 €', ParrainageService::montantCourt(2.5));

        config(['referral.enabled' => false]);
        $this->actingAs($this->membre('Tom', 'tom@ex.com'))->get('/mon-compte')->assertDontSee('Inviter mes amis');
    }

    public function test_admin_annule_une_recompense_et_exporte(): void
    {
        [$lea, $tom] = $this->couple();
        $this->terminer($this->achat($tom));
        $admin = $this->membre('Admin', 'cabinet@taochyconsulting.fr');
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Resources\Parrainages\Pages\ListParrainages::class)
            ->assertSee('Léa Hoarau')
            ->callTableAction('annuler', Parrainage::sole(), ['raison' => 'Faux compte'])
            ->assertHasNoTableActionErrors()
            ->callAction('csv')
            ->assertFileDownloaded('parrainages-' . now()->format('Y-m-d') . '.csv');

        \Livewire\Livewire::test(\App\Filament\Widgets\Parrainage\TopParrains::class)->assertSee('Léa Hoarau');
        \Livewire\Livewire::test(\App\Filament\Widgets\Parrainage\ParrainageStats::class)->assertSee('Filleuls inscrits');

        $this->assertSame('annule', Parrainage::sole()->statut);
        $this->assertSame(0, Portefeuille::solde($lea));
        $this->get('/admin/parrainage-evenements')->assertOk()->assertSee('Récompense annulée');
    }
}
