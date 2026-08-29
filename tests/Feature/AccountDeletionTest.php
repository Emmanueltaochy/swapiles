<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suppression de compte à la demande (RGPD + exigence des stores) :
 *   - sans historique financier : suppression pure ;
 *   - avec des transactions : anonymisation (comptabilité conservée).
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U ' . $email, 'email' => $email, 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    public function test_la_page_publique_est_accessible_sans_connexion(): void
    {
        $this->get(route('account.deletion.info'))
            ->assertOk()
            ->assertSee('Supprimer mon compte');
    }

    public function test_suppression_pure_sans_historique_financier(): void
    {
        $user = $this->user('del@ex.com');

        $this->actingAs($user)->delete(route('account.delete'), [
            'confirmation' => 'SUPPRIMER',
            'password' => 'secret1234',
        ])->assertRedirect(route('home'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertGuest();
    }

    public function test_mauvais_mot_de_passe_bloque_la_suppression(): void
    {
        $user = $this->user('del2@ex.com');

        $this->actingAs($user)->delete(route('account.delete'), [
            'confirmation' => 'SUPPRIMER',
            'password' => 'mauvais',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_confirmation_textuelle_obligatoire(): void
    {
        $user = $this->user('del3@ex.com');

        $this->actingAs($user)->delete(route('account.delete'), [
            'confirmation' => 'oui',
            'password' => 'secret1234',
        ])->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_anonymisation_si_transactions_existantes(): void
    {
        $seller = $this->user('seller@ex.com');
        $buyer = $this->user('buyer@ex.com');

        $listing = Listing::create([
            'user_id' => $seller->id, 'title' => 'Vélo', 'price' => 30,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => false, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);

        Transaction::create([
            'listing_id' => $listing->id,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'amount' => 30,
            'status' => 'completed',
        ]);

        $this->actingAs($seller)->delete(route('account.delete'), [
            'confirmation' => 'SUPPRIMER',
            'password' => 'secret1234',
        ])->assertRedirect(route('home'));

        // Le compte existe toujours mais est anonymisé.
        $seller->refresh();
        $this->assertSame('Compte supprimé', $seller->name);
        $this->assertStringContainsString('@swapiles.invalid', $seller->email);
        $this->assertTrue((bool) $seller->is_banned);

        // La transaction (comptabilité) est conservée.
        $this->assertDatabaseHas('transactions', ['listing_id' => $listing->id, 'seller_id' => $seller->id]);

        // L'annonce est retirée de la vente.
        $this->assertSame('draft', $listing->fresh()->status);
    }
}
