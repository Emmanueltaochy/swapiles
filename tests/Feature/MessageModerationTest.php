<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Support\MessageModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modération Partie 1 — détection par mots-clés (sans IA).
 */
class MessageModerationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U', 'email' => $email, 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    public function test_detecte_les_variantes_de_paiement(): void
    {
        $this->assertNotEmpty(MessageModeration::detectPayment('tu peux payer par wero ?'));
        $this->assertNotEmpty(MessageModeration::detectPayment('W E R O stp'));
        $this->assertNotEmpty(MessageModeration::detectPayment('envoie sur payp4l'));
        $this->assertNotEmpty(MessageModeration::detectPayment('je te donne mon R.I.B'));
        $this->assertNotEmpty(MessageModeration::detectPayment('voici mon ibán')); // accent
        $this->assertNotEmpty(MessageModeration::detectPayment('western   union ?'));

        // Pas de faux positif sur des mots contenant « rib » / « iban ».
        $this->assertEmpty(MessageModeration::detectPayment('je peux te l\'attribuer demain'));
        $this->assertEmpty(MessageModeration::detectPayment('rendez-vous à Saint-Denis'));
    }

    public function test_detecte_un_numero_ou_contact(): void
    {
        $this->assertTrue(MessageModeration::detectPhone('appelle moi au 0692 12 34 56'));
        $this->assertTrue(MessageModeration::detectPhone('on continue sur whatsapp ?'));
        $this->assertTrue(MessageModeration::detectPhone('donne ton numéro stp'));
        $this->assertFalse(MessageModeration::detectPhone('le prix est de 25 euros'));
    }

    public function test_mot_cle_paiement_bloque_l_envoi_sans_confirmation(): void
    {
        $sender = $this->user('s@ex.com');
        $receiver = $this->user('r@ex.com');

        $resp = $this->actingAs($sender)->post(route('account.messages.store.general', $receiver), [
            'body' => 'tu peux me payer par paypal ?',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('moderation_payment_warning');
        $this->assertSame(0, Message::count(), 'Le message ne doit pas être créé sans confirmation.');
    }

    public function test_envoi_confirme_cree_le_message_signale(): void
    {
        $sender = $this->user('s2@ex.com');
        $receiver = $this->user('r2@ex.com');

        $this->actingAs($sender)->post(route('account.messages.store.general', $receiver), [
            'body' => 'paiement par wero possible ?',
            'moderation_confirm' => '1',
        ])->assertRedirect();

        $message = Message::first();
        $this->assertNotNull($message);
        $this->assertSame('payment_forced', $message->flag_kind);
        $this->assertNotNull($message->flagged_at);
    }

    public function test_numero_est_signale_mais_message_delivre(): void
    {
        $sender = $this->user('s3@ex.com');
        $receiver = $this->user('r3@ex.com');

        $this->actingAs($sender)->post(route('account.messages.store.general', $receiver), [
            'body' => 'salut, tu peux m\'appeler au 0692 11 22 33 ?',
        ])->assertRedirect();

        $message = Message::first();
        $this->assertNotNull($message, 'Le message doit être délivré (pas de blocage).');
        $this->assertSame('phone', $message->flag_kind);
    }

    public function test_message_normal_non_signale(): void
    {
        $sender = $this->user('s4@ex.com');
        $receiver = $this->user('r4@ex.com');

        $this->actingAs($sender)->post(route('account.messages.store.general', $receiver), [
            'body' => 'Bonjour, l\'article est-il toujours disponible ?',
        ])->assertRedirect();

        $message = Message::first();
        $this->assertNotNull($message);
        $this->assertNull($message->flagged_at);
    }
}
