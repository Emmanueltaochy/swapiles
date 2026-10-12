<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Message;
use App\Models\Report;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chiffres du tableau de bord de l'administration.
 *
 * Toutes les requêtes du tableau de bord vivent ici (et non dans les
 * widgets) : elles sont testables, et chaque chiffre a une seule définition.
 */
class TableauDeBord
{
    /** Une vente est réalisée quand le paiement est passé (puis finalisé). */
    public const STATUTS_VENDUS = ['paid', 'completed'];

    /** Périodes proposées dans le filtre du tableau de bord. */
    public const PERIODES = [
        '7j' => '7 derniers jours',
        '30j' => '30 derniers jours',
        '90j' => '3 derniers mois',
        '12m' => '12 derniers mois',
        'tout' => 'Depuis le début',
    ];

    public static function debut(?string $periode): ?Carbon
    {
        return match ($periode) {
            '7j' => now()->subDays(6)->startOfDay(),
            '90j' => now()->subDays(89)->startOfDay(),
            '12m' => now()->subMonths(12)->startOfDay(),
            'tout' => null,
            default => now()->subDays(29)->startOfDay(),
        };
    }

    /** Nombre de jours tracés sur les graphiques pour la période. */
    public static function joursGraphique(?string $periode): int
    {
        return match ($periode) {
            '7j' => 7,
            '90j' => 90,
            '12m', 'tout' => 365,
            default => 30,
        };
    }

    // --- Aujourd'hui -------------------------------------------------------

    /** Valeurs par jour des N derniers jours (aujourd'hui compris). */
    public static function serieJournaliere(callable $requete, int $jours = 14, string $colonne = 'created_at'): array
    {
        $debut = now()->subDays($jours - 1)->startOfDay();
        $parJour = $requete()
            ->where($colonne, '>=', $debut)
            ->selectRaw("DATE({$colonne}) as jour, COUNT(*) as total")
            ->groupBy('jour')
            ->pluck('total', 'jour');

        $serie = [];
        for ($i = 0; $i < $jours; $i++) {
            $jour = $debut->copy()->addDays($i)->toDateString();
            $serie[] = (int) ($parJour[$jour] ?? 0);
        }

        return $serie;
    }

    public static function inscriptions(): callable
    {
        return fn () => User::query();
    }

    public static function annonces(): callable
    {
        return fn () => Listing::query();
    }

    /**
     * Ventes payées EN LIGNE (par carte, via Swap'Îles) : les seules certaines.
     * Les ventes en main propre déclarées par le vendeur sont comptées à part.
     */
    public static function ventes(): callable
    {
        return fn () => Transaction::query()->whereIn('status', self::STATUTS_VENDUS)->enLigne();
    }

    /** Visiteurs uniques (identifiant visiteur) par jour, robots exclus à l'enregistrement. */
    public static function visiteursParJour(int $jours = 14): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return array_fill(0, $jours, 0);
        }

        $debut = now()->subDays($jours - 1)->startOfDay();
        $parJour = DB::table('analytics_events')
            ->where('created_at', '>=', $debut)
            ->selectRaw('DATE(created_at) as jour, COUNT(DISTINCT COALESCE(visitor_id, session_id)) as total')
            ->groupBy('jour')
            ->pluck('total', 'jour');

        $serie = [];
        for ($i = 0; $i < $jours; $i++) {
            $serie[] = (int) ($parJour[$debut->copy()->addDays($i)->toDateString()] ?? 0);
        }

        return $serie;
    }

    /** Évolution en % entre aujourd'hui et hier (null si hier = 0). */
    public static function evolution(array $serie): ?int
    {
        $n = count($serie);
        if ($n < 2 || $serie[$n - 2] === 0) {
            return null;
        }

        return (int) round(($serie[$n - 1] - $serie[$n - 2]) / $serie[$n - 2] * 100);
    }

    // --- À traiter ----------------------------------------------------------

    /**
     * Ce qui attend une action de l'équipe.
     *
     * @return array<string,int>
     */
    public static function aTraiter(): array
    {
        return [
            'signalements' => Schema::hasTable('reports') ? Report::where('status', 'open')->count() : 0,
            'messages_signales' => Message::query()
                ->where('flag_kind', 'payment_forced')
                ->where('flagged_at', '>=', now()->subDays(30))
                ->count(),
            'ventes_a_verifier' => Transaction::query()
                ->whereNotNull('auto_review_flagged_at')
                ->whereNotIn('status', ['completed', 'refunded', 'cancelled'])
                ->count(),
            'annonces_sans_photo' => Listing::query()->whereNotNull('photoless_hidden_at')->count(),
        ];
    }

    // --- Sur la période -----------------------------------------------------

    /** @return array<string,float|int> */
    public static function periode(?string $periode): array
    {
        $debut = self::debut($periode);
        $depuis = fn ($q) => $debut ? $q->where('created_at', '>=', $debut) : $q;

        $vendues = $depuis(Transaction::query()->whereIn('status', self::STATUTS_VENDUS));
        $ventes = (clone $vendues)->enLigne();
        $nbVentes = (clone $ventes)->count();
        $volume = (float) (clone $ventes)->sum('amount');
        // Les ventes déclarées ne rapportent rien à Swap'Îles (0 € de frais) :
        // les revenus se calculent sur toutes les ventes, sans risque de gonfler.
        // Revenus = protection + commission + part Swap'Îles des frais de point
        // relais (le reste va au commerçant) ; même règle que FicheTransaction.
        $revenus = (float) (clone $vendues)->sum('buyer_protection_fee') + (float) (clone $vendues)->sum('commission')
            + max(0.0, (float) (clone $vendues)->sum('relay_fee') - (float) (clone $vendues)->sum('relay_merchant_fee'));
        $especes = (clone $vendues)->where('payment_method', 'especes')->whereNull('acheteur_conteste_at');

        return [
            'inscriptions' => $depuis(User::query())->count(),
            'annonces' => $depuis(Listing::query())->count(),
            'annonces_en_ligne' => Listing::where('status', 'published')->count(),
            'ventes' => $nbVentes,
            'volume' => $volume,
            'revenus' => $revenus,
            'panier_moyen' => $nbVentes > 0 ? $volume / $nbVentes : 0,
            // Main propre en espèces, sur la seule parole du vendeur.
            'ventes_declarees' => (clone $especes)->count(),
            'volume_declare' => (float) (clone $especes)->sum('amount'),
            'ventes_declarees_confirmees' => (clone $especes)->whereNotNull('acheteur_confirme_at')->count(),
            'messages' => $depuis(Message::query())->count(),
        ];
    }

    /**
     * Séries pour le graphique d'évolution : jour par jour (≤ 90 jours),
     * mois par mois au-delà.
     *
     * @return array{labels:array<int,string>,inscriptions:array<int,int>,annonces:array<int,int>,ventes:array<int,int>}
     */
    public static function evolutionPeriode(?string $periode): array
    {
        $jours = self::joursGraphique($periode);

        if ($jours <= 90) {
            $labels = [];
            for ($i = $jours - 1; $i >= 0; $i--) {
                $labels[] = now()->subDays($i)->format('d/m');
            }

            return [
                'labels' => $labels,
                'inscriptions' => self::serieJournaliere(self::inscriptions(), $jours),
                'annonces' => self::serieJournaliere(self::annonces(), $jours),
                'ventes' => self::serieJournaliere(self::ventes(), $jours),
            ];
        }

        // Par mois sur 12 mois.
        $labels = [];
        $series = ['inscriptions' => [], 'annonces' => [], 'ventes' => []];
        for ($i = 11; $i >= 0; $i--) {
            $mois = now()->startOfMonth()->subMonths($i);
            $fin = $mois->copy()->endOfMonth();
            $labels[] = $mois->locale('fr')->translatedFormat('M y');
            foreach (['inscriptions' => self::inscriptions(), 'annonces' => self::annonces(), 'ventes' => self::ventes()] as $cle => $requete) {
                $series[$cle][] = $requete()->whereBetween('created_at', [$mois, $fin])->count();
            }
        }

        return ['labels' => $labels] + $series;
    }

    /** Annonces en ligne par île. */
    public static function annoncesParIle(): array
    {
        return Listing::query()
            ->where('status', 'published')
            ->selectRaw("COALESCE(NULLIF(territoire, ''), 'Non renseigné') as ile, COUNT(*) as total")
            ->groupBy('ile')
            ->orderByDesc('total')
            ->pluck('total', 'ile')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    public static function euros(float $montant): string
    {
        return number_format($montant, $montant >= 1000 ? 0 : 2, ',', ' ') . ' €';
    }
}
