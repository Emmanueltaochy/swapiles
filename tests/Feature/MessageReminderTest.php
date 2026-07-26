<?php

namespace Tests\Feature;

use App\Jobs\SendMessageReminderEmail;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Point 8 : relance après 24 h SANS réponse — jamais si la conversation a
 * progressé, jamais deux fois.
 */
class MessageReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;
    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::create(['name' => 'V', 'email' => 's_'.uniqid().'@ex.com', 'password' => bcrypt('x12345678'), 'territoire' => 'La Réunion']);
        $this->buyer = User::create(['name' => 'A', 'email' => 'b_'.uniqid().'@ex.com', 'password' => bcrypt('x12345678'), 'territoire' => 'La Réunion']);
        $this->listing = Listing::create([
            'user_id' => $this->seller->id, 'title' => 'Sac', 'price' => 10,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
    }

    private function msg(int $from, int $to, string $ago = '-25 hours'): Message
    {
        $m = Message::create([
            'listing_id' => $this->listing->id, 'sender_id' => $from, 'receiver_id' => $to,
            'body' => 'Bonjour, dispo ?',
        ]);
        // created_at n'est pas mass-assignable : on le force après coup.
        $m->forceFill(['created_at' => now()->modify($ago)])->saveQuietly();

        return $m->fresh();
    }

    public function test_relance_un_message_sans_reponse_depuis_24h(): void
    {
        Queue::fake();
        $m = $this->msg($this->buyer->id, $this->seller->id, '-25 hours');

        $this->artisan('messages:remind-unanswered')->assertExitCode(0);

        Queue::assertPushed(SendMessageReminderEmail::class, 1);
        $this->assertNotNull($m->fresh()->reminder_sent_at);
    }

    public function test_ne_relance_pas_si_la_conversation_a_progresse(): void
    {
        Queue::fake();
        // L'acheteur écrit (il y a 25h), PUIS le vendeur a répondu (il y a 2h).
        $this->msg($this->buyer->id, $this->seller->id, '-25 hours');
        $this->msg($this->seller->id, $this->buyer->id, '-2 hours');

        $this->artisan('messages:remind-unanswered')->assertExitCode(0);

        Queue::assertNotPushed(SendMessageReminderEmail::class);
    }

    public function test_ne_relance_pas_deux_fois(): void
    {
        Queue::fake();
        $this->msg($this->buyer->id, $this->seller->id, '-25 hours');

        $this->artisan('messages:remind-unanswered');
        $this->artisan('messages:remind-unanswered');

        Queue::assertPushed(SendMessageReminderEmail::class, 1);
    }

    public function test_ne_relance_pas_un_message_trop_recent(): void
    {
        Queue::fake();
        $this->msg($this->buyer->id, $this->seller->id, '-2 hours');

        $this->artisan('messages:remind-unanswered');

        Queue::assertNotPushed(SendMessageReminderEmail::class);
    }
}
