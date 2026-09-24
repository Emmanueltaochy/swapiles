<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Règles d'envoi des notifications push.
 *
 * Trois garde-fous, dans l'ordre où ils s'appliquent :
 *
 * 1. HEURES DE SILENCE, calculées dans le fuseau du MEMBRE.
 *    Le serveur tourne à l'heure de La Réunion, mais les îles s'étalent sur
 *    8 heures de décalage : quand il est 9 h à Saint-Denis, il est 1 h du
 *    matin à Fort-de-France. Une notification d'animation envoyée le matin
 *    réveillait donc les Antillais en pleine nuit. Elle est désormais
 *    DIFFÉRÉE au réveil, pas supprimée.
 *
 * 2. PLAFOND QUOTIDIEN, par niveau d'importance. Au-delà, la notification
 *    reste consultable dans l'app mais ne fait plus sonner le téléphone.
 *
 * 3. Les notifications liées à l'ARGENT ou à la SÉCURITÉ du compte échappent
 *    aux deux : une vente ou un remboursement part immédiatement.
 */
class PushPolicy
{
    /** Fuseau horaire réel de chaque île. */
    public const FUSEAUX = [
        'La Réunion' => 'Indian/Reunion',      // UTC+4
        'Mayotte' => 'Indian/Mayotte',         // UTC+3
        'Guyane' => 'America/Cayenne',         // UTC−3
        'Martinique' => 'America/Martinique',  // UTC−4
        'Guadeloupe' => 'America/Guadeloupe',  // UTC−4
    ];

    public const FUSEAU_DEFAUT = 'Indian/Reunion';

    /**
     * Niveaux d'importance.
     *   transactionnel : argent, sécurité — jamais retenu, jamais plafonné
     *   social         : un humain attend une réponse — plafond large
     *   animation      : incitation — plafond serré
     */
    public const NIVEAUX = [
        'social' => ['message_received', 'exchange_proposal', 'listing_interest'],
        'animation' => [
            'favorite_added',
            'seller_published_listing',
            'listing_available_colissimo',
            'listing_needs_photo',
            'account_onboarding',
        ],
    ];

    /** Niveau d'un type de notification. */
    public static function niveau(?string $type): string
    {
        foreach (self::NIVEAUX as $niveau => $types) {
            if ($type !== null && in_array($type, $types, true)) {
                return $niveau;
            }
        }

        // Tout le reste (ventes, offres, modération) et les types inconnus :
        // on ne retient jamais quelque chose qu'on ne sait pas classer.
        return 'transactionnel';
    }

    /** Fuseau horaire du membre, d'après son île. */
    public static function fuseau(?User $user): string
    {
        return self::FUSEAUX[$user?->territoire] ?? self::FUSEAU_DEFAUT;
    }

    /**
     * Décide du sort d'une notification push.
     *
     * @return array{action: 'envoyer'|'differer'|'ignorer', envoi_a: ?CarbonInterface, raison: ?string}
     */
    public static function decider(?User $user, ?string $type, ?CarbonInterface $maintenant = null): array
    {
        $niveau = self::niveau($type);
        $maintenant ??= Carbon::now();

        if ($niveau === 'transactionnel') {
            return ['action' => 'envoyer', 'envoi_a' => null, 'raison' => null];
        }

        // --- Plafond quotidien -------------------------------------------
        $plafond = (int) config('push.plafonds.' . $niveau, $niveau === 'social' ? 10 : 3);

        if ($user && $plafond > 0) {
            $locale = $maintenant->copy()->setTimezone(self::fuseau($user));
            $cle = 'push_cap:' . $user->id . ':' . $niveau . ':' . $locale->toDateString();

            Cache::add($cle, 0, $locale->copy()->endOfDay()->addHours(6));

            if (Cache::increment($cle) > $plafond) {
                return [
                    'action' => 'ignorer',
                    'envoi_a' => null,
                    'raison' => 'plafond quotidien atteint (' . $plafond . ')',
                ];
            }
        }

        // --- Heures de silence, dans le fuseau du membre -------------------
        $reveil = self::prochainCreneau($user, $maintenant);

        if ($reveil !== null) {
            return [
                'action' => 'differer',
                'envoi_a' => $reveil,
                'raison' => 'heures de silence chez le membre',
            ];
        }

        return ['action' => 'envoyer', 'envoi_a' => null, 'raison' => null];
    }

    /**
     * Si l'on est dans les heures de silence du membre, renvoie le moment du
     * prochain créneau autorisé. Sinon null.
     */
    public static function prochainCreneau(?User $user, ?CarbonInterface $maintenant = null): ?CarbonInterface
    {
        $debut = (int) config('push.silence.debut', 22);
        $fin = (int) config('push.silence.fin', 8);

        $maintenant ??= Carbon::now();
        $locale = $maintenant->copy()->setTimezone(self::fuseau($user));
        $heure = (int) $locale->format('G');

        $enSilence = $debut > $fin
            ? ($heure >= $debut || $heure < $fin)   // plage qui passe minuit
            : ($heure >= $debut && $heure < $fin);

        if (! $enSilence) {
            return null;
        }

        $reveil = $locale->copy()->setTime($fin, 0);

        // Après minuit, le réveil est le matin même ; avant minuit, le lendemain.
        if ($reveil->lessThanOrEqualTo($locale)) {
            $reveil->addDay();
        }

        return $reveil;
    }
}
