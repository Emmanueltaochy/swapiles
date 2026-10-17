<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\MembresIndicateurs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMembresIndicateursTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $nom, string $ile, array $plus = []): User
    {
        $u = User::create(['name' => $nom, 'email' => strtolower($nom) . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => $ile]);
        $u->forceFill($plus)->save();

        return $u;
    }

    public function test_indicateurs_et_repartition_par_ile(): void
    {
        $admin = $this->membre('Admin', 'La Réunion');
        $admin->forceFill(['email' => 'cabinet@taochyconsulting.fr'])->save();
        $lea = $this->membre('Lea', 'La Réunion', ['comment_connu' => 'Facebook', 'stripe_charges_enabled' => true]);
        $this->membre('Tom', 'Martinique', ['comment_connu' => 'Facebook']);
        $this->membre('Zoe', 'Martinique', ['comment_connu' => 'Bouche-à-oreille', 'is_banned' => true]);
        Listing::create(['user_id' => $lea->id, 'title' => 'Robe', 'description' => 'Description de test assez longue.', 'price' => 20, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion']);

        $c = MembresIndicateurs::chiffres();
        $this->assertSame(4, $c['total']);
        $this->assertSame(['ile' => 'La Réunion', 'membres' => 2, 'nouveaux' => 2], collect($c['par_ile'])->firstWhere('ile', 'La Réunion'));
        $this->assertSame(['Facebook' => 2, 'Bouche-à-oreille' => 1], $c['comment_connu']);
        $this->assertSame(1, $c['vendeurs']);
        $this->assertSame(1, $c['paiement_actif']);
        $this->assertSame(1, $c['bannis']);

        $this->actingAs($admin)->get('/admin/users')->assertOk()
            ->assertSee('Membres par île')->assertSee('Comment ils nous ont connus')->assertSee('Martinique');
    }
}
