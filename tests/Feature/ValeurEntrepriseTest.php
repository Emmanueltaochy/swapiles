<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ValeurEntreprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Valeur de Swap'Îles : estimation prudente sur les vrais revenus, plancher technologie + membres actifs. */
class ValeurEntrepriseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-12-20 15:00', 'Indian/Reunion'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $nom): User
    {
        return User::create(['name' => $nom, 'email' => strtolower($nom) . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    /** Vente payée en ligne, avec $protection € de revenus pour Swap'Îles. */
    private function vente(float $protection, string $le, array $plus = []): void
    {
        $vendeur = $this->membre('Vendeur');
        $annonce = Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => 'sold', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
        $t = Transaction::create($plus + [
            'listing_id' => $annonce->id, 'seller_id' => $vendeur->id, 'buyer_id' => $this->membre('Acheteur')->id,
            'amount' => 20 + $protection, 'seller_amount' => 20, 'commission' => 0, 'buyer_protection_fee' => $protection, 'shipping_fee' => 0,
            'currency' => 'EUR', 'payment_method' => 'cb', 'delivery_method' => 'hand_delivery', 'status' => 'completed',
            'shipping_status' => 'received', 'stripe_payment_intent_id' => 'pi_' . uniqid(),
        ]);
        $t->forceFill(['created_at' => Carbon::parse($le)])->save();
    }

    public function test_sans_revenus_la_valeur_repose_sur_la_technologie(): void
    {
        $v = ValeurEntreprise::estimation();

        $this->assertSame('actifs', $v['methode']);
        $this->assertSame(['bas' => 15000, 'haut' => 40000], $v['fourchette']);
        $this->assertSame(0.0, $v['revenus']['rythme_annuel']);
        $this->assertNull($v['part_en_ligne']);
    }

    public function test_les_revenus_et_la_croissance_fixent_la_valeur(): void
    {
        // 3 mois précédents (juin à août) : 1 000 € ; 3 derniers mois complets (sept. à nov.) : 3 000 € → +200 %.
        $this->vente(1000, '2026-07-10 12:00');
        $this->vente(3000, '2026-10-10 12:00');
        // Le mois en cours ne compte pas dans le rythme (mois incomplet).
        $this->vente(500, '2026-12-05 12:00');

        $v = ValeurEntreprise::estimation();

        $this->assertSame(3000.0, $v['revenus']['trimestre']);
        $this->assertSame(200, $v['revenus']['croissance']);
        $this->assertSame(12000.0, $v['revenus']['rythme_annuel']);
        // Forte croissance : × 2 à × 4 → 24 000 à 48 000 €, plus haut que le plancher technologie.
        $this->assertSame(['bas' => 24000, 'haut' => 48000], $v['par_revenus']);
        $this->assertSame(['bas' => 24000, 'haut' => 48000], $v['fourchette']);
        $this->assertSame('revenus', $v['methode']);
        $this->assertSame(4000.0, $v['revenus']['douze_mois']);
    }

    public function test_revenus_en_baisse_multiple_prudent(): void
    {
        $this->vente(3000, '2026-07-10 12:00');
        $this->vente(1000, '2026-10-10 12:00');

        $v = ValeurEntreprise::estimation();

        $this->assertSame(-67, $v['revenus']['croissance']);
        $this->assertSame(0.5, $v['multiple']['bas']);
        // 4 000 € par an × 0,5 à 1 : sous le plancher, la technologie l'emporte.
        $this->assertSame(['bas' => 15000, 'haut' => 40000], $v['fourchette']);
    }

    public function test_page_admin(): void
    {
        $admin = $this->membre('Admin');
        $admin->forceFill(['email' => 'cabinet@taochyconsulting.fr'])->save();
        $this->vente(2, '2026-12-10 12:00');

        $this->actingAs($admin)->get('/admin/valeur-swapiles')->assertOk()
            ->assertSee('Valeur estimée aujourd’hui', false)
            ->assertSee('15 000 € – 40 000 €', false)
            ->assertSee('Ce qui fait monter la valeur', false);
    }
}
