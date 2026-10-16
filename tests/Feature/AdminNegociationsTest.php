<?php

namespace Tests\Feature;

use App\Models\ExchangeProposal;
use App\Models\Listing;
use App\Models\ListingOffer;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Negociations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNegociationsTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $nom): User
    {
        return User::create(['name' => $nom, 'email' => strtolower($nom) . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function annonce(User $vendeur, float $prix = 20): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe fleurie ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => $prix, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
    }

    private function offre(Listing $a, User $acheteur, int $montant, string $statut, array $plus = []): ListingOffer
    {
        return ListingOffer::create($plus + [
            'listing_id' => $a->id, 'buyer_id' => $acheteur->id, 'seller_id' => $a->user_id,
            'amount' => $montant, 'status' => $statut,
        ]);
    }

    public function test_etats_des_offres_et_bilan(): void
    {
        $lea = $this->membre('Lea');
        $tom = $this->membre('Tom');
        $robe = $this->annonce($lea, 20);

        $attente = $this->offre($robe, $tom, 15, 'pending');
        $refusee = $this->offre($robe, $tom, 10, 'declined');
        $payee = $this->offre($robe, $tom, 16, 'accepted');
        $acceptee = $this->offre($robe, $tom, 18, 'accepted');
        $expiree = $this->offre($robe, $tom, 18, 'accepted', ['source' => 'favoris', 'expire_at' => now()->subHour()]);
        $contre = $this->offre($robe, $tom, 19, 'accepted', ['source' => 'contre_offre']);

        Transaction::create([
            'listing_id' => $robe->id, 'listing_offer_id' => $payee->id, 'seller_id' => $lea->id, 'buyer_id' => $tom->id,
            'amount' => 17.6, 'seller_amount' => 16, 'commission' => 0, 'buyer_protection_fee' => 1.6, 'shipping_fee' => 0,
            'currency' => 'EUR', 'payment_method' => 'cb', 'delivery_method' => 'hand_delivery', 'status' => 'paid',
            'shipping_status' => 'pending', 'stripe_payment_intent_id' => 'pi_1',
        ]);

        $this->assertSame('en_attente', Negociations::etatOffre($attente));
        $this->assertSame('refusee', Negociations::etatOffre($refusee));
        $this->assertSame('payee', Negociations::etatOffre($payee));
        $this->assertSame('acceptee', Negociations::etatOffre($acceptee));
        $this->assertSame('expiree', Negociations::etatOffre($expiree));
        $this->assertSame('contre_offre', Negociations::origine($contre));
        $this->assertSame(25, Negociations::remise($attente));

        // Les filtres donnent les mêmes réponses que les badges.
        foreach (['en_attente' => [$attente], 'refusee' => [$refusee], 'payee' => [$payee], 'acceptee' => [$acceptee, $contre], 'expiree' => [$expiree]] as $etat => $attendues) {
            $this->assertEqualsCanonicalizing(
                collect($attendues)->pluck('id')->all(),
                Negociations::filtrerOffres(ListingOffer::query(), $etat)->pluck('id')->all(),
                $etat,
            );
        }

        $b = Negociations::bilanOffres();
        $this->assertSame(6, $b['offres']);
        $this->assertSame(1, $b['en_attente']);
        $this->assertSame(67, $b['taux_acceptation'], 'Offres d’acheteurs décidées : 2 acceptées sur 3.');
        $this->assertSame(25, $b['taux_paiement'], '1 payée sur 4 acceptées.');
        $this->assertEqualsWithDelta(17.6, $b['volume_paye'], 0.001);
    }

    public function test_contre_offre_marquee(): void
    {
        $lea = $this->membre('Lea');
        $tom = $this->membre('Tom');
        $robe = $this->annonce($lea, 20);

        $this->actingAs($lea)->post(route('offers.counter', [$robe, $tom]), ['amount' => 17])->assertRedirect();

        $this->assertSame('contre_offre', ListingOffer::firstOrFail()->source);
    }

    public function test_echanges_et_pages_admin(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $lea = $this->membre('Lea');
        $tom = $this->membre('Tom');
        $robe = $this->annonce($lea, 20);
        $veste = $this->annonce($tom, 25);

        $attente = ExchangeProposal::create(['listing_id' => $robe->id, 'proposer_id' => $tom->id, 'seller_id' => $lea->id, 'offered_listing_id' => $veste->id, 'status' => 'pending']);
        $conclu = ExchangeProposal::create(['listing_id' => $robe->id, 'proposer_id' => $tom->id, 'seller_id' => $lea->id, 'offered_title' => 'Sac en raphia', 'status' => 'accepted']);
        Transaction::create([
            'listing_id' => $robe->id, 'seller_id' => $lea->id, 'buyer_id' => $tom->id,
            'amount' => 0, 'seller_amount' => 0, 'commission' => 0, 'buyer_protection_fee' => 0, 'shipping_fee' => 0,
            'currency' => 'EUR', 'payment_method' => 'echange', 'delivery_method' => 'hand_delivery', 'status' => 'completed',
            'shipping_status' => 'pending',
        ]);

        $this->assertSame('en_attente', Negociations::etatEchange($attente));
        $this->assertSame('conclu', Negociations::etatEchange($conclu));
        $this->assertSame([$conclu->id], Negociations::filtrerEchanges(ExchangeProposal::query(), 'conclu')->pluck('id')->all());
        $this->assertSame(1, Negociations::bilanEchanges()['conclus']);

        $this->offre($robe, $tom, 15, 'pending');

        $this->actingAs($admin);
        $this->get('/admin/negociations')->assertOk()
            ->assertSee('Négociations')->assertSee('Robe fleurie')->assertSee('En attente du vendeur')->assertSee('Offre de l’acheteur', false);
        $this->get('/admin/echanges')->assertOk()
            ->assertSee('Échanges')->assertSee('Sac en raphia')->assertSee('Robe fleurie')->assertSee('Échange conclu');
    }
}
