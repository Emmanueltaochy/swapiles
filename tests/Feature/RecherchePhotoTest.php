<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** 📷 Recherche par photo : la photo devient une recherche classique, même budget que l'IA dépôt. */
class RecherchePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function fauxClient(array $reponseIa): void
    {
        $reponse = (object) [
            'stopReason' => 'end_turn',
            'usage' => (object) ['inputTokens' => 1500, 'outputTokens' => 200, 'cacheCreationInputTokens' => 0, 'cacheReadInputTokens' => 0],
            'content' => [(object) ['type' => 'text', 'text' => json_encode($reponseIa)]],
        ];
        $this->app->instance('ia_depot.client', (object) ['messages' => new class($reponse) {
            public function __construct(private object $reponse) {}

            public function create(...$args): object
            {
                return $this->reponse;
            }
        }]);
    }

    public function test_masquee_sans_cle(): void
    {
        config()->set('ia_depot.cle', null);
        $this->get('/')->assertOk()->assertDontSee('data-recherche-photo', false);
        $this->postJson(route('search.photo'), ['photo' => UploadedFile::fake()->image('p.jpg')])->assertNotFound();
    }

    public function test_photo_vers_recherche(): void
    {
        config()->set('ia_depot.cle', 'cle-de-test');
        $this->fauxClient(['mots_cles' => 'robe fleurie', 'categorie' => 'femme']);

        $this->get('/')->assertOk()->assertSee('data-recherche-photo', false)->assertSee('l’IA remplit ton annonce', false);

        $url = $this->postJson(route('search.photo'), ['photo' => UploadedFile::fake()->image('p.jpg', 1200, 900)])
            ->assertOk()->json('url');
        $this->assertStringContainsString('q=robe%20fleurie', str_replace('+', '%20', $url));
        $this->assertStringContainsString('category=femme', $url);
        $this->assertSame('recherche_photo', DB::table('ia_depot_usages')->value('fonction'));
        // L'admin voit ce qui a été cherché (jamais la photo).
        $this->assertStringContainsString('robe fleurie', (string) DB::table('ia_depot_usages')->value('resultat'));

        $this->get($url)->assertOk()->assertSee('Recherche à partir de ta photo', false);
    }

    public function test_categorie_inventee_ignoree(): void
    {
        config()->set('ia_depot.cle', 'cle-de-test');
        $this->fauxClient(['mots_cles' => 'tondeuse', 'categorie' => 'jardinage-extreme']);

        $url = $this->postJson(route('search.photo'), ['photo' => UploadedFile::fake()->image('p.jpg')])->assertOk()->json('url');
        $this->assertStringNotContainsString('category=', $url);
    }
}
