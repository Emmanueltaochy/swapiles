<?php

namespace Tests\Feature;

use App\Models\SearchAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Point 17b : la capture « préviens-moi » enregistre l'intérêt (terme + e-mail)
 * SANS envoyer d'e-mail. Un même e-mail ne s'abonne qu'une fois par terme.
 */
class SearchAlertCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_enregistre_alerte_normalisee(): void
    {
        $resp = $this->post(route('search.alert.store'), [
            'term' => '  PlayStation  5 ',
            'email' => 'Buyer@Example.COM',
            'territoire' => 'Guadeloupe',
        ]);

        $resp->assertSessionHas('alert_saved');

        $this->assertDatabaseHas('search_alerts', [
            'term' => 'playstation 5',
            'email' => 'buyer@example.com',
            'territoire' => 'Guadeloupe',
        ]);
        $this->assertSame(1, SearchAlert::count());
        $this->assertNull(SearchAlert::first()->notified_at, 'Aucune alerte ne doit être marquée envoyée à la capture.');
    }

    public function test_meme_email_meme_terme_ne_cree_pas_de_doublon(): void
    {
        $payload = ['term' => 'iphone 13', 'email' => 'a@ex.com'];
        $this->post(route('search.alert.store'), $payload);
        $this->post(route('search.alert.store'), $payload);

        $this->assertSame(1, SearchAlert::where('term', 'iphone 13')->where('email', 'a@ex.com')->count());
    }

    public function test_email_invalide_rejete(): void
    {
        $resp = $this->post(route('search.alert.store'), [
            'term' => 'canapé',
            'email' => 'pas-un-email',
        ]);

        $resp->assertSessionHasErrors('email');
        $this->assertSame(0, SearchAlert::count());
    }
}
