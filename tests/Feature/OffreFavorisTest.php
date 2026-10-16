<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\Listing;
use App\Models\ListingOffer;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripePaymentIntentService;
use App\Support\OffreFavoris;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OffreFavorisTest extends TestCase
{
    use RefreshDatabase;

    private object $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        config([
            'features.defer_kyc' => true, 'referral.enabled' => false,
            'pricing.protection_rate' => 0.10, 'pricing.protection_floor' => 0.50, 'pricing.protection_cap' => 15.00,
        ]);
        $this->stripe = new class extends StripePaymentIntentService
        {
            public int $dernierMontant = -1;

            public function create(int $amountCents, array $metadata, array $options = [], string $currency = 'eur'): object
            {
                $this->dernierMontant = $amountCents;

                return (object) ['id' => 'pi_' . uniqid(), 'client_secret' => 'cs_test'];
            }

            public function resolveCustomerId(User $buyer): ?string
            {
                return null;
            }

            public function annuler(string $paymentIntentId): void {}
        };
        $this->app->instance(StripePaymentIntentService::class, $this->stripe);
    }

    private function membre(string $nom): User
    {
        return User::create(['name' => $nom, 'email' => strtolower($nom) . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function annonce(User $vendeur, float $prix = 20): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe fleurie', 'description' => 'Description de test assez longue.',
            'price' => $prix, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => true, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    public function test_prix_reduit_en_euros_entiers_toujours_sous_le_prix(): void
    {
        $lea = $this->membre('Lea');
        $this->assertSame(18, OffreFavoris::prix($this->annonce($lea, 20), 10));
        $this->assertSame(13, OffreFavoris::prix($this->annonce($lea, 15), 10), '13,50 € → 13 € (en faveur de l’acheteur).');
        $this->assertSame(1, OffreFavoris::prix($this->annonce($lea, 2), 5), 'Toujours au moins 1 € de moins.');
        $this->assertSame(18, OffreFavoris::prix($this->annonce($lea, 19.99), 5));
    }

    public function test_offre_envoyee_aux_fans_puis_achat_au_prix_reduit(): void
    {
        $lea = $this->membre('Lea');
        $robe = $this->annonce($lea, 20);
        [$tom, $zoe, $bloque] = [$this->membre('Tom'), $this->membre('Zoe'), $this->membre('Bloque')];
        foreach ([$tom, $zoe, $bloque, $lea] as $fan) {
            Favorite::create(['user_id' => $fan->id, 'listing_id' => $robe->id]);
        }
        $lea->blockedUsers()->attach($bloque->id);

        // Le vendeur voit le nombre de fans (hors lui-même et bloqués) et envoie −10 %.
        $this->actingAs($lea)->get(route('listings.show', $robe))->assertOk()
            ->assertSee('2 personnes ont aimé cet article')->assertSee('18 €');
        $this->post(route('offers.favoris', $robe), ['reduction' => 10])->assertSessionHas('status', '🎁 Offre envoyée à 2 personnes : 18 € pendant 48 h.');

        $this->assertSame(2, ListingOffer::where('source', 'favoris')->where('status', 'accepted')->where('amount', 18)->count());
        $this->assertSame(2, Notification::where('type', 'favorite_offer')->count());
        $this->assertSame(0, Notification::where('type', 'favorite_offer')->where('user_id', $bloque->id)->count());

        // Pas de deuxième offre avant 3 jours ; réduction hors liste refusée.
        $this->post(route('offers.favoris', $robe), ['reduction' => 20])->assertSessionHas('status');
        $this->assertSame(2, ListingOffer::count());
        $this->post(route('offers.favoris', $robe), ['reduction' => 50])->assertSessionHasErrors('reduction');

        // Tom voit l'offre et paie 18 € + protection 1,80 € = 19,80 €.
        $offre = OffreFavoris::offreActive($robe, $tom);
        $this->actingAs($tom)->get(route('listings.show', $robe))->assertOk()
            ->assertSee('Offre spéciale pour vous')->assertSee('Acheter à 18 €');
        $this->post(route('checkout.start', ['listing' => $robe, 'offer' => $offre->id]), ['delivery_method' => 'hand_delivery'])->assertOk();
        $this->assertSame(1980, $this->stripe->dernierMontant);
        $this->assertEquals(18.00, (float) Transaction::firstOrFail()->seller_amount);
    }

    public function test_l_offre_expire_apres_48_h(): void
    {
        $lea = $this->membre('Lea');
        $robe = $this->annonce($lea, 20);
        $tom = $this->membre('Tom');
        Favorite::create(['user_id' => $tom->id, 'listing_id' => $robe->id]);
        OffreFavoris::envoyer($robe, 20);
        $offre = ListingOffer::firstOrFail();

        $this->travel(49)->hours();

        $this->assertNull(OffreFavoris::offreActive($robe, $tom));
        $this->actingAs($tom)->get(route('listings.show', $robe))->assertDontSee('Offre spéciale pour vous');
        $this->post(route('checkout.start', ['listing' => $robe, 'offer' => $offre->id]), ['delivery_method' => 'hand_delivery'])
            ->assertRedirect(route('listings.show', $robe));
        $this->assertSame(0, Transaction::count(), 'Offre expirée : pas d’achat au prix réduit.');
    }

    public function test_pas_d_offre_sans_paiement_en_ligne(): void
    {
        $lea = $this->membre('Lea');
        $robe = $this->annonce($lea, 20);
        $robe->update(['requires_online_payment' => false]);

        $this->assertNotNull(OffreFavoris::empechement($robe));
        $this->actingAs($lea)->post(route('offers.favoris', $robe), ['reduction' => 10])->assertSessionHas('status');
        $this->assertSame(0, ListingOffer::count());

        // Seul le vendeur peut envoyer l'offre.
        $this->actingAs($this->membre('Tom'))->post(route('offers.favoris', $robe), ['reduction' => 10])->assertForbidden();
    }

    public function test_bouton_image_de_partage_story_sur_l_annonce(): void
    {
        $robe = $this->annonce($this->membre('Lea'), 20);

        $this->withCookie('territoire', 'reunion')->get(route('listings.show', $robe))->assertOk()
            ->assertSee('data-story-partage', false)
            ->assertSee('js/story-partage.js', false)
            ->assertSee('Partager en story');
    }
}
