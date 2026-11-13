<?php

namespace App\Support;

use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Agent\Alerte;

/**
 * Sondage « Aimeriez-vous faire du live shopping ? » et demandes d'accès
 * aux lives (organiser). Emmanuel accepte ou refuse dans l'admin.
 */
class InteretLive
{
    public const CHOIX = [
        'regarder' => 'Oui, regarder et acheter',
        'organiser' => 'Oui, organiser mes lives',
        'les_deux' => 'Les deux',
        'non' => 'Pas pour l’instant',
    ];

    /** Revenir demander à ceux qui ont répondu « pas pour l'instant » après N jours. */
    public const RELANCE_NON_JOURS = 60;

    /** La carte du sondage s'affiche-t-elle pour ce membre ? */
    public static function aDemander(?User $u): bool
    {
        if (! $u || $u->is_banned || LiveAcces::peutAnimer($u)) {
            return false;
        }
        if ($u->live_interet === null) {
            return true;
        }

        return $u->live_interet === 'non' && $u->live_interet_at && $u->live_interet_at->lt(now()->subDays(self::RELANCE_NON_JOURS));
    }

    /** Peut-il encore demander à organiser des lives ? */
    public static function peutDemander(?User $u): bool
    {
        return $u && ! $u->is_banned && ! LiveAcces::peutAnimer($u) && $u->live_demande_statut !== 'en_attente';
    }

    public static function repondre(User $u, string $choix): void
    {
        $u->forceFill(['live_interet' => $choix, 'live_interet_at' => now()])->save();
    }

    public static function demander(User $u, ?string $message): void
    {
        $u->forceFill([
            'live_demande_statut' => 'en_attente',
            'live_demande_at' => now(),
            'live_demande_message' => $message !== null && trim($message) !== '' ? mb_substr(trim($message), 0, 500) : null,
        ])->save();

        $p = self::profil($u);
        Alerte::envoyer(
            '🔴 Demande pour faire des lives : ' . $u->name,
            implode("\n", array_filter([
                'Membre : ' . $u->name . ($u->territoire ? ' (' . $u->territoire . ')' : '') . ($p['pro'] ? ' — compte Pro' : ' — particulier'),
                $p['ventes'] . ' vente(s) · ' . $p['annonces'] . ' annonce(s) en ligne' . ($p['note'] ? ' · note ' . $p['note'] . '/5 (' . $p['avis'] . ' avis)' : ' · pas encore d’avis'),
                $u->live_demande_message ? 'Ce qu’il veut vendre en live : « ' . $u->live_demande_message . ' »' : null,
                '',
                'Accepter ou refuser : admin › Demandes de lives.',
            ], fn ($l) => $l !== null)),
            url('/admin/demandes-lives'),
            'live_demande'
        );
    }

    /** Ce qui aide à décider (sans donnée sensible). */
    public static function profil(User $u): array
    {
        $avis = Review::where('reviewed_id', $u->id);

        return [
            'pro' => (bool) ($u->pro_valide_at ?? false),
            'ventes' => Transaction::where('seller_id', $u->id)->whereIn('status', ['paid', 'completed'])->count(),
            'annonces' => $u->listings()->where('status', 'published')->count(),
            'avis' => (clone $avis)->count(),
            'note' => ($n = (clone $avis)->avg('rating')) ? round((float) $n, 1) : null,
        ];
    }

    public static function accepter(User $u): void
    {
        $u->forceFill(['live_autorise' => true, 'live_demande_statut' => 'acceptee'])->save();
        \App\Models\Notification::create([
            'user_id' => $u->id, 'type' => 'live_demande_acceptee',
            'title' => '🎉 Tu peux faire des lives sur Swap’Îles !',
            'message' => 'Ta demande est acceptée : programme ton premier live et présente tes articles en direct.',
            'url' => route('live.create', absolute: false),
        ]);
    }

    public static function refuser(User $u): void
    {
        $u->forceFill(['live_demande_statut' => 'refusee'])->save();
        \App\Models\Notification::create([
            'user_id' => $u->id, 'type' => 'live_demande_refusee',
            'title' => 'Lives Swap’Îles : pas encore cette fois',
            'message' => 'Merci pour ta demande ! Les lives ouvrent petit à petit : continue à vendre et à recevoir de bons avis, nous reviendrons vers toi.',
            'url' => route('account.dashboard', absolute: false),
        ]);
    }
}
