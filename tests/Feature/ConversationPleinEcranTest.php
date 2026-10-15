<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Conversation plein écran et réponse à un message (glisser, comme Instagram). */
class ConversationPleinEcranTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $nom, string $email): User
    {
        return User::create(['name' => $nom, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    public function test_repondre_a_un_message_de_la_conversation(): void
    {
        $lea = $this->membre('Léa', 'lea@ex.com');
        $paul = $this->membre('Paul', 'paul@ex.com');
        $question = Message::create(['sender_id' => $paul->id, 'receiver_id' => $lea->id, 'body' => 'Elle taille comment cette robe ?']);

        $this->actingAs($lea)->post(route('account.messages.store.general', $paul), [
            'body' => 'Elle taille normalement, du 38',
            'reply_to_id' => $question->id,
        ])->assertRedirect();

        $reponse = Message::latest('id')->first();
        $this->assertSame($question->id, $reponse->reply_to_id);

        $this->actingAs($lea)->get(route('account.messages.show.general', $paul))->assertOk()
            ->assertSee('data-conversation', false)
            ->assertSee('data-aller-a="' . $question->id . '"', false)
            ->assertSee('Elle taille comment cette robe ?')
            ->assertSee('name="reply_to_id"', false)
            ->assertSee('Aujourd’hui', false);
    }

    public function test_impossible_de_citer_un_message_d_une_autre_conversation(): void
    {
        $lea = $this->membre('Léa', 'lea@ex.com');
        $paul = $this->membre('Paul', 'paul@ex.com');
        $zoe = $this->membre('Zoé', 'zoe@ex.com');
        $prive = Message::create(['sender_id' => $zoe->id, 'receiver_id' => $paul->id, 'body' => 'Message privé entre Zoé et Paul']);

        $this->actingAs($lea)->post(route('account.messages.store.general', $paul), [
            'body' => 'Coucou', 'reply_to_id' => $prive->id,
        ]);

        $this->assertNull(Message::latest('id')->first()->reply_to_id);
    }

    public function test_conversation_d_annonce_en_plein_ecran(): void
    {
        $vendeur = $this->membre('Léa', 'lea@ex.com');
        $acheteur = $this->membre('Paul', 'paul@ex.com');
        $annonce = Listing::create(['user_id' => $vendeur->id, 'title' => 'Robe rouge', 'description' => 'Description de test assez longue.', 'price' => 15, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion']);

        $this->actingAs($acheteur)->get(route('account.messages.show', ['listing' => $annonce, 'user' => $vendeur]))->assertOk()
            ->assertSee('Robe rouge')->assertSee('Écrire un message…', false)->assertSee('aria-label="Envoyer"', false);
    }
}
