<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('swapiles:cleanup-pending')->hourly();

// Relève le nombre de connectés CHAQUE MINUTE pour capter fidèlement les pics
// de visiteurs simultanés (un relevé espacé rate les pics courts).
Schedule::command('traffic:snapshot')->everyMinute()->withoutOverlapping();

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

// Rappel « N'oubliez pas votre favori » : chaque jour, on relance les membres
// dont un article favori (encore en ligne) date d'au moins une semaine. Un
// favori n'est relancé qu'une seule fois (colonne favorites.reminded_at).
Schedule::command('favorites:remind')->dailyAt('09:00')->withoutOverlapping();

// Qualité / conversion : masque les annonces publiées sans aucune photo (elles
// nuisent à la conversion sur la page recherche). Idempotent et réversible.
Schedule::command('listings:hide-photoless')->dailyAt('06:30')->withoutOverlapping();
