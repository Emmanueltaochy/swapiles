<?php

namespace App\Support;

use App\Models\PushEnvoi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Suivi des notifications push : journal des envois et des clics.
 *
 * Le clic est repéré par un petit paramètre « pn » ajouté au lien de la
 * notification (/annonce/12?pn=345). Quand le membre touche la notification,
 * l'appli ouvre ce lien : le site note le clic (qui, sur quel téléphone),
 * puis affiche la page demandée sans le paramètre.
 */
class PushSuivi
{
    public const PARAMETRE = 'pn';

    /** Libellés des types de notification. */
    public const TYPES = [
        'diffusion' => 'Envoi manuel (admin)',
        'message_received' => 'Message reçu',
        'message_reminder' => 'Relance : message sans réponse',
        'exchange_proposal' => 'Proposition d’échange',
        'listing_interest' => 'Demande de livraison',
        'offer_accepted' => 'Offre acceptée',
        'offer_declined' => 'Offre refusée',
        'transaction_paid_buyer' => 'Achat payé (acheteur)',
        'transaction_paid_seller' => 'Vente payée (vendeur)',
        'transaction_refunded' => 'Remboursement',
        'transaction_shipped' => 'Colis parti',
        'transaction_received' => 'Vente finalisée',
        'payout_sent' => 'Paiement envoyé au vendeur',
        'cash_sale_confirm' => 'Vente en main propre à confirmer',
        'relay_incoming' => 'Point relais : colis annoncé',
        'relay_ready' => 'Colis au point relais',
        'relay_collected' => 'Colis retiré',
        'relay_deposit_confirmed' => 'Dépôt confirmé au relais',
        'relay_deposit_declared' => 'Colis déposé au relais',
        'seller_ship_reminder' => 'Relance : pense à expédier',
        'buyer_confirm_reminder' => 'Relance : confirme la réception',
        'buyer_handover_reminder' => 'Relance : remise en main propre',
        'relay_pickup_reminder' => 'Relance : colis à retirer',
        'favorite_added' => 'Mise en favori',
        'favorite_price_drop' => 'Baisse de prix d’un favori',
        'favorite_reminder' => 'Rappel d’un favori',
        'listing_viewed' => 'Annonce consultée',
        'seller_published_listing' => 'Nouvel article d’un dressing suivi',
        'listing_available_colissimo' => 'Article disponible en Colissimo',
        'listing_needs_photo' => 'Annonce sans photo',
        'account_onboarding' => 'Premiers pas',
        'recommandations' => 'Recommandé pour vous',
        'top_dressing' => 'Classement des dressings',
        'moderation_warning' => 'Avertissement modération',
        'referral_joined' => 'Parrainage : un ami s’est inscrit',
        'referral_welcome' => 'Parrainage : bienvenue au filleul',
        'referral_reward' => 'Parrainage : récompense',
        'wallet_expiring' => 'Crédit qui expire bientôt',
        'live_soon' => 'Live : commence bientôt',
        'live_started' => 'Live : c’est parti (inscrits)',
        'live_started_suivi' => 'Live : c’est parti (abonnés)',
    ];

    public static function libelle(?string $type): string
    {
        return self::TYPES[$type] ?? ($type ? Str::of($type)->replace('_', ' ')->ucfirst()->toString() : '—');
    }

    /** Note un envoi dans le journal. */
    public static function journaliser(array $donnees): PushEnvoi
    {
        return PushEnvoi::create($donnees + ['origine' => 'auto']);
    }

    /**
     * Lien de la notification, avec le repère de suivi. Sans lien, la
     * notification ouvre l'accueil (et le clic est quand même compté).
     */
    public static function lien(?string $url, int $envoiId): string
    {
        $url = $url ?: '/';
        $ancre = '';

        if (str_contains($url, '#')) {
            [$url, $ancre] = explode('#', $url, 2);
            $ancre = '#' . $ancre;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . self::PARAMETRE . '=' . $envoiId . $ancre;
    }

    /** Résultat de l'envoi vers les appareils. */
    public static function resultat(?int $envoiId, int $appareils, int $ok): void
    {
        if (! $envoiId) {
            return;
        }

        PushEnvoi::whereKey($envoiId)->update([
            'statut' => $appareils === 0 ? 'sans_appareil' : ($ok > 0 ? 'envoye' : 'echec'),
            'envoye_at' => now(),
            'appareils' => $appareils,
            'appareils_ok' => $ok,
        ]);
    }

    /**
     * Bilan sur une période.
     *
     * @return array{parties:int,automatiques:int,manuelles:int,ouvertes:int,taux:?float,membres_cliqueurs:int,retenues:int}
     */
    public static function bilan($debut, $fin): array
    {
        $envois = PushEnvoi::query()->whereBetween('created_at', [$debut, $fin]);

        $parties = (clone $envois)->where('statut', 'envoye');
        $personnelles = (clone $parties)->whereNotNull('user_id');
        $nbPerso = $personnelles->count();
        $ouvertesPerso = (clone $personnelles)->where('clics', '>', 0)->count();

        return [
            'parties' => $parties->count(),
            'automatiques' => (clone $parties)->where('origine', 'auto')->count(),
            'manuelles' => (clone $parties)->where('origine', 'manuel')->count(),
            'ouvertes' => (int) DB::table('push_clics')->whereIn('push_envoi_id', (clone $envois)->select('id'))->count(),
            'taux' => $nbPerso > 0 ? round($ouvertesPerso / $nbPerso * 100, 1) : null,
            'membres_cliqueurs' => (int) DB::table('push_clics')->whereIn('push_envoi_id', (clone $envois)->select('id'))
                ->whereNotNull('user_id')->distinct()->count('user_id'),
            'retenues' => (clone $envois)->whereIn('statut', ['ignore', 'coupe'])->count(),
        ];
    }

    /**
     * Par type : parties, ouvertes, taux d'ouverture, retenues.
     *
     * @return array<int,array{type:?string,libelle:string,parties:int,ouvertes:int,taux:?float,retenues:int}>
     */
    public static function parType($debut, $fin): array
    {
        return PushEnvoi::query()->whereBetween('created_at', [$debut, $fin])
            ->selectRaw("type,
                SUM(CASE WHEN statut = 'envoye' THEN 1 ELSE 0 END) as parties,
                SUM(CASE WHEN statut = 'envoye' AND user_id IS NOT NULL AND clics > 0 THEN 1 ELSE 0 END) as ouvertes_perso,
                SUM(CASE WHEN statut = 'envoye' AND user_id IS NULL THEN clics ELSE 0 END) as clics_diffusion,
                SUM(CASE WHEN statut = 'envoye' AND user_id IS NULL THEN appareils_ok ELSE 0 END) as appareils_diffusion,
                SUM(CASE WHEN statut IN ('ignore', 'coupe') THEN 1 ELSE 0 END) as retenues")
            ->groupBy('type')
            ->get()
            ->map(function ($l) {
                $diffusion = $l->type === 'diffusion';
                $ouvertes = $diffusion ? (int) $l->clics_diffusion : (int) $l->ouvertes_perso;
                $base = $diffusion ? (int) $l->appareils_diffusion : (int) $l->parties;

                return [
                    'type' => $l->type,
                    'libelle' => self::libelle($l->type),
                    'parties' => (int) $l->parties,
                    'ouvertes' => $ouvertes,
                    'taux' => $base > 0 ? round(min(100, $ouvertes / $base * 100), 1) : null,
                    'retenues' => (int) $l->retenues,
                ];
            })
            ->sortByDesc('parties')->values()->all();
    }

    /** Clic sur une notification (une seule fois par personne). */
    public static function clic(Request $request, int $envoiId): bool
    {
        $envoi = PushEnvoi::find($envoiId);
        if (! $envoi) {
            return false;
        }

        $user = $request->user();
        $cle = $user ? 'membre-' . $user->id : 'session-' . hash('sha256', $request->session()->getId());
        $ua = mb_strtolower((string) $request->userAgent());
        $plateforme = str_contains($ua, 'iphone') || str_contains($ua, 'ipad') ? 'ios' : (str_contains($ua, 'android') ? 'android' : 'web');

        $nouveau = DB::table('push_clics')->insertOrIgnore([
            'push_envoi_id' => $envoi->id,
            'user_id' => $user?->id,
            'cle' => $cle,
            'plateforme' => $plateforme,
            'created_at' => now(),
        ]) > 0;

        if ($nouveau) {
            $envoi->increment('clics');
            if (! $envoi->premier_clic_at) {
                $envoi->forceFill(['premier_clic_at' => now()])->saveQuietly();
            }
        }

        return $nouveau;
    }
}
