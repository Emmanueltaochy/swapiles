<?php

namespace App\Support;

use App\Models\RelayPoint;
use App\Models\RelayVersement;
use App\Models\Transaction;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Commissions des points relais : relevé du mois (autofacture quand le
 * commerce a donné son SIRET), versement mensuel par virement et soldes.
 * La commission est due au mois de la REMISE du colis (à l'acheteur, ou au
 * vendeur après le délai de garde).
 */
class VersementsRelais
{
    /** Colis rémunérés d'un point relais pour un mois. */
    public static function colisDuMois(RelayPoint $point, Carbon $mois): Collection
    {
        $debut = $mois->copy()->startOfMonth();
        $fin = $debut->copy()->addMonth();

        return Transaction::query()
            ->where('relay_point_id', $point->id)
            ->whereIn('relay_status', RelayPoint::STATUTS_REMUNERES)
            ->where('relay_merchant_fee', '>', 0)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('relay_status', 'collected')->where('relay_collected_at', '>=', $debut)->where('relay_collected_at', '<', $fin))
                ->orWhere(fn ($q) => $q->where('relay_status', 'returned')->where('relay_returned_at', '>=', $debut)->where('relay_returned_at', '<', $fin)))
            ->with(['listing' => fn ($q) => $q->withTrashed()->select('id', 'title')])
            ->orderBy('id')
            ->get();
    }

    /** @return array<string,mixed> */
    public static function releve(RelayPoint $point, Carbon $mois): array
    {
        $mois = $mois->copy()->startOfMonth();
        $colis = self::colisDuMois($point, $mois);
        $total = round((float) $colis->sum('relay_merchant_fee'), 2);
        $taux = $point->assujetti_tva ? (float) (config('relais.taux_tva')[$point->territoire] ?? 0) : 0.0;
        // La commission est un montant TTC : on en déduit la part de TVA.
        $ht = $taux > 0 ? round($total / (1 + $taux / 100), 2) : $total;

        return [
            'point' => $point,
            'mois' => $mois,
            'numero' => self::numero($point, $mois),
            'autofacture' => filled($point->siret) && $point->charte_acceptee_at !== null,
            'lignes' => $colis->map(fn (Transaction $t) => [
                'date' => $t->relay_status === 'returned' ? $t->relay_returned_at : $t->relay_collected_at,
                'reference' => 'Vente n° ' . $t->id,
                'article' => $t->listing?->title ?? 'Article',
                'remise' => $t->relay_status === 'returned' ? 'Rendu au vendeur' : 'Remis à l’acheteur',
                'montant' => (float) $t->relay_merchant_fee,
            ])->all(),
            'colis' => $colis->count(),
            'total' => $total,
            'ht' => $ht,
            'tva' => round($total - $ht, 2),
            'taux_tva' => $taux,
            'versement' => RelayVersement::where('relay_point_id', $point->id)->whereDate('mois', $mois->toDateString())->first(),
        ];
    }

    public static function numero(RelayPoint $point, Carbon $mois): string
    {
        return 'SWP-R' . $point->id . '-' . $mois->format('Ym');
    }

    /** Fige le relevé du mois (colis, montant, numéro) sans toucher au versement déjà noté. */
    public static function enregistrer(RelayPoint $point, Carbon $mois): RelayVersement
    {
        $r = self::releve($point, $mois);

        // whereDate : la colonne « mois » peut être stockée avec l'heure selon la base.
        $versement = RelayVersement::where('relay_point_id', $point->id)->whereDate('mois', $r['mois']->toDateString())->first()
            ?? new RelayVersement(['relay_point_id' => $point->id, 'mois' => $r['mois']->toDateString()]);
        $versement->fill(['colis' => $r['colis'], 'montant' => $r['total'], 'numero' => $r['numero']])->save();

        return $versement;
    }

    /**
     * Note le virement du mois. Une retenue (colis perdu par la faute du
     * commerçant, voir la charte) est déduite du virement, jamais au-delà des
     * commissions du mois : le reste se reporte sur un mois suivant.
     */
    public static function marquerVerse(RelayPoint $point, Carbon $mois, ?string $reference = null, float $retenue = 0, ?string $motif = null): RelayVersement
    {
        $versement = self::enregistrer($point, $mois);
        $retenue = round(min(max(0, $retenue), (float) $versement->montant), 2);
        $versement->fill([
            'verse_at' => now(),
            'reference' => $reference ?: null,
            'retenue' => $retenue,
            'retenue_motif' => $retenue > 0 ? ($motif ?: 'Colis perdu ou remis sans code') : null,
        ])->save();

        return $versement;
    }

    /**
     * Soldes d'un ou plusieurs points : gagné depuis le début, déjà versé, reste à verser.
     *
     * @param  iterable<int>|int  $pointIds
     * @return array{gagne:float,verse:float,retenues:float,a_verser:float}
     */
    public static function soldes(iterable|int $pointIds): array
    {
        $ids = collect(is_int($pointIds) ? [$pointIds] : $pointIds)->all();

        $gagne = (float) Transaction::whereIn('relay_point_id', $ids)
            ->whereIn('relay_status', RelayPoint::STATUTS_REMUNERES)
            ->sum('relay_merchant_fee');
        // Les commissions d'un mois versé sont soldées, retenue comprise.
        $regle = (float) RelayVersement::whereIn('relay_point_id', $ids)->whereNotNull('verse_at')->sum('montant');
        $retenues = (float) RelayVersement::whereIn('relay_point_id', $ids)->whereNotNull('verse_at')->sum('retenue');

        return [
            'gagne' => round($gagne, 2),
            'verse' => round($regle - $retenues, 2),
            'retenues' => round($retenues, 2),
            'a_verser' => round(max(0, $gagne - $regle), 2),
        ];
    }

    /**
     * Les mois qui ont des colis rémunérés, du plus récent au plus ancien.
     *
     * @return array<int,Carbon>
     */
    public static function moisAvecColis(RelayPoint $point): array
    {
        return Transaction::query()
            ->where('relay_point_id', $point->id)
            ->whereIn('relay_status', RelayPoint::STATUTS_REMUNERES)
            ->where('relay_merchant_fee', '>', 0)
            ->get(['relay_status', 'relay_collected_at', 'relay_returned_at'])
            ->map(fn ($t) => Carbon::parse($t->relay_status === 'returned' ? $t->relay_returned_at : $t->relay_collected_at)->startOfMonth())
            ->unique(fn ($m) => $m->format('Y-m'))
            ->sortByDesc(fn ($m) => $m->timestamp)
            ->values()
            ->all();
    }

    public static function pdf(RelayPoint $point, Carbon $mois): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->setChroot([public_path(), base_path('vendor/dompdf/dompdf')]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.releve-relais', self::releve($point, $mois) + [
            'logo' => public_path('images/logo.png'),
            'contact' => config('partenaires.contact'),
        ])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    public static function nomFichier(RelayPoint $point, Carbon $mois): string
    {
        return 'swapiles-releve-relais-' . self::numero($point, $mois) . '.pdf';
    }
}
