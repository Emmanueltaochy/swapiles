<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripePaymentIntentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Une vente ne passe « payée » que si Stripe confirme le paiement, pour le
 * bon montant. Ouvrir la page de retour ou envoyer un faux message au
 * webhook ne suffit plus.
 */
class ConfirmationPaiementStripeTest extends TestCase
{
    use RefreshDatabase;

    /** Faux Stripe : PaymentIntent => montant réellement encaissé (null = pas payé). */
    private array $encaisse = [];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $test = $this;
        $this->app->instance(StripePaymentIntentService::class, new class($test) extends StripePaymentIntentService {
            public function __construct(private $test) {}

            public function paiementReussi(string $paymentIntentId, int $montantCents): bool
            {
                $recu = $this->test->encaisse($paymentIntentId);

                return $recu !== null && $recu >= $montantCents;
            }
        });
    }

    public function encaisse(string $pi): ?int
    {
        return $this->encaisse[$pi] ?? null;
    }

    private function achat(string $pi): array
    {
        $vendeur = User::create(['name' => 'Lea', 'email' => 'lea' . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $acheteur = User::create(['name' => 'Tom', 'email' => 'tom' . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $annonce = Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe', 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
        $transaction = Transaction::create([
            'listing_id' => $annonce->id, 'seller_id' => $vendeur->id, 'buyer_id' => $acheteur->id,
            'amount' => 22, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 2, 'shipping_fee' => 0,
            'currency' => 'EUR', 'payment_method' => 'cb', 'delivery_method' => 'hand_delivery',
            'status' => 'pending', 'shipping_status' => 'pending', 'stripe_payment_intent_id' => $pi,
        ]);

        return [$acheteur, $transaction, $annonce];
    }

    public function test_ouvrir_la_page_de_retour_sans_payer_ne_valide_rien(): void
    {
        [$acheteur, $transaction, $annonce] = $this->achat('pi_impaye');

        $this->actingAs($acheteur)->get(route('checkout.success', $transaction))
            ->assertRedirect()
            ->assertSessionHas('status', 'Paiement en cours de confirmation par la banque. Vous serez prévenu dès qu’il est validé.');

        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame('published', $annonce->fresh()->status);
    }

    public function test_un_montant_insuffisant_ne_valide_rien(): void
    {
        [$acheteur, $transaction] = $this->achat('pi_partiel');
        $this->encaisse['pi_partiel'] = 100; // 1 € au lieu de 22 €

        $this->actingAs($acheteur)->get(route('checkout.success', $transaction));
        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_paiement_reussi_chez_stripe_valide_la_vente(): void
    {
        [$acheteur, $transaction, $annonce] = $this->achat('pi_ok');
        $this->encaisse['pi_ok'] = 2200;

        $this->actingAs($acheteur)->get(route('checkout.success', $transaction))
            ->assertSessionHas('status', 'Paiement confirmé. Votre achat est bien enregistré.');

        $this->assertSame('paid', $transaction->fresh()->status);
        $this->assertSame('sold', $annonce->fresh()->status);
    }

    public function test_un_faux_message_webhook_non_signe_ne_valide_ni_n_annule_rien(): void
    {
        config(['app.env' => 'testing']);
        putenv('STRIPE_WEBHOOK_SECRET');
        [, $transaction] = $this->achat('pi_faux');

        $faux = fn (string $type) => $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => $type, 'data' => ['object' => ['id' => 'pi_faux']]]));

        $faux('payment_intent.succeeded')->assertOk();
        $this->assertSame('pending', $transaction->fresh()->status, 'Stripe dit « pas payé » : rien ne change.');

        $faux('charge.refunded')->assertOk();
        $faux('payment_intent.payment_failed')->assertOk();
        $this->assertSame('pending', $transaction->fresh()->status, 'Messages non signés ignorés.');

        // Le vrai paiement arrive : le même message, revérifié, valide la vente.
        $this->encaisse['pi_faux'] = 2200;
        $faux('payment_intent.succeeded')->assertOk();
        $this->assertSame('paid', $transaction->fresh()->status);
    }

    public function test_le_menage_n_annule_pas_une_vente_payee_en_retard(): void
    {
        [, $payee] = $this->achat('pi_tard');
        [, $abandonnee] = $this->achat('pi_abandon');
        Transaction::query()->update(['created_at' => now()->subHours(3)]);
        $this->encaisse['pi_tard'] = 2200;

        $this->artisan('swapiles:cleanup-pending')->assertSuccessful();

        $this->assertSame('paid', $payee->fresh()->status);
        $this->assertSame('cancelled', $abandonnee->fresh()->status);
    }
}
