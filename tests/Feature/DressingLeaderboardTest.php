<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DressingLeaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Classement des dressings (orienté engagement).
 */
class DressingLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('leaderboard.points', [
            'view' => 1, 'favorite' => 20, 'message' => 10, 'sale' => 25, 'review' => 8,
        ]);
    }

    private function seller(string $email): User
    {
        return User::create([
            'name' => 'V '.$email, 'email' => $email,
            'password' => bcrypt('x'), 'territoire' => 'La Réunion',
        ]);
    }

    private function listing(User $seller, int $views = 0): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'title' => 'A', 'price' => 20,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'views_count' => $views,
        ]);
    }

    public function test_l_engagement_prime_sur_les_ventes(): void
    {
        // A : gros engagement (favoris + vues), aucune vente.
        $a = $this->seller('a@ex.com');
        $la = $this->listing($a, 300);           // 300 vues
        $fan1 = $this->seller('f1@ex.com');
        $fan2 = $this->seller('f2@ex.com');
        DB::table('favorites')->insert([
            ['user_id' => $fan1->id, 'listing_id' => $la->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $fan2->id, 'listing_id' => $la->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
        // score A = 300*1 + 2*20 = 340

        // B : une vente, peu d'engagement.
        $b = $this->seller('b@ex.com');
        $lb = $this->listing($b, 10);            // 10 vues
        $buyer = $this->seller('buy@ex.com');
        Transaction::create([
            'listing_id' => $lb->id, 'seller_id' => $b->id, 'buyer_id' => $buyer->id,
            'amount' => 20, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 0,
            'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
            'delivery_method' => 'hand_delivery', 'status' => 'completed', 'shipping_status' => 'received',
        ]);
        // score B = 10*1 + 25 = 35

        $ranked = DressingLeaderboard::ranked();

        $this->assertSame($a->id, $ranked->first()->user_id, 'Le dressing le plus engageant passe devant celui qui a vendu.');
        $this->assertSame(1, DressingLeaderboard::rankOf($a->id));
        $this->assertSame(2, DressingLeaderboard::rankOf($b->id));
    }

    public function test_la_qualite_prime_sur_la_portee_avec_les_poids_par_defaut(): void
    {
        // Poids par défaut « qualité » : favori 25, vente 50, avis 15, vue 0,3.
        config()->set('leaderboard.points', [
            'view' => 0.3, 'favorite' => 25, 'message' => 8, 'sale' => 50, 'review' => 15,
        ]);

        // « Marie » : moins de vues mais complète (favoris + ventes).
        $marie = $this->seller('marie@ex.com');
        $lm = $this->listing($marie, 671);
        $buyer = $this->seller('buyer@ex.com');
        for ($i = 0; $i < 16; $i++) {
            $f = $this->seller("fanM{$i}@ex.com");
            DB::table('favorites')->insert(['user_id' => $f->id, 'listing_id' => $lm->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        for ($i = 0; $i < 4; $i++) {
            $l = $this->listing($marie, 0);
            Transaction::create([
                'listing_id' => $l->id, 'seller_id' => $marie->id, 'buyer_id' => $buyer->id,
                'amount' => 20, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => 0,
                'shipping_fee' => 0, 'currency' => 'EUR', 'payment_method' => 'cb',
                'delivery_method' => 'hand_delivery', 'status' => 'completed', 'shipping_status' => 'received',
            ]);
        }

        // « Vide Dressing » : énormément de vues, presque aucun favori, 0 vente.
        $vide = $this->seller('vide@ex.com');
        $lv = $this->listing($vide, 2252);
        for ($i = 0; $i < 2; $i++) {
            $f = $this->seller("fanV{$i}@ex.com");
            DB::table('favorites')->insert(['user_id' => $f->id, 'listing_id' => $lv->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $ranked = DressingLeaderboard::ranked();

        $this->assertSame($marie->id, $ranked->first()->user_id, 'Le dressing complet (favoris + ventes) passe devant le gros catalogue qui n\'a que des vues.');
        $this->assertSame(1, DressingLeaderboard::rankOf($marie->id));
        $this->assertSame(2, DressingLeaderboard::rankOf($vide->id));
    }

    public function test_un_vendeur_sans_engagement_n_apparait_pas(): void
    {
        $muet = $this->seller('muet@ex.com');
        $this->listing($muet, 0); // 0 vue, 0 favori, 0 vente -> score 0

        $this->assertNull(DressingLeaderboard::rankOf($muet->id));
        $this->assertTrue(DressingLeaderboard::ranked()->isEmpty());
    }

    public function test_un_vendeur_banni_est_exclu(): void
    {
        $banni = $this->seller('banni@ex.com');
        $this->listing($banni, 100);
        $banni->update(['is_banned' => true]);

        $this->assertNull(DressingLeaderboard::rankOf($banni->id));
    }

    public function test_la_page_est_accessible(): void
    {
        $this->get(route('dressings.top'))->assertOk()->assertSee('meilleurs dressings', false);
    }

    public function test_le_badge_top_apparait_sur_le_profil_d_un_classe(): void
    {
        config()->set('leaderboard.top', 10);

        $seller = $this->seller('top@ex.com');
        $l = $this->listing($seller, 500); // 500 vues -> score 500 -> classé #1
        $fan = $this->seller('fan@ex.com');
        DB::table('favorites')->insert([
            'user_id' => $fan->id, 'listing_id' => $l->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(1, DressingLeaderboard::rankOf($seller->id));

        $this->get(route('profiles.show', $seller))
            ->assertOk()
            ->assertSee('Top 1 des dressings', false);
    }
}
