<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\NoteAppli;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NoteAppliTest extends TestCase
{
    use RefreshDatabase;

    public function test_demande_envoyee_une_fois_puis_ignoree(): void
    {
        Queue::fake();
        $lea = User::create(['name' => 'Léa Martin', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);

        $this->assertTrue(NoteAppli::demander($lea));
        $this->assertFalse(NoteAppli::demander($lea->fresh()), 'Pas deux fois en 60 jours.');

        $notif = Notification::where('type', 'app_note')->sole();
        $this->assertSame('/noter', $notif->url);
        $this->assertStringStartsWith('Léa, une note', $notif->message);

        $this->travel(61)->days();
        $this->assertTrue(NoteAppli::demander($lea->fresh()));
    }

    public function test_lien_noter_ouvre_la_page_d_avis_du_bon_store(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)')->get('/noter')
            ->assertRedirect(config('deeplinks.stores.ios') . '?action=write-review');
        $android = $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Mobile')->get('/noter');
        $this->assertStringContainsString('showAllReviews=true', $android->headers->get('Location'));
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0)')->get('/noter')->assertOk()->assertSee('Noter sur l’App Store', false);
    }
}
