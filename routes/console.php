<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('swapiles:cleanup-pending')->hourly();

// Libère automatiquement les versements vendeurs en attente
// (ventes finalisées dont le vendeur a configuré son Stripe Connect après coup).
Schedule::command('payouts:release-pending')->everyFifteenMinutes()->withoutOverlapping();

// Filets de sécurité paiement : versement auto (colis expédié depuis longtemps
// sans confirmation) + signalement des ventes non expédiées à rembourser.
Schedule::command('transactions:auto-resolve')->dailyAt('07:00')->withoutOverlapping();
