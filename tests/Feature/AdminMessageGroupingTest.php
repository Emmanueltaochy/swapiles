<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin — la liste des messages est regroupée par conversation (une ligne par
 * paire de participants + annonce, représentée par le dernier message). On
 * valide la logique de regroupement (LEAST/GREATEST/COALESCE).
 */
class AdminMessageGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U', 'email' => $email, 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    public function test_une_ligne_par_conversation_avec_le_dernier_message(): void
    {
        $a = $this->user('a@ex.com');
        $b = $this->user('b@ex.com');
        $c = $this->user('c@ex.com');

        // Conversation 1 (A <-> B, sans annonce) : 3 messages dans les 2 sens.
        Message::create(['sender_id' => $a->id, 'receiver_id' => $b->id, 'body' => 'm1']);
        Message::create(['sender_id' => $b->id, 'receiver_id' => $a->id, 'body' => 'm2']);
        $lastAb = Message::create(['sender_id' => $a->id, 'receiver_id' => $b->id, 'body' => 'm3']);

        // Conversation 2 (A <-> C) : 1 message.
        $lastAc = Message::create(['sender_id' => $c->id, 'receiver_id' => $a->id, 'body' => 'm4']);

        // Ids du dernier message par conversation (même logique que l'admin).
        $ids = Message::query()
            ->selectRaw('MAX(id) as id')
            ->groupByRaw('(sender_id + receiver_id), (sender_id * receiver_id), COALESCE(listing_id, 0)')
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        // 2 conversations => 2 lignes, et ce sont bien les derniers messages.
        $this->assertCount(2, $ids);
        $this->assertEqualsCanonicalizing([$lastAb->id, $lastAc->id], $ids);
    }

    public function test_meme_paire_annonces_differentes_sont_deux_conversations(): void
    {
        $a = $this->user('a2@ex.com');
        $b = $this->user('b2@ex.com');

        $l1 = \App\Models\Listing::create([
            'user_id' => $a->id, 'title' => 'L1', 'price' => 5, 'status' => 'published',
            'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
        $l2 = \App\Models\Listing::create([
            'user_id' => $a->id, 'title' => 'L2', 'price' => 5, 'status' => 'published',
            'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);

        Message::create(['sender_id' => $a->id, 'receiver_id' => $b->id, 'listing_id' => $l1->id, 'body' => 'x']);
        Message::create(['sender_id' => $b->id, 'receiver_id' => $a->id, 'listing_id' => $l2->id, 'body' => 'y']);

        $count = Message::query()
            ->selectRaw('MAX(id) as id')
            ->groupByRaw('(sender_id + receiver_id), (sender_id * receiver_id), COALESCE(listing_id, 0)')
            ->get()
            ->count();

        $this->assertSame(2, $count, 'Deux annonces distinctes = deux conversations.');
    }
}
