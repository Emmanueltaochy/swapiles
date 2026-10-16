<?php

namespace App\Support;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Badge « Répond vite » : le délai habituel (médiane) entre le premier
 * message d'un acheteur et la réponse du vendeur, sur les 60 derniers jours.
 *
 * - Une relance de l'acheteur avant la réponse ne remet pas le compteur à zéro.
 * - Un message resté sans réponse plus de 24 h compte comme 72 h : ignorer
 *   les acheteurs ne doit pas donner un bon badge.
 * - Il faut au moins 3 mesures pour afficher quelque chose.
 */
class TempsReponse
{
    public const JOURS = 60;

    public const MESURES_MIN = 3;

    public const SANS_REPONSE_MINUTES = 72 * 60;

    /** @return array{minutes: ?int, mesures: int} */
    public static function calculer(User $vendeur): array
    {
        $messages = Message::query()
            ->where('created_at', '>=', now()->subDays(self::JOURS))
            ->where(fn ($query) => $query->where('sender_id', $vendeur->id)->orWhere('receiver_id', $vendeur->id))
            ->orderBy('created_at')
            ->get(['listing_id', 'sender_id', 'receiver_id', 'created_at']);

        $delais = [];
        foreach (self::parConversation($messages, $vendeur->id) as $fil) {
            $enAttente = null;
            foreach ($fil as $message) {
                if ($message->receiver_id === $vendeur->id) {
                    $enAttente ??= $message->created_at;
                } elseif ($enAttente) {
                    $delais[] = max(0, (int) round($enAttente->diffInSeconds($message->created_at) / 60));
                    $enAttente = null;
                }
            }
            if ($enAttente && $enAttente->lt(now()->subDay())) {
                $delais[] = self::SANS_REPONSE_MINUTES;
            }
        }

        if (count($delais) < self::MESURES_MIN) {
            return ['minutes' => null, 'mesures' => count($delais)];
        }

        sort($delais);
        $milieu = intdiv(count($delais), 2);
        $mediane = count($delais) % 2 ? $delais[$milieu] : intdiv($delais[$milieu - 1] + $delais[$milieu], 2);

        return ['minutes' => $mediane, 'mesures' => count($delais)];
    }

    /** Une conversation = l'annonce + l'autre membre. */
    private static function parConversation(Collection $messages, int $vendeurId): Collection
    {
        return $messages->groupBy(function ($message) use ($vendeurId) {
            $autre = $message->sender_id === $vendeurId ? $message->receiver_id : $message->sender_id;

            return ($message->listing_id ?? 0) . '-' . $autre;
        });
    }

    /**
     * Libellé affiché, ou null si on n'a rien d'utile à dire.
     *
     * @return array{texte: string, rapide: bool}|null
     */
    public static function libelle(?int $minutes): ?array
    {
        return match (true) {
            $minutes === null => null,
            $minutes <= 60 => ['texte' => '⚡ Répond vite · moins d’1 h en général', 'rapide' => true],
            $minutes <= 6 * 60 => ['texte' => 'Répond en quelques heures', 'rapide' => false],
            $minutes <= 24 * 60 => ['texte' => 'Répond dans la journée', 'rapide' => false],
            default => null,
        };
    }

    /** Recalcule tous les vendeurs ayant reçu un message récemment. */
    public static function recalculerTout(): int
    {
        $ids = Message::query()
            ->where('created_at', '>=', now()->subDays(self::JOURS))
            ->distinct()
            ->pluck('receiver_id');

        $n = 0;
        User::query()->whereIn('id', $ids)->each(function (User $vendeur) use (&$n) {
            $r = self::calculer($vendeur);
            $vendeur->forceFill(['reponse_minutes' => $r['minutes'], 'reponse_mesures' => $r['mesures']])->saveQuietly();
            $n++;
        });

        // Plus de message reçu depuis 60 jours : le badge s'éteint.
        User::query()->whereNotIn('id', $ids)->whereNotNull('reponse_minutes')
            ->update(['reponse_minutes' => null, 'reponse_mesures' => 0]);

        return $n;
    }
}
