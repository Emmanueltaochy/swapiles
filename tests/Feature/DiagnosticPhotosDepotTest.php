<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Comptes rendus anonymes du dépôt d'annonce (choix des photos, envois coupés)
 * et leur lecture dans le diagnostic des journaux de déploiement.
 */
class DiagnosticPhotosDepotTest extends TestCase
{
    use RefreshDatabase;

    public function test_compte_rendu_du_choix_des_photos(): void
    {
        $lea = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);

        $this->actingAs($lea)->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0) SwapilesApp')
            ->post(route('account.listings.photos-diag'), [
                'choisies' => 3, 'ajoutees' => 1, 'reduites' => 1, 'illisibles' => 2, 'types' => 'image/jpeg,vide',
                'plus_grosse_ko' => 9800, 'duree_ms' => 2500, 'etapes' => 'bmp:delai,img:illisible', 'erreur' => '<script>x</script>',
            ])->assertNoContent();

        $cas = Cache::get('depot:photos')[0];
        $this->assertSame('appli iPhone', $cas['appareil']);
        $this->assertSame(3, $cas['choisies']);
        $this->assertSame(1, $cas['ajoutees']);
        $this->assertSame('image/jpeg,vide', $cas['types']);
        $this->assertStringNotContainsString('<', $cas['erreur']);

        $this->artisan('depot:diagnostic')
            ->expectsOutputToContain('1 fois récemment, 1 avec au moins une photo non ajoutée')
            ->expectsOutputToContain('appli iPhone | choisies 3, ajoutées 1')
            ->assertExitCode(0);
    }

    public function test_reserve_aux_membres_connectes(): void
    {
        $this->post(route('account.listings.photos-diag'), ['choisies' => 1])->assertRedirect();
        $this->assertNull(Cache::get('depot:photos'));
    }
}
