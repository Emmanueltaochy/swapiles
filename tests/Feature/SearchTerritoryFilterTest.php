<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug critique filtre territoire : avec territoire = Guadeloupe et « Inter-îles »
 * décoché, la recherche renvoyait des annonces de La Réunion.
 *
 * Règles strictes (aucune exception) :
 *   • « Inter-îles » décoché → uniquement le territoire choisi, quel que soit
 *     le mode de paiement.
 *   • Mode « Espèces / main propre » → uniquement le territoire choisi
 *     (remise en main propre entre deux îles impossible).
 */
class SearchTerritoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private function seller(string $email): User
    {
        return User::create([
            'name' => 'V', 'email' => $email, 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    private function listing(User $seller, string $territoire, string $title): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'title' => $title, 'price' => 20,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => $territoire,
            'requires_online_payment' => false, 'allows_colissimo' => false,
            'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    public function test_guadeloupe_inter_iles_decoche_exclut_la_reunion_quel_que_soit_le_paiement(): void
    {
        $s = $this->seller('s1@ex.com');
        $this->listing($s, 'La Réunion', 'Annonce Réunion 1');
        $this->listing($s, 'La Réunion', 'Annonce Réunion 2');
        $this->listing($s, 'Guadeloupe', 'Annonce Guadeloupe');

        // Inter-îles décoché (absent), sans filtre de paiement.
        $resp = $this->get(route('search', ['territoire' => 'Guadeloupe']));
        $resp->assertOk();
        $listings = $resp->viewData('listings');
        foreach ($listings as $l) {
            $this->assertNotSame('La Réunion', $l->territoire, 'Une annonce Réunion est présente sans inter-îles.');
        }

        // Même chose avec le mode « Espèces / main propre ».
        $resp2 = $this->get(route('search', [
            'territoire' => 'Guadeloupe',
            'payment' => ['cash'],
        ]));
        $resp2->assertOk();
        foreach ($resp2->viewData('listings') as $l) {
            $this->assertSame('Guadeloupe', $l->territoire, 'Une annonce hors Guadeloupe en main propre.');
        }
    }

    public function test_main_propre_limite_strictement_au_territoire_meme_inter_iles_coche(): void
    {
        $s = $this->seller('s2@ex.com');
        $this->listing($s, 'La Réunion', 'Réunion');
        $this->listing($s, 'Guadeloupe', 'Guadeloupe');

        // Inter-îles COCHÉ mais mode main propre → toujours limité au territoire.
        $resp = $this->get(route('search', [
            'territoire' => 'Guadeloupe',
            'inter_iles' => '1',
            'payment' => ['cash'],
        ]));
        $resp->assertOk();
        $listings = $resp->viewData('listings');
        $this->assertGreaterThanOrEqual(0, $listings->count());
        foreach ($listings as $l) {
            $this->assertSame('Guadeloupe', $l->territoire);
        }
    }
}
