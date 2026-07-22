<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('swapiles:cleanup-pending')->hourly();

// Synchronise l'état des comptes Stripe Connect (charges/payouts/details) :
// Stripe active souvent le compte quelques minutes après l'onboarding, de façon
// asynchrone. Sans ça, un nouveau vendeur ne peut pas encaisser en CB.
Schedule::command('stripe:sync-accounts')->hourly()->withoutOverlapping();

// Libère automatiquement les versements vendeurs en attente
// (ventes finalisées dont le vendeur a configuré son Stripe Connect après coup).
Schedule::command('payouts:release-pending')->everyFifteenMinutes()->withoutOverlapping();

// Filets de sécurité paiement : versement auto (colis expédié depuis longtemps
// sans confirmation) + signalement des ventes non expédiées à rembourser.
Schedule::command('transactions:auto-resolve')->dailyAt('07:00')->withoutOverlapping();
