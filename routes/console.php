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

// Relance vendeur : un message resté sans réponse depuis 24 h déclenche un
// e-mail au destinataire (point 8). Une seule relance par message.
Schedule::command('messages:remind-unanswered')->everySixHours()->withoutOverlapping();

// Ménage des jetons d'appareil (notifications push). Un jeton change à chaque
// réinstallation : sans ménage, le nombre d'appareils affiché n'a plus rien à
// voir avec le nombre d'installations réelles.
Schedule::command('push:cleanup-tokens')->weeklyOn(1, '04:00')->withoutOverlapping();

// « Recommandé pour vous » : la commande tourne chaque heure mais n'envoie
// qu'aux membres pour qui il est 18 h–21 h sur leur île, au plus tous les
// 4 jours, et seulement si la sélection est pertinente (config/recommandations.php).
Schedule::command('recommandations:envoyer')->hourlyAt(5)->withoutOverlapping();

// Ventes en cours : « pense à expédier », « tu as reçu ton colis ? »,
// « ton colis t'attend au point relais ». Deux relances au plus par sujet ;
// la nuit, elles attendent le matin chez le membre (PushPolicy).
Schedule::command('ventes:relances')->hourlyAt(20)->withoutOverlapping();

// Ventes en direct : « le live commence dans 15 min » aux membres qui ont
// appuyé sur « Me prévenir ». Une seule fois par live.
Schedule::command('live:rappels')->everyMinute()->withoutOverlapping();

// Replays supprimés par leur vendeur : la vidéo est effacée du stockage
// (plusieurs passages, LiveKit pouvant finir l'envoi après la fin du live).
Schedule::command('live:nettoyer-replays')->everyTenMinutes()->withoutOverlapping();

// Parrainage : purge RGPD des invitations (7 jours), expiration des crédits,
// rappel « ton crédit expire dans 7 jours ».
Schedule::command('parrainage:entretien')->dailyAt('09:12')->withoutOverlapping();
