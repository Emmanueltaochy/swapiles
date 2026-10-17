<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Combien de téléphones ont l'appli Swap'Îles, et s'en servent ?
 *
 * Les téléchargements, seuls Apple et Google les connaissent (App Store
 * Connect, Play Console). Ici, on compte ce que l'appli nous dit elle-même :
 * une installation est comptée dès qu'elle est OUVERTE (public/js/app-install.js),
 * avec un identifiant anonyme tiré au hasard, sans lien avec le compte.
 */
class InstallationsAppli
{
    public const PLATEFORMES = ['ios' => 'iPhone / iPad', 'android' => 'Android'];

    /** Enregistre une ouverture de l'appli. Renvoie true pour une nouvelle installation. */
    public static function ouverture(string $installId, string $plateforme): bool
    {
        $maintenant = now();

        $maj = DB::table('app_installations')->where('install_id', $installId)->update([
            'platform' => $plateforme,
            'ouvertures' => DB::raw('ouvertures + 1'),
            'derniere_ouverture_at' => $maintenant,
        ]);

        if ($maj === 0) {
            return DB::table('app_installations')->insertOrIgnore([
                'install_id' => $installId,
                'platform' => $plateforme,
                'ouvertures' => 1,
                'premiere_ouverture_at' => $maintenant,
                'derniere_ouverture_at' => $maintenant,
            ]) > 0;
        }

        return false;
    }

    /**
     * @return array<string,array{total:int,actives_7j:int,actives_30j:int,aujourdhui:int,nouvelles_7j:int,notifications:int}>
     *   par plateforme, plus 'toutes'
     */
    public static function chiffres(): array
    {
        $chiffres = [];

        foreach ([...array_keys(self::PLATEFORMES), 'toutes'] as $p) {
            $q = fn () => DB::table('app_installations')->when($p !== 'toutes', fn ($q) => $q->where('platform', $p));

            $chiffres[$p] = [
                'total' => Schema::hasTable('app_installations') ? $q()->count() : 0,
                'actives_7j' => Schema::hasTable('app_installations') ? $q()->where('derniere_ouverture_at', '>=', now()->subDays(7))->count() : 0,
                'actives_30j' => Schema::hasTable('app_installations') ? $q()->where('derniere_ouverture_at', '>=', now()->subDays(30))->count() : 0,
                'aujourdhui' => Schema::hasTable('app_installations') ? $q()->where('derniere_ouverture_at', '>=', today())->count() : 0,
                'nouvelles_7j' => Schema::hasTable('app_installations') ? $q()->where('premiere_ouverture_at', '>=', now()->subDays(7))->count() : 0,
                // Téléphones qui reçoivent les notifications (jeton enregistré).
                'notifications' => DB::table('device_tokens')
                    ->when($p !== 'toutes', fn ($q) => $q->where('platform', $p), fn ($q) => $q->whereIn('platform', array_keys(self::PLATEFORMES)))
                    ->count(),
            ];
        }

        return $chiffres;
    }

    /**
     * Nouvelles installations ouvertes par jour, par plateforme.
     *
     * @return array{labels:array<int,string>,ios:array<int,int>,android:array<int,int>}
     */
    public static function nouvellesParJour(int $jours = 30): array
    {
        $debut = now()->subDays($jours - 1)->startOfDay();
        $lignes = Schema::hasTable('app_installations')
            ? DB::table('app_installations')
                ->where('premiere_ouverture_at', '>=', $debut)
                ->selectRaw('DATE(premiere_ouverture_at) as jour, platform, COUNT(*) as total')
                ->groupBy('jour', 'platform')
                ->get()
            : collect();

        $serie = ['labels' => [], 'ios' => [], 'android' => []];
        for ($i = 0; $i < $jours; $i++) {
            $jour = $debut->copy()->addDays($i);
            $serie['labels'][] = $jour->format('d/m');
            foreach (['ios', 'android'] as $p) {
                $serie[$p][] = (int) $lignes->first(fn ($l) => substr((string) $l->jour, 0, 10) === $jour->toDateString() && $l->platform === $p)?->total;
            }
        }

        return $serie;
    }

    /** Membres connectés qui ont utilisé l'appli (repère, sans plateforme). */
    public static function membresDansLAppli(): int
    {
        return DB::table('users')->whereNotNull('appli_vue_at')->count();
    }

    public static function debutComptage(): ?Carbon
    {
        $min = Schema::hasTable('app_installations') ? DB::table('app_installations')->min('premiere_ouverture_at') : null;

        return $min ? Carbon::parse($min) : null;
    }

    /**
     * Clics sur le lien unique swapiles.com/app, 30 derniers jours.
     *
     * @return array{total:int,par_plateforme:array<string,int>,par_source:array<string,int>}
     */
    public static function clicsLien(int $jours = 30): array
    {
        if (! Schema::hasTable('app_lien_clics')) {
            return ['total' => 0, 'par_plateforme' => [], 'par_source' => []];
        }

        $base = DB::table('app_lien_clics')->where('created_at', '>=', now()->subDays($jours));

        return [
            'total' => (clone $base)->count(),
            'par_plateforme' => (clone $base)->selectRaw('plateforme, COUNT(*) as n')->groupBy('plateforme')->pluck('n', 'plateforme')->map(fn ($n) => (int) $n)->all(),
            'par_source' => (clone $base)->selectRaw("COALESCE(source, 'sans précision') as s, COUNT(*) as n")->groupBy('s')->orderByDesc('n')->pluck('n', 's')->map(fn ($n) => (int) $n)->all(),
        ];
    }
}
