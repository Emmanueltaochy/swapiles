<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jetons d'appareil : un jeton n'identifie pas un appareil de façon durable.
 * Il change à chaque réinstallation, ce qui gonfle le compteur bien au-delà du
 * nombre réel d'installations affiché par Google Play ou l'App Store.
 */
class DeviceTokenCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function jeton(string $token, ?int $vuIlYAJours): DeviceToken
    {
        return DeviceToken::create([
            'token' => $token,
            'platform' => 'android',
            'last_seen_at' => $vuIlYAJours === null ? null : now()->subDays($vuIlYAJours),
        ]);
    }

    public function test_seuls_les_jetons_revus_recemment_sont_actifs(): void
    {
        config(['push.active_days' => 30]);

        $this->jeton('recent', 2);
        $this->jeton('limite', 29);
        $this->jeton('ancien', 60);
        $this->jeton('jamais-vu', null);

        $this->assertSame(2, DeviceToken::actifs()->count());
        $this->assertSame(2, DeviceToken::obsoletes()->count());
    }

    public function test_le_menage_supprime_les_jetons_trop_anciens(): void
    {
        $this->jeton('vivant', 5);
        $this->jeton('mort', 120);
        $this->jeton('mort-aussi', 200);

        $this->artisan('push:cleanup-tokens', ['--days' => 90])->assertExitCode(0);

        $this->assertSame(1, DeviceToken::count());
        $this->assertDatabaseHas('device_tokens', ['token' => 'vivant']);
    }

    public function test_le_menage_en_simulation_ne_supprime_rien(): void
    {
        $this->jeton('mort', 200);

        $this->artisan('push:cleanup-tokens', ['--days' => 90, '--dry-run' => true])->assertExitCode(0);

        $this->assertSame(1, DeviceToken::count());
    }

    public function test_une_reinstallation_cree_bien_une_seconde_ligne(): void
    {
        // Comportement normal du service : le jeton change, l'ancien subsiste.
        // C'est exactement ce qui fait diverger le compteur des installations.
        $this->jeton('ancien-jeton', 100);
        $this->jeton('nouveau-jeton', 1);

        $this->assertSame(2, DeviceToken::count());
        $this->assertSame(1, DeviceToken::actifs()->count());
    }
}
