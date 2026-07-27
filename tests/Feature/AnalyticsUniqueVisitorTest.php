<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Support\AnalyticsMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Point 14 : « visiteur unique » = identifiant STABLE (visitor_id), pas
 * session_id. Le churn de session ne doit plus surcompter.
 */
class AnalyticsUniqueVisitorTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $visitorId, string $sessionId): void
    {
        AnalyticsEvent::create([
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'ip_address' => '10.0.0.1',
            'method' => 'GET',
            'path' => '/',
            'created_at' => now(),
        ]);
    }

    public function test_le_churn_de_session_ne_surcompte_plus(): void
    {
        // Visiteur A : 3 sessions différentes (régénérations) = 1 personne.
        $this->event('visitorA', 'sess1');
        $this->event('visitorA', 'sess2');
        $this->event('visitorA', 'sess3');
        // Visiteur B : 1 session = 1 personne.
        $this->event('visitorB', 'sess4');

        // Ancien comptage (session_id distincts) aurait donné 4.
        $this->assertSame(2, AnalyticsMetrics::todayUniqueVisitors());
    }

    public function test_repli_sur_session_id_pour_les_evenements_historiques(): void
    {
        // Événements anciens sans visitor_id : on retombe sur session_id.
        $this->event('', 'old-sess'); // visitor_id vide -> COALESCE prend session_id
        AnalyticsEvent::create([
            'visitor_id' => null, 'session_id' => 'old-sess-2', 'ip_address' => '10.0.0.2',
            'method' => 'GET', 'path' => '/', 'created_at' => now(),
        ]);

        $this->assertSame(2, AnalyticsMetrics::todayUniqueVisitors());
    }
}
