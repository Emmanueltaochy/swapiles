<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Habitudes et historique d'un membre, pour l'équipe Swap'Îles.
 *
 * Usage interne uniquement (support, modération, amélioration du service) :
 * ces informations ne quittent jamais l'administration. Le contenu des
 * messages privés n'est jamais affiché, seulement le fait qu'un échange a eu
 * lieu et à propos de quelle annonce.
 */
class HistoriqueMembre
{
    /** Fenêtre d'observation des habitudes. */
    public const JOURS = 90;

    /** Fuseau de chaque île : « le soir » à Fort-de-France n'est pas le soir à Saint-Denis. */
    public const FUSEAUX = [
        'La Réunion' => 'Indian/Reunion',
        'Mayotte' => 'Indian/Mayotte',
        'Martinique' => 'America/Martinique',
        'Guadeloupe' => 'America/Guadeloupe',
        'Guyane' => 'America/Cayenne',
    ];

    private const JOURS_SEMAINE = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    // --- Habitudes ----------------------------------------------------------

    /** @return array<string,mixed> */
    public static function habitudes(User $membre): array
    {
        $depuis = now()->subDays(self::JOURS);
        $fuseau = self::FUSEAUX[$membre->territoire] ?? config('app.timezone');

        $moments = Schema::hasTable('analytics_events')
            ? DB::table('analytics_events')
                ->where('user_id', $membre->id)
                ->where('created_at', '>=', $depuis)
                ->orderBy('created_at')
                ->get(['created_at', 'device', 'path'])
            : collect();

        $heures = [];
        $jours = [];
        $joursActifs = [];
        foreach ($moments as $m) {
            $local = Carbon::parse($m->created_at, config('app.timezone'))->setTimezone($fuseau);
            $heures[$local->hour] = ($heures[$local->hour] ?? 0) + 1;
            $jours[$local->dayOfWeekIso] = ($jours[$local->dayOfWeekIso] ?? 0) + 1;
            $joursActifs[$local->toDateString()] = true;
        }

        $appareils = $moments->countBy(fn ($m) => VisiteursEnDirect::appareil($m->device))->sortDesc();
        $appli = DB::table('presences')->where('user_id', $membre->id)->where('appli', true)->exists();

        $achats = Transaction::query()->where('buyer_id', $membre->id)->whereIn('status', TableauDeBord::STATUTS_VENDUS);
        $ventes = Transaction::query()->where('seller_id', $membre->id)->whereIn('status', TableauDeBord::STATUTS_VENDUS);

        $derniereActivite = collect([
            $moments->last()?->created_at,
            DB::table('presences')->where('user_id', $membre->id)->max('last_seen_at'),
            Schema::hasTable('user_sessions') ? DB::table('user_sessions')->where('user_id', $membre->id)->max('created_at') : null,
        ])->filter()->map(fn ($d) => Carbon::parse($d))->sortDesc()->first();

        return [
            'jours_actifs' => count($joursActifs),
            'pages_vues' => $moments->count(),
            'derniere_activite' => $derniereActivite,
            'moment_prefere' => self::momentPrefere($heures),
            'jour_prefere' => $jours ? self::JOURS_SEMAINE[array_search(max($jours), $jours, true)] : null,
            'appareil' => $appli ? "Appli Swap'Îles" : match ($appareils->keys()->first()) {
                'mobile' => 'Téléphone (site)',
                'tablette' => 'Tablette (site)',
                'ordinateur' => 'Ordinateur (site)',
                default => null,
            },
            'categories' => self::categoriesPreferees($membre, $depuis),
            'marques' => array_slice(array_keys(Recommandations::profil($membre)['marques'] ?? []), 0, 5),
            'budget' => self::budget($membre, $depuis),
            'recherches' => self::recherches($membre, 8),
            'achats' => ['nombre' => (clone $achats)->count(), 'montant' => round((float) (clone $achats)->sum('amount'), 2)],
            'ventes' => ['nombre' => (clone $ventes)->count(), 'montant' => round((float) (clone $ventes)->sum('amount'), 2)],
            'annonces_en_ligne' => Listing::where('user_id', $membre->id)->where('status', 'published')->count(),
            'favoris' => DB::table('favorites')->where('user_id', $membre->id)->count(),
            'messages_envoyes' => DB::table('messages')->where('sender_id', $membre->id)->count(),
        ];
    }

    /** Tranche de la journée où le membre est le plus présent. */
    public static function momentPrefere(array $heures): ?string
    {
        if ($heures === []) {
            return null;
        }

        $tranches = ['la nuit (0 h – 6 h)' => 0, 'le matin (6 h – 12 h)' => 0, "l'après-midi (12 h – 18 h)" => 0, 'le soir (18 h – 24 h)' => 0];
        $noms = array_keys($tranches);
        foreach ($heures as $heure => $nombre) {
            $tranches[$noms[intdiv((int) $heure, 6)]] += $nombre;
        }
        arsort($tranches);

        return array_key_first($tranches);
    }

    /**
     * Catégories regardées et mises en favori.
     *
     * @return array<int,array{libelle:string,part:int}>
     */
    public static function categoriesPreferees(User $membre, Carbon $depuis): array
    {
        $ids = DB::table('favorites')->where('user_id', $membre->id)->where('created_at', '>=', $depuis)->pluck('listing_id');
        if (Schema::hasTable('listing_consultations')) {
            $ids = $ids->merge(DB::table('listing_consultations')->where('user_id', $membre->id)->where('derniere_vue_at', '>=', $depuis)->pluck('listing_id'));
        }

        $parCategorie = Listing::query()
            ->whereIn('id', $ids->unique())
            ->where('user_id', '!=', $membre->id)
            ->get(['category_level1', 'category_level2'])
            ->countBy(function ($annonce) {
                $n1 = Categories::label($annonce->category_level1) ?? 'Autre';
                $n2 = Categories::label($annonce->category_level2);

                return $n2 ? $n1 . ' › ' . $n2 : $n1;
            })
            ->sortDesc();

        $total = max(1, $parCategorie->sum());

        return $parCategorie->take(5)
            ->map(fn ($n, $libelle) => ['libelle' => $libelle, 'part' => (int) round($n / $total * 100)])
            ->values()
            ->all();
    }

    /**
     * Prix des articles qui l'intéressent (favoris et consultations) :
     * médiane, et fourchette du quart le moins cher au quart le plus cher.
     *
     * @return array{median:float,min:float,max:float}|null
     */
    public static function budget(User $membre, Carbon $depuis): ?array
    {
        $ids = DB::table('favorites')->where('user_id', $membre->id)->where('created_at', '>=', $depuis)->pluck('listing_id');
        if (Schema::hasTable('listing_consultations')) {
            $ids = $ids->merge(DB::table('listing_consultations')->where('user_id', $membre->id)->where('derniere_vue_at', '>=', $depuis)->pluck('listing_id'));
        }

        $prix = Listing::query()
            ->whereIn('id', $ids->unique())
            ->where('user_id', '!=', $membre->id)
            ->where('price', '>', 0)
            ->pluck('price')
            ->map(fn ($p) => (float) $p)
            ->sort()
            ->values();

        return self::fourchette($prix->all());
    }

    /** @param  array<int,float>  $prix  triés @return array{median:float,min:float,max:float}|null */
    public static function fourchette(array $prix): ?array
    {
        $n = count($prix);
        if ($n === 0) {
            return null;
        }

        $median = $n % 2 ? $prix[intdiv($n, 2)] : ($prix[$n / 2 - 1] + $prix[$n / 2]) / 2;

        return [
            'median' => round($median, 2),
            'min' => round($prix[(int) floor(($n - 1) * 0.25)], 2),
            'max' => round($prix[(int) ceil(($n - 1) * 0.75)], 2),
        ];
    }

    /** Dernières recherches tapées (les plus récentes d'abord, sans doublon). */
    public static function recherches(User $membre, int $limite = 10): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return [];
        }

        return DB::table('analytics_events')
            ->where('user_id', $membre->id)
            ->whereIn('path', ['recherche', '/recherche'])
            ->where('url', 'like', '%q=%')
            ->latest('created_at')
            ->limit(200)
            ->pluck('url')
            ->map(function ($url) {
                parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $params);

                return trim(mb_substr((string) ($params['q'] ?? ''), 0, 80));
            })
            ->filter()
            ->unique(fn ($q) => mb_strtolower($q))
            ->take($limite)
            ->values()
            ->all();
    }

    // --- Chronologie --------------------------------------------------------

    /**
     * Tout ce que le membre a fait, du plus récent au plus ancien.
     *
     * @return Collection<int,array{date:Carbon,icone:string,texte:string,lien:?string}>
     */
    public static function chronologie(User $membre, int $limite = 80): Collection
    {
        $evenements = collect();
        $ajouter = function ($date, string $icone, string $texte, ?string $lien = null) use ($evenements) {
            if ($date) {
                $evenements->push(['date' => Carbon::parse($date), 'icone' => $icone, 'texte' => $texte, 'lien' => $lien]);
            }
        };
        $annonce = fn ($id) => $id ? url('/annonce/' . $id) : null;

        $ajouter($membre->created_at, '🎉', 'Inscription sur Swap\'Îles');

        if (Schema::hasTable('user_sessions')) {
            DB::table('user_sessions')->where('user_id', $membre->id)->where('event_type', 'login')
                ->latest('created_at')->limit($limite)->get(['created_at', 'user_agent'])
                ->each(fn ($s) => $ajouter($s->created_at, '🔑', 'Connexion · ' . self::libelleAppareil($s->user_agent)));
        }

        Listing::query()->where('user_id', $membre->id)->latest()->limit($limite)->get(['id', 'title', 'price', 'created_at'])
            ->each(fn ($l) => $ajouter($l->created_at, '📦', 'A publié « ' . $l->title . ' » (' . TableauDeBord::euros((float) $l->price) . ')', $annonce($l->id)));

        DB::table('favorites')->join('listings', 'listings.id', '=', 'favorites.listing_id')
            ->where('favorites.user_id', $membre->id)->latest('favorites.created_at')->limit($limite)
            ->get(['favorites.created_at', 'listings.id', 'listings.title'])
            ->each(fn ($f) => $ajouter($f->created_at, '❤️', 'A mis en favori « ' . $f->title . ' »', $annonce($f->id)));

        if (Schema::hasTable('listing_consultations')) {
            DB::table('listing_consultations')->join('listings', 'listings.id', '=', 'listing_consultations.listing_id')
                ->where('listing_consultations.user_id', $membre->id)->latest('listing_consultations.derniere_vue_at')->limit($limite)
                ->get(['listing_consultations.derniere_vue_at', 'listing_consultations.vues', 'listings.id', 'listings.title'])
                ->each(fn ($c) => $ajouter($c->derniere_vue_at, '👀', 'A regardé « ' . $c->title . ' »' . ($c->vues > 1 ? ' (' . $c->vues . ' fois)' : ''), $annonce($c->id)));
        }

        // Une ligne par conversation et par jour, jamais le contenu.
        DB::table('messages')->leftJoin('listings', 'listings.id', '=', 'messages.listing_id')
            ->where('messages.sender_id', $membre->id)
            ->selectRaw('DATE(messages.created_at) as jour, messages.receiver_id, messages.listing_id, listings.title, MAX(messages.created_at) as dernier, COUNT(*) as nombre')
            ->groupBy('jour', 'messages.receiver_id', 'messages.listing_id', 'listings.title')
            ->orderByDesc('dernier')->limit($limite)->get()
            ->each(fn ($m) => $ajouter($m->dernier, '💬', $m->nombre . ' message(s) envoyé(s)' . ($m->title ? ' à propos de « ' . $m->title . ' »' : '')));

        Transaction::query()->with('listing:id,title')
            ->where(fn ($q) => $q->where('buyer_id', $membre->id)->orWhere('seller_id', $membre->id))
            ->latest()->limit($limite)->get()
            ->each(function (Transaction $t) use ($membre, $ajouter, $annonce) {
                $achat = (int) $t->buyer_id === (int) $membre->id;
                $etat = match ($t->status) {
                    'pending' => 'paiement en attente',
                    'paid' => 'payé',
                    'completed' => 'terminé',
                    'refunded' => 'remboursé',
                    'cancelled' => 'annulé',
                    default => $t->status,
                };
                $ajouter($t->created_at, $achat ? '🛍️' : '💰', ($achat ? 'Achat' : 'Vente') . ' de « ' . ($t->listing->title ?? 'annonce supprimée') . ' » · ' . TableauDeBord::euros((float) $t->amount) . ' · ' . $etat, $annonce($t->listing_id));
            });

        DB::table('reviews')->where('reviewer_id', $membre->id)->latest()->limit($limite)->get(['created_at', 'rating'])
            ->each(fn ($r) => $ajouter($r->created_at, '⭐', 'A laissé un avis ' . $r->rating . '/5'));
        DB::table('reviews')->where('reviewed_id', $membre->id)->latest()->limit($limite)->get(['created_at', 'rating'])
            ->each(fn ($r) => $ajouter($r->created_at, '🌟', 'A reçu un avis ' . $r->rating . '/5'));

        foreach (self::recherchesDatees($membre, $limite) as [$date, $texte]) {
            $ajouter($date, '🔍', 'A cherché « ' . $texte . ' »');
        }

        return $evenements->sortByDesc(fn ($e) => $e['date']->getTimestamp())->take($limite)->values();
    }

    private static function recherchesDatees(User $membre, int $limite): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return [];
        }

        return DB::table('analytics_events')
            ->where('user_id', $membre->id)
            ->whereIn('path', ['recherche', '/recherche'])
            ->where('url', 'like', '%q=%')
            ->latest('created_at')->limit($limite)->get(['created_at', 'url'])
            ->map(function ($e) {
                parse_str((string) parse_url((string) $e->url, PHP_URL_QUERY), $params);

                return [$e->created_at, trim(mb_substr((string) ($params['q'] ?? ''), 0, 80))];
            })
            ->filter(fn ($r) => $r[1] !== '')
            ->all();
    }

    private static function libelleAppareil(?string $userAgent): string
    {
        $ua = mb_strtolower((string) $userAgent);
        if (str_contains($ua, 'swapiles') || str_contains($ua, 'capacitor') || str_contains($ua, '; wv)')) {
            return 'appli';
        }

        return match (VisiteursEnDirect::appareil($userAgent)) {
            'mobile' => 'téléphone',
            'tablette' => 'tablette',
            default => 'ordinateur',
        };
    }
}
