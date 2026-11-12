<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Génération du bordereau Colissimo par le vendeur : une vente seule
 * (hors lot) plantait au calcul du poids depuis l'arrivée des lots.
 */
class BordereauColissimoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['COLISSIMO_ACCOUNT_NUMBER' => '123456', 'COLISSIMO_PASSWORD' => 'secret'] as $cle => $valeur) {
            putenv("{$cle}={$valeur}");
            $_ENV[$cle] = $_SERVER[$cle] = $valeur;
        }
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach (['COLISSIMO_ACCOUNT_NUMBER', 'COLISSIMO_PASSWORD'] as $cle) {
            putenv($cle);
            unset($_ENV[$cle], $_SERVER[$cle]);
        }
        parent::tearDown();
    }

    private function venteSeule(): Transaction
    {
        $vendeuse = User::create(['name' => 'Muriel', 'email' => 'muriel@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion',
            'email_verified_at' => now(), 'address_line1' => '12 rue des Lilas', 'postal_code' => '97410', 'city' => 'Saint-Pierre', 'phone' => '0692000000']);
        $acheteur = User::create(['name' => 'Paul', 'email' => 'paul@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
        $annonce = Listing::create(['user_id' => $vendeuse->id, 'title' => 'Robe en lin', 'description' => 'Description de test assez longue.', 'price' => 20,
            'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion', 'weight_kg' => 1.2]);

        return Transaction::create(['listing_id' => $annonce->id, 'seller_id' => $vendeuse->id, 'buyer_id' => $acheteur->id, 'amount' => 30, 'seller_amount' => 20,
            'shipping_fee' => 8, 'currency' => 'EUR', 'status' => 'paid', 'delivery_method' => 'colissimo',
            'shipping_address_line1' => '5 avenue de la Mer', 'shipping_postal_code' => '13001', 'shipping_city' => 'Marseille', 'shipping_country' => 'France',
            'buyer_full_name' => 'Paul Martin', 'buyer_phone' => '0600000000']);
    }

    public function test_le_vendeur_genere_le_bordereau_d_une_vente_seule(): void
    {
        Http::fake(['ws.colissimo.fr/*' => Http::response("--uuid\r\nContent-Type: application/json\r\n\r\n{\"messages\":[{\"id\":\"0\",\"type\":\"INFOS\",\"messageContent\":\"La requête a été traitée avec succès\"}]}\r\n--uuid\r\nContent-Type: application/octet-stream\r\n\r\n%PDF-1.4 étiquette\r\n--uuid--", 200)]);
        $vente = $this->venteSeule();

        $this->actingAs($vente->seller)->post(route('account.colissimo.generate', $vente))
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'généré avec succès'));

        $vente->refresh();
        $this->assertSame('transaction-' . $vente->id . '.pdf', basename($vente->colissimo_label_path));
        Storage::disk('local')->assertExists($vente->colissimo_label_path);
        // Poids de l'article et déclaration de contenu (envoi depuis La Réunion).
        Http::assertSent(fn ($r) => $r['letter']['parcel']['weight'] == 1.2 && isset($r['letter']['customsDeclarations']));
        // Codes pays Colissimo : « France » saisi à la commande devient FR, vendeuse à La Réunion en RE.
        Http::assertSent(fn ($r) => $r['letter']['addressee']['address']['countryCode'] === 'FR'
            && $r['letter']['sender']['address']['countryCode'] === 'RE');
    }

    public function test_message_clair_si_colissimo_refuse(): void
    {
        Http::fake(['ws.colissimo.fr/*' => Http::response('{"messages":[{"id":"30220","type":"ERROR","messageContent":"Le code postal est invalide"}]}', 400)]);
        $vente = $this->venteSeule();

        User::create(['name' => 'Emmanuel', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        $this->actingAs($vente->seller)->post(route('account.colissimo.generate', $vente))
            ->assertSessionHasErrors(['colissimo' => 'Le bordereau Colissimo n’a pas pu être généré : Colissimo : 30220 Le code postal est invalide. Si le problème continue, écrivez-nous à contact@swapiles.com.']);
        $this->assertNull($vente->fresh()->colissimo_label_path);
        // Emmanuel est prévenu (une seule alerte par heure pour la même vente).
        $this->actingAs($vente->seller)->post(route('account.colissimo.generate', $vente));
        $this->assertSame(1, \App\Models\Notification::where('type', 'colissimo_echec')->count());
    }
}
