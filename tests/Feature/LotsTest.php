<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Lot;
use App\Models\Notification;
use App\Models\PanierArticle;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripePaymentIntentService;
use App\Support\ColissimoPricing;
use App\Support\Lots;
use App\Support\OrderPricing;
use App\Support\Portefeuille;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LotsTest extends TestCase
{
    use RefreshDatabase;

    private object $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        config([
            'features.defer_kyc' => true,
            'pricing.protection_rate' => 0.10, 'pricing.protection_floor' => 0.50, 'pricing.protection_cap' => 15.00,
            'referral.enabled' => false,
        ]);

        $this->stripe = new class extends StripePaymentIntentService
        {
            public int $dernierMontant = -1;

            public array $paye = [];

            public function create(int $amountCents, array $metadata, array $options = [], string $currency = 'eur'): object
            {
                $this->dernierMontant = $amountCents;

                return (object) ['id' => 'pi_' . uniqid(), 'client_secret' => 'cs_test'];
            }

            public function resolveCustomerId(User $buyer): ?string
            {
                return 'cus_' . $buyer->id;
            }

            public function annuler(string $paymentIntentId): void {}

            public function carteEmpreinte(string $paymentIntentId): ?string
            {
                return null;
            }

            public function paiementReussi(string $paymentIntentId, int $montantCents): bool
            {
                return isset($this->paye[$paymentIntentId]) && $this->paye[$paymentIntentId] >= $montantCents;
            }
        };
        $this->app->instance(StripePaymentIntentService::class, $this->stripe);
    }

    private function membre(string $nom, array $plus = []): User
    {
        $membre = User::create(['name' => $nom, 'email' => strtolower($nom) . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $membre->forceFill($plus)->save();

        return $membre;
    }

    private function annonce(User $vendeur, float $prix, array $plus = []): Listing
    {
        return Listing::create($plus + [
            'user_id' => $vendeur->id, 'title' => 'Robe ' . $prix . ' ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => $prix, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'allows_colissimo' => true, 'pickup_enabled' => true,
            'weight_kg' => 0.5,
        ]);
    }

    /** Léa (−10 % dès 3 articles) vend 3 robes à 20, 15 et 25 € ; Tom les met dans son lot. */
    private function lotDeTom(): array
    {
        $lea = $this->membre('Lea', ['lot_remise_pct' => 10, 'lot_remise_min' => 3, 'address_line1' => '1 rue des Lilas', 'postal_code' => '97410', 'city' => 'Saint-Pierre']);
        $tom = $this->membre('Tom');
        $robes = collect([20, 15, 25])->map(fn ($p) => $this->annonce($lea, $p));
        foreach ($robes as $robe) {
            $this->assertNull(Lots::ajouter($tom, $robe));
        }

        return [$lea, $tom, $robes];
    }

    public function test_calculs_au_centime(): void
    {
        // Remise : la somme des prix remisés est exactement le total remisé.
        $this->assertSame([1800, 1350, 2250], Lots::prixRemises([2000, 1500, 2500], 10));
        $remises = Lots::prixRemises([999, 999, 999], 15);
        $this->assertSame((int) round(2997 * 0.85), array_sum($remises));
        $this->assertSame([2000, 1500], Lots::prixRemises([2000, 1500], 0));

        // Répartition : la somme des parts est exactement le montant.
        $parts = Lots::repartir(540, [1800, 1350, 2250]);
        $this->assertSame(540, array_sum($parts));
        $this->assertSame([180, 135, 225], $parts);
        $this->assertSame(100, array_sum(Lots::repartir(100, [1, 1, 1])));
        $this->assertSame([0, 0], Lots::repartir(0, [5, 5]));

        // Remise vendeur : seulement à partir du minimum choisi.
        $lea = $this->membre('Lea', ['lot_remise_pct' => 10, 'lot_remise_min' => 3]);
        $this->assertSame(0, Lots::remisePct($lea, 2));
        $this->assertSame(10, Lots::remisePct($lea, 3));
        $this->assertSame(0, Lots::remisePct($this->membre('Max'), 5), 'Pas de remise si le vendeur n’en propose pas.');
    }

    public function test_regles_pour_ajouter_au_lot(): void
    {
        $lea = $this->membre('Lea');
        $tom = $this->membre('Tom');

        $this->assertNotNull(Lots::ajouter($lea, $this->annonce($lea, 10)), 'Pas son propre article.');
        $this->assertNotNull(Lots::ajouter($tom, $this->annonce($lea, 10, ['requires_online_payment' => false])), 'Pas d’article payable en ligne.');

        $this->assertNull(Lots::ajouter($tom, $this->annonce($lea, 10)));
        $this->assertNotNull(Lots::ajouter($tom, $this->annonce($lea, 10, ['territoire' => 'Martinique'])), 'Une seule île par lot.');

        for ($i = 0; $i < 9; $i++) {
            $this->assertNull(Lots::ajouter($tom, $this->annonce($lea, 10)));
        }
        $this->assertSame(Lots::MAX_ARTICLES, Lots::articles($tom, $lea)->count());
        $this->assertNotNull(Lots::ajouter($tom, $this->annonce($lea, 10)), '10 articles au maximum.');

        // Via la page : bouton « Ajouter au lot » puis la page du lot.
        $max = $this->membre('Max');
        $robe = $this->annonce($lea, 12);
        $this->actingAs($max)->get(route('listings.show', $robe))->assertSee('Ajouter au lot');
        $this->post(route('lots.ajouter', $robe))->assertSessionHas('status');
        $this->get(route('listings.show', $robe))->assertSee('Voir mon lot (1)');
        $this->get(route('lots.show', $lea))->assertOk()->assertSee('au moins 2 articles');
    }

    public function test_paiement_unique_remise_et_protection_calculee_une_fois(): void
    {
        [$lea, $tom, $robes] = $this->lotDeTom();

        $this->actingAs($tom)->get(route('lots.show', $lea))->assertOk()
            ->assertSee('Remise lot −10 %', false)->assertSee('6,00 €', false);

        $this->post(route('lots.payer', $lea), ['delivery_method' => 'hand_delivery'])->assertOk()
            ->assertSee('Lot de 3 articles')->assertSee('59,40 €', false);

        // 60 € − 10 % = 54 € ; protection 10 % de 54 € = 5,40 € (et non 6 € par article) ; total 59,40 €.
        $this->assertSame(5940, $this->stripe->dernierMontant);
        $lot = Lot::firstOrFail();
        $this->assertSame(3, $lot->nb_articles);
        $this->assertEquals(6.00, (float) $lot->remise_montant);

        $ventes = Transaction::where('lot_id', $lot->id)->orderBy('id')->get();
        $this->assertCount(3, $ventes);
        $this->assertEquals([18.00, 13.50, 22.50], $ventes->pluck('seller_amount')->map(fn ($v) => (float) $v)->all());
        $this->assertEqualsWithDelta(5.40, $ventes->sum(fn ($v) => (float) $v->buyer_protection_fee), 0.001);
        $this->assertEqualsWithDelta(59.40, $ventes->sum(fn ($v) => (float) $v->amount), 0.001);
        $this->assertSame(1, $ventes->pluck('stripe_payment_intent_id')->unique()->count(), 'Un seul paiement.');

        // Stripe confirme : les 3 ventes passent payées, une seule notification au vendeur.
        $this->stripe->paye[$ventes->first()->stripe_payment_intent_id] = 5940;
        $this->get(route('checkout.success', $ventes->first()))->assertRedirect(route('account.transactions.show', $ventes->first()));

        $this->assertSame(['paid'], Transaction::where('lot_id', $lot->id)->pluck('status')->unique()->values()->all());
        $this->assertSame(['sold'], Listing::whereIn('id', $robes->pluck('id'))->pluck('status')->unique()->values()->all());
        $this->assertSame(1, Notification::where('user_id', $lea->id)->where('type', 'transaction_paid_seller')->count());
        $this->assertSame(0, PanierArticle::count(), 'Le lot payé est vidé.');

        // « Mes transactions » : le lot est une seule commande.
        $this->get(route('account.transactions.index'))->assertOk()->assertSee('Lot de 3 articles')->assertSee('59,40 €', false);
    }

    public function test_un_paiement_partiel_ne_valide_pas_le_lot(): void
    {
        [$lea, $tom] = $this->lotDeTom();
        $this->actingAs($tom)->post(route('lots.payer', $lea), ['delivery_method' => 'hand_delivery']);
        $principale = Transaction::orderBy('id')->first();

        // Stripe n'a encaissé que le prix d'un article.
        $this->stripe->paye[$principale->stripe_payment_intent_id] = (int) round((float) $principale->amount * 100);
        $this->get(route('checkout.success', $principale));

        $this->assertSame(['pending'], Transaction::pluck('status')->unique()->values()->all());
    }

    public function test_colissimo_un_seul_colis_au_poids_total(): void
    {
        [$lea, $tom] = $this->lotDeTom();

        $this->actingAs($tom)->post(route('lots.payer', $lea), [
            'delivery_method' => 'colissimo', 'buyer_full_name' => 'Tom Payet', 'buyer_phone' => '0692000000',
            'shipping_address_line1' => '2 rue du Port', 'shipping_postal_code' => '97300', 'shipping_city' => 'Cayenne',
            'shipping_territory' => 'guyane',
        ])->assertOk();

        $port = (int) round(ColissimoPricing::calculate('La Réunion', 'guyane', 1.5)['shipping_fee'] * 100);
        $this->assertSame(5940 + $port, $this->stripe->dernierMontant, 'Un seul envoi de 1,5 kg.');

        $ventes = Transaction::orderBy('id')->get();
        $this->assertEqualsWithDelta($port / 100, (float) $ventes[0]->shipping_fee, 0.001);
        $this->assertSame([0.0, 0.0], [(float) $ventes[1]->shipping_fee, (float) $ventes[2]->shipping_fee], 'Le port est sur la vente principale seulement.');
    }

    public function test_le_credit_est_reparti_sans_depasser_le_total(): void
    {
        [$lea, $tom] = $this->lotDeTom();
        Portefeuille::crediter($tom, 300, 'credit_parrainage', now()->addDays(90));

        $this->actingAs($tom)->post(route('lots.payer', $lea), ['delivery_method' => 'hand_delivery'])->assertOk();

        $ventes = Transaction::orderBy('id')->get();
        $this->assertSame(5640, $this->stripe->dernierMontant, '59,40 € − 3 € de crédit.');
        $this->assertEqualsWithDelta(3.00, $ventes->sum(fn ($v) => (float) $v->credit_utilise), 0.001);
        $this->assertEqualsWithDelta(56.40, $ventes->sum(fn ($v) => (float) $v->amount), 0.001);
        $this->assertTrue($ventes->every(fn ($v) => (float) $v->amount > 0));
        $this->assertSame(0, Portefeuille::solde($tom->fresh()), 'Crédit réservé.');
    }

    public function test_articles_reserves_pendant_le_paiement(): void
    {
        [$lea, $tom, $robes] = $this->lotDeTom();
        $this->actingAs($tom)->post(route('lots.payer', $lea), ['delivery_method' => 'hand_delivery']);

        $max = $this->membre('Max');
        $this->actingAs($max)->post(route('checkout.start', $robes[0]), ['delivery_method' => 'hand_delivery'])
            ->assertRedirect(route('listings.show', $robes[0]))->assertSessionHas('status');
        $this->assertSame(3, Transaction::count(), 'Pas de 2e achat pendant que Tom paie.');
        $this->assertNotNull(Lots::ajouter($max, $robes[1]));

        // 15 minutes plus tard, la réservation tombe.
        Transaction::query()->update(['created_at' => now()->subMinutes(16)]);
        $this->assertNull(Lots::ajouter($max, $robes[1]));
    }

    public function test_suivi_groupe_reception_et_un_seul_avis(): void
    {
        [$lea, $tom] = $this->lotDeTom();
        $this->actingAs($tom)->post(route('lots.payer', $lea), ['delivery_method' => 'hand_delivery']);
        $ventes = Transaction::orderBy('id')->get();
        $this->stripe->paye[$ventes[0]->stripe_payment_intent_id] = 5940;
        $this->get(route('checkout.success', $ventes[0]));

        // Le vendeur marque expédié depuis n'importe quel article : tout le lot l'est.
        $this->actingAs($lea)->patch(route('transactions.shipped', $ventes[2]));
        $this->assertSame(['shipped'], Transaction::pluck('shipping_status')->unique()->values()->all());

        // L'acheteur confirme la réception une fois : tout le lot est terminé.
        $this->actingAs($tom)->patch(route('transactions.received', $ventes[0]));
        $this->assertSame(['completed'], Transaction::pluck('status')->unique()->values()->all());

        // Un seul avis pour la commande.
        $this->post(route('account.reviews.store', $ventes[0]), ['rating' => 5, 'comment' => 'Parfait']);
        $this->post(route('account.reviews.store', $ventes[1]), ['rating' => 1]);
        $this->assertSame(1, Review::where('reviewer_id', $tom->id)->count());

        // La fiche d'un article du lot renvoie à la commande entière.
        $this->get(route('account.transactions.show', $ventes[1]))->assertRedirect(route('account.transactions.show', $ventes[0]));
        $this->get(route('account.transactions.show', $ventes[0]))->assertOk()->assertSee('Lot de 3 articles')->assertSee('6,00 € économisés', false);
    }

    public function test_remboursement_partiel_d_un_lot_ne_touche_pas_les_autres_articles(): void
    {
        [$lea, $tom] = $this->lotDeTom();
        $this->actingAs($tom)->post(route('lots.payer', $lea), ['delivery_method' => 'hand_delivery']);
        $ventes = Transaction::orderBy('id')->get();
        $pi = $ventes[0]->stripe_payment_intent_id;
        Transaction::query()->update(['status' => 'paid']);
        $ventes[1]->update(['status' => 'refunded']); // l'admin rembourse un article

        // Webhook signé « remboursement partiel » : les autres articles restent payés.
        putenv('STRIPE_WEBHOOK_SECRET=whsec_test');
        $payload = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => $pi, 'refunded' => false]]]);
        $t = time();
        $signature = 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, 'whsec_test');
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload)->assertOk();
        putenv('STRIPE_WEBHOOK_SECRET');

        $this->assertSame(['paid', 'refunded', 'paid'], Transaction::orderBy('id')->pluck('status')->all());
    }

    public function test_protection_identique_a_un_achat_simple_pour_le_meme_total(): void
    {
        // Garde-fou : la protection du lot suit exactement la règle d'un achat simple.
        $this->assertSame(OrderPricing::protectionFor(5400), OrderPricing::make(5400)->protectionCents());
        $this->assertSame(1500, OrderPricing::protectionFor(300000), 'Plafond à 15 € même pour un gros lot.');
    }
}
