<?php

namespace Tests\Feature;

use App\Models\BlockedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blocage d'utilisateur + liste noire d'e-mails persistante (survit à la
 * suppression du compte).
 */
class BlockedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_blocked_est_insensible_casse_et_espaces(): void
    {
        BlockedEmail::block('Spam@Example.com ');

        $this->assertTrue(BlockedEmail::isBlocked('spam@example.com'));
        $this->assertTrue(BlockedEmail::isBlocked('  SPAM@EXAMPLE.COM'));
        $this->assertFalse(BlockedEmail::isBlocked('autre@example.com'));
        $this->assertSame(1, BlockedEmail::count());

        // Idempotent : re-bloquer le même e-mail ne crée pas de doublon.
        BlockedEmail::block('spam@example.com');
        $this->assertSame(1, BlockedEmail::count());
    }

    public function test_email_bloque_ne_peut_pas_s_inscrire(): void
    {
        BlockedEmail::block('banni@example.com', 'Test');

        $response = $this->post(route('register.store'), [
            'name' => 'X',
            'email' => 'Banni@Example.com',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'territoire' => 'La Réunion',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'banni@example.com']);
    }

    public function test_supprimer_un_membre_banni_bloque_son_email(): void
    {
        $user = User::create([
            'name' => 'V', 'email' => 'mechant@example.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion', 'is_banned' => true,
        ]);

        $user->delete();

        $this->assertTrue(BlockedEmail::isBlocked('mechant@example.com'),
            'L’e-mail d’un membre banni supprimé doit rester bloqué.');
    }

    public function test_supprimer_un_membre_non_banni_ne_bloque_pas_son_email(): void
    {
        $user = User::create([
            'name' => 'OK', 'email' => 'gentil@example.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion', 'is_banned' => false,
        ]);

        $user->delete();

        $this->assertFalse(BlockedEmail::isBlocked('gentil@example.com'));
    }

    public function test_interlocuteurs_notifies_a_la_suppression_du_membre(): void
    {
        $deleted = User::create([
            'name' => 'Parti', 'email' => 'parti@example.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
        $partner = User::create([
            'name' => 'Reste', 'email' => 'reste@example.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
        $stranger = User::create([
            'name' => 'Inconnu', 'email' => 'inconnu@example.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);

        \App\Models\Message::create(['sender_id' => $deleted->id, 'receiver_id' => $partner->id, 'body' => 'coucou']);
        \App\Models\Message::create(['sender_id' => $partner->id, 'receiver_id' => $deleted->id, 'body' => 'salut']);

        $deleted->delete();

        // L'interlocuteur est prévenu, une seule fois.
        $this->assertSame(1, \App\Models\Notification::where('user_id', $partner->id)
            ->where('type', 'user_deleted')->count());
        // Un membre sans échange n'est pas notifié.
        $this->assertSame(0, \App\Models\Notification::where('user_id', $stranger->id)
            ->where('type', 'user_deleted')->count());
    }

    public function test_deblocage_libere_l_email(): void
    {
        BlockedEmail::block('temp@example.com');
        $this->assertTrue(BlockedEmail::isBlocked('temp@example.com'));

        BlockedEmail::unblock('TEMP@example.com');
        $this->assertFalse(BlockedEmail::isBlocked('temp@example.com'));
    }
}
