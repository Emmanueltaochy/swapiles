<?php

namespace App\Support;

use App\Models\ExchangeProposal;
use App\Models\ListingOffer;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Suivi des négociations (offres de prix) et des propositions d'échange
 * pour l'administration : où en est chaque deal.
 */
class Negociations
{
    public const ETATS_OFFRE = [
        'en_attente' => '⏳ En attente du vendeur',
        'acceptee' => '✅ Acceptée, pas encore payée',
        'payee' => '💳 Payée',
        'expiree' => '⌛ Expirée',
        'refusee' => '❌ Refusée',
    ];

    public const ORIGINES = [
        'acheteur' => '💬 Offre de l’acheteur',
        'contre_offre' => '↩️ Contre-offre du vendeur',
        'favoris' => '🎁 Offre aux fans',
    ];

    public const ETATS_ECHANGE = [
        'en_attente' => '⏳ En attente du vendeur',
        'acceptee' => '🤝 Acceptée',
        'conclu' => '✅ Échange conclu',
        'refusee' => '❌ Refusée',
    ];

    /** Ventes payées issues d'une offre. */
    private static function ventesPayees()
    {
        return Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->whereNotNull('listing_offer_id')->select('listing_offer_id');
    }

    public static function etatOffre(ListingOffer $offre): string
    {
        if (in_array($offre->status, ['refused', 'declined'], true)) {
            return 'refusee';
        }
        if ($offre->status === 'pending') {
            return 'en_attente';
        }
        if (Transaction::where('listing_offer_id', $offre->id)->whereIn('status', TableauDeBord::STATUTS_VENDUS)->exists()) {
            return 'payee';
        }

        return $offre->expire_at && $offre->expire_at->isPast() ? 'expiree' : 'acceptee';
    }

    public static function filtrerOffres(Builder $query, ?string $etat): Builder
    {
        return match ($etat) {
            'refusee' => $query->whereIn('status', ['refused', 'declined']),
            'en_attente' => $query->where('status', 'pending'),
            'payee' => $query->where('status', 'accepted')->whereIn('id', self::ventesPayees()),
            'expiree' => $query->where('status', 'accepted')->whereNotIn('id', self::ventesPayees())
                ->whereNotNull('expire_at')->where('expire_at', '<=', now()),
            'acceptee' => $query->where('status', 'accepted')->whereNotIn('id', self::ventesPayees())->valide(),
            default => $query,
        };
    }

    public static function origine(ListingOffer $offre): string
    {
        return in_array($offre->source, ['favoris', 'contre_offre'], true) ? $offre->source : 'acheteur';
    }

    public static function filtrerOrigine(Builder $query, ?string $origine): Builder
    {
        return match ($origine) {
            'favoris', 'contre_offre' => $query->where('source', $origine),
            'acheteur' => $query->where(fn ($q) => $q->whereNull('source')->orWhereNotIn('source', ['favoris', 'contre_offre'])),
            default => $query,
        };
    }

    /** Remise par rapport au prix affiché, en % (null si inconnu). */
    public static function remise(ListingOffer $offre): ?int
    {
        $prix = (float) ($offre->listing?->price ?? 0);

        return $prix > 0 ? (int) round((1 - (float) $offre->amount / $prix) * 100) : null;
    }

    /**
     * Bilan des offres des 30 derniers jours.
     *
     * @return array{offres:int,en_attente:int,acceptees:int,payees:int,taux_acceptation:?int,taux_paiement:?int,remise_moyenne:?int,volume_paye:float}
     */
    public static function bilanOffres(int $jours = 30): array
    {
        $base = ListingOffer::query()->where('created_at', '>=', now()->subDays($jours - 1)->startOfDay());
        // Les offres envoyées par le vendeur (fans, contre-offres) sont acceptées d'office :
        // le taux d'acceptation ne regarde que les offres des acheteurs.
        $acheteurs = self::filtrerOrigine(clone $base, 'acheteur');
        $decidees = (clone $acheteurs)->whereIn('status', ['accepted', 'refused', 'declined'])->count();
        $acceptees = (clone $acheteurs)->where('status', 'accepted')->count();
        $toutesAcceptees = (clone $base)->where('status', 'accepted')->count();
        $payees = (clone $base)->whereIn('id', self::ventesPayees())->count();

        $remises = (clone $base)->with('listing:id,price')->get(['id', 'listing_id', 'amount'])
            ->map(fn (ListingOffer $o) => self::remise($o))->filter(fn ($r) => $r !== null);

        return [
            'offres' => (clone $base)->count(),
            'en_attente' => ListingOffer::where('status', 'pending')->count(),
            'acceptees' => $acceptees,
            'payees' => $payees,
            'taux_acceptation' => $decidees > 0 ? (int) round($acceptees / $decidees * 100) : null,
            'taux_paiement' => $toutesAcceptees > 0 ? (int) round($payees / $toutesAcceptees * 100) : null,
            'remise_moyenne' => $remises->isNotEmpty() ? (int) round($remises->avg()) : null,
            'volume_paye' => (float) Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)
                ->whereIn('listing_offer_id', (clone $base)->select('id'))->sum('amount'),
        ];
    }

    /** L'échange a-t-il été déclaré conclu (vente « échange » entre les deux membres) ? */
    private static function echangesConclus()
    {
        return Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->where('payment_method', 'echange');
    }

    public static function etatEchange(ExchangeProposal $echange): string
    {
        if ($echange->status === 'refused') {
            return 'refusee';
        }
        if ($echange->status === 'pending') {
            return 'en_attente';
        }

        return self::echangesConclus()->where('listing_id', $echange->listing_id)->where('buyer_id', $echange->proposer_id)->exists()
            ? 'conclu' : 'acceptee';
    }

    public static function filtrerEchanges(Builder $query, ?string $etat): Builder
    {
        $conclu = fn ($q) => $q->selectRaw('1')->from('transactions')
            ->whereColumn('transactions.listing_id', 'exchange_proposals.listing_id')
            ->whereColumn('transactions.buyer_id', 'exchange_proposals.proposer_id')
            ->where('transactions.payment_method', 'echange')
            ->whereIn('transactions.status', TableauDeBord::STATUTS_VENDUS);

        return match ($etat) {
            'refusee' => $query->where('status', 'refused'),
            'en_attente' => $query->where('status', 'pending'),
            'conclu' => $query->where('status', 'accepted')->whereExists($conclu),
            'acceptee' => $query->where('status', 'accepted')->whereNotExists($conclu),
            default => $query,
        };
    }

    /** @return array{propositions:int,en_attente:int,acceptees:int,conclus:int,taux_acceptation:?int} */
    public static function bilanEchanges(int $jours = 30): array
    {
        $base = ExchangeProposal::query()->where('created_at', '>=', now()->subDays($jours - 1)->startOfDay());
        $decidees = (clone $base)->whereIn('status', ['accepted', 'refused'])->count();
        $acceptees = (clone $base)->where('status', 'accepted')->count();

        return [
            'propositions' => (clone $base)->count(),
            'en_attente' => ExchangeProposal::where('status', 'pending')->count(),
            'acceptees' => $acceptees,
            'conclus' => self::filtrerEchanges(clone $base, 'conclu')->count(),
            'taux_acceptation' => $decidees > 0 ? (int) round($acceptees / $decidees * 100) : null,
        ];
    }
}
