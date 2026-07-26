<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pièces jointes (photo / vidéo) dans la messagerie.
 */
class MessageAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;
    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::create(['name' => 'V', 'email' => 's@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $this->buyer = User::create(['name' => 'A', 'email' => 'a@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $this->listing = Listing::create([
            'user_id' => $this->seller->id, 'title' => 'Sac', 'price' => 10,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
    }

    private function route(): string
    {
        return route('account.messages.store', ['listing' => $this->listing, 'user' => $this->seller]);
    }

    public function test_envoi_d_une_photo_sans_texte(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->buyer)->post($this->route(), [
            'attachment' => UploadedFile::fake()->image('photo.jpg', 400, 300),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $msg = Message::latest('id')->first();
        $this->assertNotNull($msg);
        $this->assertSame('image', $msg->attachment_type);
        $this->assertTrue($msg->hasAttachment());
        $this->assertTrue($msg->isImageAttachment());
        Storage::disk('public')->assertExists($msg->attachment_path);
    }

    public function test_message_texte_seul_reste_possible(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->buyer)->post($this->route(), ['body' => 'Bonjour']);

        $response->assertSessionHasNoErrors();
        $this->assertFalse(Message::latest('id')->first()->hasAttachment());
    }

    public function test_ni_texte_ni_piece_jointe_est_refuse(): void
    {
        $response = $this->actingAs($this->buyer)->post($this->route(), []);

        $response->assertSessionHasErrors(['body']);
    }

    public function test_rendu_video(): void
    {
        // Détection du type vidéo au niveau du modèle (rendu <video>).
        $msg = Message::create([
            'listing_id' => $this->listing->id, 'sender_id' => $this->buyer->id, 'receiver_id' => $this->seller->id,
            'body' => null, 'attachment_path' => 'messages/x.mp4', 'attachment_type' => 'video', 'attachment_mime' => 'video/mp4',
        ]);

        $this->assertTrue($msg->isVideoAttachment());
        $this->assertFalse($msg->isImageAttachment());
        $this->assertNotNull($msg->attachmentUrl());
    }
}
