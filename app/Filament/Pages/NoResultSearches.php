<?php

namespace App\Filament\Pages;

use App\Models\SearchLog;
use App\Models\SearchNoResult;
use Filament\Pages\Page;

/**
 * Recherches des visiteurs, en deux onglets :
 *  - « Toutes les recherches » : ce que les gens cherchent, avec ou sans résultat ;
 *  - « Introuvables » : ce qui n'existe nulle part sur la plateforme (à
 *    recruter : PS5, iPhone, canapé…).
 */
class NoResultSearches extends Page
{
    protected static ?string $navigationLabel = 'Recherches';

    protected static ?string $title = 'Recherches';

    protected static ?string $slug = 'no-result-searches';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?int $navigationSort = 4;

    protected static string | \UnitEnum | null $navigationGroup = 'Audience';

    protected string $view = 'filament.pages.no-result-searches';

    public string $onglet = 'toutes';

    public function mount(): void
    {
        $this->onglet = request('onglet') === 'introuvables' ? 'introuvables' : 'toutes';
    }

    public function getSubheading(): ?string
    {
        return '30 derniers jours · robots exclus';
    }

    public function getViewData(): array
    {
        $since = now()->subDays(30);

        $toutes = SearchLog::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('term, COUNT(*) as total, COUNT(DISTINCT COALESCE(visitor_id, user_id)) as visitors, ROUND(AVG(resultats)) as resultats, SUM(CASE WHEN resultats = 0 THEN 1 ELSE 0 END) as vides, MAX(created_at) as last_seen')
            ->groupBy('term')
            ->orderByDesc('total')
            ->limit(200)
            ->get();

        $introuvables = SearchNoResult::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('term, COUNT(*) as total, COUNT(DISTINCT COALESCE(visitor_id, user_id)) as visitors, MAX(created_at) as last_seen')
            ->groupBy('term')
            ->orderByDesc('total')
            ->limit(200)
            ->get();

        return [
            'toutes' => $toutes,
            'introuvables' => $introuvables,
            'nbRecherches' => SearchLog::where('created_at', '>=', $since)->count(),
            'nbIntrouvables' => SearchNoResult::where('created_at', '>=', $since)->count(),
        ];
    }
}
