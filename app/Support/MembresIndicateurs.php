<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        return [
            'total' => $total,
            'nouveaux_7j' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'nouveaux_30j' => User::where('created_at', '>=', $depuis30)->count(),
            'par_ile' => $parIle,
            'comment_connu' => $connus,
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
}
