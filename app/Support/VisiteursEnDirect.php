<?php

namespace App\Support;

use App\Models\LiveVisit;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Qui est sur Swap'Îles en ce moment ?
 *
 * On ne compte comme « en ligne » que les visiteurs dont le navigateur a
 * confirmé sa présence (public/js/presence.js) : la page a vraiment été
 * ouverte et quelqu'un l'a touchée. Le reste du trafic vu par le serveur
 * (robots qui se font passer pour des navigateurs, vérification des
 * publicités par Meta, aperçus de liens) est montré à part, comme
 * « non confirmé », au lieu d'être mélangé aux humains.
 */
class VisiteursEnDirect
{
    /** Battement toutes les 45 s : 3 minutes sans nouvelles = parti. */
    public const FENETRE_MINUTES = 3;

    /** Fenêtre du trafic brut côté serveur (live_visits). */
    public const FENETRE_SERVEUR_MINUTES = 5;

    /** Au-delà, l'historique des présences est effacé. */
    public const CONSERVATION_JOURS = 30;

    public const SOURCES = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'google' => 'Google',
        'tiktok' => 'TikTok',
        'snapchat' => 'Snapchat',
        'moteur' => 'Autre moteur de recherche',
        'site' => 'Autre site',
        'direct' => 'Accès direct',
    ];

    public const APPAREILS = [
        'appli' => "Dans l'appli",
        'mobile' => 'Site sur mobile',
        'tablette' => 'Site sur tablette',
        'ordinateur' => 'Site sur ordinateur',
    ];

    // --- Enregistrement ---------------------------------------------------

    /**
     * Battement envoyé par le navigateur.
     *
     * @param  array{path?:?string,appli?:bool,utm?:?string,ref?:?string,page?:bool}  $donnees
     */
    public static function enregistrer(Request $request, array $donnees): ?Presence
    {
        if (BotDetector::isBot($request->userAgent())) {
            return null;
        }

        // Identifiant visiteur stable (cookie swp_vid) : il survit à la
        // connexion, contrairement à la session. À défaut, la session.
        $identifiant = $request->attributes->get('swp_vid') ?: $request->cookie('swp_vid') ?: $request->session()->getId();
        $cle = hash('sha256', 'presence|' . $identifiant . config('app.key'));
        $appli = (bool) ($donnees['appli'] ?? false);
        $source = self::source($donnees['utm'] ?? null, $donnees['ref'] ?? null, $request->userAgent(), $request->getHost());
        $path = mb_substr('/' . ltrim((string) ($donnees['path'] ?? '/'), '/'), 0, 500);

        $presence = Presence::firstOrNew(['cle' => $cle]);
        $dejaCompteAujourdhui = $presence->exists && $presence->last_seen_at->isSameDay(now());
        $nouvelle = ! $presence->exists
            || $presence->last_seen_at->lt(now()->subMinutes(30));

        if ($nouvelle) {
            // Nouvelle visite (ou retour après 30 min) : on repart de zéro.
            $presence->fill(['debut_at' => now(), 'pages' => 1, 'source' => $source]);
        } else {
            if (! empty($donnees['page']) && $presence->path !== $path) {
                $presence->pages++;
            }
            // La source se garde pour toute la visite ; seul « direct » peut
            // être précisé après coup.
            if ($presence->source === 'direct' && $source !== 'direct') {
                $presence->source = $source;
            }
        }

        $presence->fill([
            'ip_hash' => hash('sha256', $request->ip() . config('app.key')),
            'user_id' => optional($request->user())->id,
            'territoire' => TerritoireContext::resolve($request),
            'path' => $path,
            'appareil' => $appli ? 'appli' : self::appareil($request->userAgent()),
            'appli' => $appli,
            'last_seen_at' => now(),
        ])->save();

        // Premier signe de vie du jour : un visiteur unique de plus.
        if (! $dejaCompteAujourdhui) {
            \Illuminate\Support\Facades\DB::table('visiteurs_jours')->insertOrIgnore([
                'jour' => now()->toDateString(),
                'cle' => $cle,
                'user_id' => $presence->user_id,
                'appli' => $presence->appli,
                'source' => $presence->source,
                'territoire' => $presence->territoire,
                'arrivee_at' => now(),
            ]);
        }

        // Ménage de temps en temps, sans tâche planifiée.
        if (random_int(1, 200) === 1) {
            Presence::where('last_seen_at', '<', now()->subDays(self::CONSERVATION_JOURS))->delete();
        }

        return $presence;
    }

    /** D'où vient la visite : publicité, moteur de recherche, autre site… */
    public static function source(?string $utm, ?string $referent, ?string $userAgent, ?string $notreHote = null): string
    {
        $utm = mb_strtolower(trim((string) $utm));
        $ua = mb_strtolower((string) $userAgent);

        // Liens marqués (publicités, campagnes).
        if ($utm !== '') {
            foreach ([
                'instagram' => ['instagram', 'ig'],
                'facebook' => ['facebook', 'fb', 'meta', 'fbclid'],
                'google' => ['google', 'gclid'],
                'tiktok' => ['tiktok', 'ttclid'],
                'snapchat' => ['snapchat', 'snap'],
            ] as $source => $motifs) {
                foreach ($motifs as $motif) {
                    // Motifs courts (ig, fb) : mot exact, sinon « digital » serait Instagram.
                    if ($utm === $motif || (strlen($motif) > 3 && str_contains($utm, $motif))) {
                        // fbclid est aussi posé par Instagram : le navigateur intégré tranche.
                        return $source === 'facebook' && str_contains($ua, 'instagram') ? 'instagram' : $source;
                    }
                }
            }
        }

        // Navigateur intégré des applis Meta.
        if (str_contains($ua, 'instagram')) {
            return 'instagram';
        }
        if (str_contains($ua, 'fban') || str_contains($ua, 'fbav') || str_contains($ua, 'fb_iab')) {
            return 'facebook';
        }

        $hote = mb_strtolower((string) parse_url((string) $referent, PHP_URL_HOST));
        if ($hote === '' || ($notreHote && str_ends_with($hote, preg_replace('/^www\./', '', mb_strtolower($notreHote))))) {
            return $utm !== '' ? 'site' : 'direct';
        }

        return match (true) {
            str_contains($hote, 'instagram') => 'instagram',
            str_contains($hote, 'facebook') || $hote === 'fb.com' || str_ends_with($hote, '.fb.com') || $hote === 'l.facebook.com' => 'facebook',
            (bool) preg_match('/(^|\.)google\./', $hote) => 'google',
            str_contains($hote, 'tiktok') => 'tiktok',
            str_contains($hote, 'snapchat') => 'snapchat',
            (bool) preg_match('/bing\.|qwant\.|duckduckgo\.|ecosia\.|yahoo\.|yandex\.|brave\./', $hote) => 'moteur',
            default => 'site',
        };
    }

    public static function appareil(?string $userAgent): string
    {
        $ua = mb_strtolower((string) $userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet') || (str_contains($ua, 'android') && ! str_contains($ua, 'mobile'))) {
            return 'tablette';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        return 'ordinateur';
    }

    // --- Lecture ----------------------------------------------------------

    /** Visiteurs humains en ligne, les plus récents d'abord. */
    public static function enLigne(int $limite = 200): Collection
    {
        return Presence::query()
            ->with('user:id,name,city,territoire')
            ->where('last_seen_at', '>=', now()->subMinutes(self::FENETRE_MINUTES))
            ->latest('last_seen_at')
            ->limit($limite)
            ->get();
    }

    /**
     * Trafic vu par le serveur mais jamais confirmé par un navigateur :
     * robots déguisés, vérification des publicités, aperçus de liens,
     * personnes reparties avant d'avoir touché l'écran.
     */
    public static function nonConfirmes(): int
    {
        $depuis = now()->subMinutes(self::FENETRE_SERVEUR_MINUTES);

        return LiveVisit::query()
            ->where('last_seen_at', '>=', $depuis)
            ->whereNotIn('ip_hash', Presence::query()->where('last_seen_at', '>=', $depuis)->select('ip_hash'))
            ->count();
    }

    /** @return array{humains:int,membres:int,appli:int,mobile:int,ordinateur:int,non_confirmes:int} */
    public static function compte(): array
    {
        $enLigne = self::enLigne(5000);

        return [
            'humains' => $enLigne->count(),
            'membres' => $enLigne->whereNotNull('user_id')->count(),
            'appli' => $enLigne->where('appareil', 'appli')->count(),
            'mobile' => $enLigne->whereIn('appareil', ['mobile', 'tablette'])->count(),
            'ordinateur' => $enLigne->where('appareil', 'ordinateur')->count(),
            'non_confirmes' => self::nonConfirmes(),
        ];
    }

    /** @return array<string,int> nombre par clé de $libelles, dans l'ordre décroissant */
    public static function repartition(Collection $enLigne, string $colonne, array $libelles): array
    {
        $parCle = $enLigne->countBy($colonne);

        return collect($libelles)
            ->map(fn ($libelle, $cle) => (int) ($parCle[$cle] ?? 0))
            ->sortDesc()
            ->all();
    }

    /**
     * Points de la carte. Un membre connecté est placé sur sa commune (s'il
     * l'a renseignée) ; les autres sont regroupés en une bulle par île : on
     * ne connaît pas leur position, on ne l'invente pas.
     *
     * @return array<int,array{lat:float,lng:float,nombre:int,libelle:string,type:string}>
     */
    public static function pointsCarte(Collection $enLigne): array
    {
        $points = [];
        $parIle = [];

        foreach ($enLigne as $presence) {
            $ile = $presence->territoire ?: TerritoireContext::DEFAUT;
            $membre = $presence->user;
            $coords = $membre && $membre->city ? DomTomGeo::coordsConnues($membre->territoire ?: $ile, $membre->city) : null;

            if ($coords) {
                $cle = 'm' . $coords[0] . ',' . $coords[1];
                $points[$cle] ??= ['lat' => $coords[0], 'lng' => $coords[1], 'nombre' => 0, 'libelle' => $membre->city, 'type' => 'membre'];
                $points[$cle]['nombre']++;

                continue;
            }

            $parIle[$ile] = ($parIle[$ile] ?? 0) + 1;
        }

        foreach ($parIle as $ile => $nombre) {
            [$lat, $lng] = DomTomGeo::center($ile);
            $points[] = ['lat' => $lat, 'lng' => $lng, 'nombre' => $nombre, 'libelle' => $ile, 'type' => 'ile'];
        }

        return array_values($points);
    }
}
