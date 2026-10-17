<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Indicateurs de la page Membres de l'admin (chiffres globaux, anonymes). */
class MembresIndicateurs
{
    /** @return array<string,mixed> */
    public static function chiffres(): array
    {
        $total = User::count();
        $depuis30 = now()->subDays(30);

        $parIle = User::query()
            ->selectRaw("COALESCE(NULLIF(territoire, ''), 'Non renseignée') as ile, COUNT(*) as membres, SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as nouveaux", [$depuis30])
            ->groupBy('ile')
            ->orderByDesc('membres')
            ->orderBy('ile')
            ->get()
            ->map(fn ($l) => ['ile' => $l->ile, 'membres' => (int) $l->membres, 'nouveaux' => (int) $l->nouveaux])
            ->all();

        $connus = User::query()
            ->whereNotNull('comment_connu')->where('comment_connu', '!=', '')
            ->selectRaw('comment_connu as source, COUNT(*) as n')
            ->groupBy('source')->orderByDesc('n')
            ->pluck('n', 'source')->map(fn ($n) => (int) $n)->all();
        $sources = self::regrouperSources($connus);

        return [
            'total' => $total,
            'nouveaux_7j' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'nouveaux_30j' => User::where('created_at', '>=', $depuis30)->count(),
            'par_ile' => $parIle,
            'comment_connu' => $sources['canaux'],
            'comment_connu_reseaux' => $sources['reseaux'],
            'comment_connu_reseaux_precise' => $sources['reseaux_precise'],
            'comment_connu_autres' => $sources['autres'],
            'comment_connu_repondu' => array_sum($connus),

            // Du membre au vendeur.
            'vendeurs' => Listing::where('status', 'published')->distinct()->count('user_id'),
            'ont_vendu' => Transaction::whereIn('status', TableauDeBord::STATUTS_VENDUS)->distinct()->count('seller_id'),
            'ont_achete' => Transaction::whereIn('status', TableauDeBord::STATUTS_VENDUS)->enLigne()->distinct()->count('buyer_id'),
            'paiement_actif' => User::where('stripe_charges_enabled', true)->count(),

            // Engagement.
            'actifs_30j' => Schema::hasTable('visiteurs_jours')
                ? (int) DB::table('visiteurs_jours')->where('jour', '>=', $depuis30->toDateString())->whereNotNull('user_id')->distinct()->count('user_id')
                : 0,
            'appli' => User::whereNotNull('appli_vue_at')->count(),
            'notifications' => Schema::hasTable('device_tokens') ? (int) DB::table('device_tokens')->whereNotNull('user_id')->distinct()->count('user_id') : 0,

            // À surveiller.
            'email_non_verifie' => User::whereNull('email_verified_at')->count(),
            'en_vacances' => Schema::hasColumn('users', 'vacances_jusqu_au') ? User::where('vacances_jusqu_au', '>=', today())->count() : 0,
            'pros' => User::where('is_pro', true)->count(),
            'bannis' => User::where('is_banned', true)->count(),
        ];
    }

    /**
     * Regroupe les réponses « Comment nous avez-vous connu ? » par canal.
     * « Réseaux sociaux : Instagram, Facebook » compte une fois dans le canal
     * Réseaux sociaux, et une fois pour chaque réseau dans le détail.
     * « Autre : xxx » compte dans Autre, la précision va dans le détail.
     *
     * @param  array<string,int>  $connus
     * @return array{canaux:array<string,int>,reseaux:array<string,int>,reseaux_precise:int,autres:array<string,int>}
     */
    public static function regrouperSources(array $connus): array
    {
        $canaux = [];
        $reseaux = [];
        $reseauxPrecise = 0;
        $autres = [];

        foreach ($connus as $source => $nb) {
            $source = trim((string) $source);

            if (str_starts_with($source, 'Réseaux sociaux')) {
                $canaux['Réseaux sociaux'] = ($canaux['Réseaux sociaux'] ?? 0) + $nb;
                if (str_starts_with($source, 'Réseaux sociaux : ')) {
                    $reseauxPrecise += $nb;
                    foreach (explode(',', Str::after($source, 'Réseaux sociaux : ')) as $reseau) {
                        $reseau = trim($reseau);
                        if ($reseau !== '') {
                            $reseaux[$reseau] = ($reseaux[$reseau] ?? 0) + $nb;
                        }
                    }
                }
            } elseif ($source === 'Autre' || str_starts_with($source, 'Autre :')) {
                $canaux['Autre'] = ($canaux['Autre'] ?? 0) + $nb;
                $precision = trim(Str::after($source, 'Autre :'));
                if ($source !== 'Autre' && $precision !== '') {
                    $precision = Str::ucfirst($precision);
                    $autres[$precision] = ($autres[$precision] ?? 0) + $nb;
                }
            } else {
                $canaux[$source] = ($canaux[$source] ?? 0) + $nb;
            }
        }

        arsort($canaux);
        arsort($reseaux);
        arsort($autres);

        return ['canaux' => $canaux, 'reseaux' => $reseaux, 'reseaux_precise' => $reseauxPrecise, 'autres' => $autres];
    }
}
