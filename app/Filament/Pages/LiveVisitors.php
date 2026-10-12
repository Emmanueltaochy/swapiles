<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\TerritoireContext;
use App\Support\VisiteursEnDirect;
use Filament\Pages\Page;

/**
 * Qui est sur Swap'Îles en ce moment : humains confirmés par leur navigateur
 * (voir VisiteursEnDirect), carte, provenance (publicités…), pages vues.
 */
class LiveVisitors extends Page
{
    protected static ?string $navigationLabel = 'Visiteurs en direct';
    protected static ?string $title = 'Visiteurs en direct';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';
    protected static ?int $navigationSort = 1;

    protected static string | \UnitEnum | null $navigationGroup = 'Audience';

    protected string $view = 'filament.pages.live-visitors';

    public const DRAPEAUX = [
        'La Réunion' => '🇷🇪',
        'Martinique' => '🇲🇶',
        'Guadeloupe' => '🇬🇵',
        'Guyane' => '🇬🇫',
        'Mayotte' => '🇾🇹',
    ];

    public const PAGES = [
        '/' => 'Accueil',
        '/recherche' => 'Recherche',
        '/deposer-une-annonce' => 'Déposer une annonce',
        '/mon-compte' => 'Mon compte',
        '/connexion' => 'Connexion',
        '/inscription' => 'Inscription',
        '/messages' => 'Messages',
        '/favoris' => 'Favoris',
    ];

    public function getDonnees(): array
    {
        $enLigne = VisiteursEnDirect::enLigne();
        $compte = VisiteursEnDirect::compte();

        return [
            'compte' => $compte,
            'visites' => $enLigne->map(fn ($p) => [
                'ile' => $p->territoire ?: TerritoireContext::DEFAUT,
                'drapeau' => self::DRAPEAUX[$p->territoire] ?? '📍',
                'membre' => $p->user?->name,
                'lien_membre' => $p->user ? UserResource::getUrl('view', ['record' => $p->user]) : null,
                'page' => self::libellePage($p->path),
                'appareil' => VisiteursEnDirect::APPAREILS[$p->appareil] ?? $p->appareil,
                'source' => VisiteursEnDirect::SOURCES[$p->source] ?? $p->source,
                'pub' => in_array($p->source, ['facebook', 'instagram', 'tiktok', 'snapchat'], true),
                'pages' => $p->pages,
                'depuis' => $p->debut_at->diffForHumans(now(), true),
            ])->all(),
            'iles' => collect(self::DRAPEAUX)
                ->map(fn ($drapeau, $ile) => ['ile' => $ile, 'drapeau' => $drapeau, 'nombre' => $enLigne->where('territoire', $ile)->count()])
                ->sortByDesc('nombre')->values()->all(),
            'sources' => VisiteursEnDirect::repartition($enLigne, 'source', VisiteursEnDirect::SOURCES),
            'appareils' => VisiteursEnDirect::repartition($enLigne, 'appareil', VisiteursEnDirect::APPAREILS),
            'pages' => $enLigne->countBy(fn ($p) => self::libellePage($p->path))->sortDesc()->take(8)->all(),
            'points' => VisiteursEnDirect::pointsCarte($enLigne),
        ];
    }

    public static function libellePage(?string $path): string
    {
        $path = $path ?: '/';

        if (isset(self::PAGES[$path])) {
            return self::PAGES[$path];
        }

        return match (true) {
            str_starts_with($path, '/annonce/') => 'Fiche annonce',
            str_starts_with($path, '/profil/') || str_starts_with($path, '/membre/') => 'Dressing d’un membre',
            str_starts_with($path, '/iles/') => 'Page île',
            str_starts_with($path, '/categorie') => 'Catégorie',
            str_starts_with($path, '/messages') => 'Messages',
            str_starts_with($path, '/paiement') || str_starts_with($path, '/checkout') => 'Paiement',
            default => $path,
        };
    }
}
