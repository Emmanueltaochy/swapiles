<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DossierPartenaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dossier PDF pour les entreprises partenaires : chiffres réels, totaux anonymes. */
class DossierPartenaireTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $nom, string $ile): User
    {
        return User::create(['name' => $nom, 'email' => strtolower($nom) . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => $ile]);
    }

    public function test_chiffres_et_pdf(): void
    {
        $lea = $this->membre('Lea', 'La Réunion');
        $tom = $this->membre('Tom', 'Martinique');
        $robe = Listing::create(['user_id' => $lea->id, 'title' => 'Robe', 'description' => 'Description de test assez longue.', 'price' => 20, 'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'category_level1' => 'femme']);
        Listing::create(['user_id' => $tom->id, 'title' => 'Vélo', 'description' => 'Description de test assez longue.', 'price' => 50, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'Martinique']);
        Transaction::create(['listing_id' => $robe->id, 'buyer_id' => $tom->id, 'seller_id' => $lea->id, 'status' => 'completed', 'payment_method' => 'especes', 'amount' => 20]);

        $d = DossierPartenaire::donnees('La Réunion');

        $this->assertSame(2, $d['membres']);
        $this->assertSame(1, $d['annonces']);
        $this->assertSame(1, $d['impact']['articles']);
        $this->assertSame(1, $d['focus']['membres']);
        $this->assertSame(0, $d['focus']['annonces']);
        $this->assertSame(1, $d['focus']['impact']['articles']);
        $this->assertNull(DossierPartenaire::donnees('Atlantide')['focus']);

        $pdf = DossierPartenaire::pdf('La Réunion');
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringNotContainsString('lea@ex.com', $pdf);
        $this->assertSame('swapiles-dossier-partenaires-la-reunion-' . now()->format('Y-m-d') . '.pdf', DossierPartenaire::nomFichier('La Réunion'));
    }

    public function test_page_admin(): void
    {
        $admin = $this->membre('Admin', 'La Réunion');
        $admin->forceFill(['email' => 'cabinet@taochyconsulting.fr'])->save();

        $this->actingAs($admin)->get('/admin/dossier-partenaires')->assertOk()
            ->assertSee('Télécharger le PDF')->assertSee('Devenir partenaire');
    }
}
