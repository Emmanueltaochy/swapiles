<?php

namespace App\Filament\Pages;

use App\Models\SearchNoResult;
use Filament\Pages\Page;

/**
 * Point 17 : recherches sans résultat, classées par fréquence et nombre
 * d'utilisateurs distincts — pour prioriser l'offre (PS5, iPhone, canapé…).
 */
class NoResultSearches extends Page
{
    protected static ?string $navigationLabel = 'Recherches sans résultat';

    protected static ?string $title = 'Recherches sans résultat';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.no-result-searches';

    public function getViewData(): array
    {
        $since = now()->subDays(30);

        $rows = SearchNoResult::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('term, COUNT(*) as total, COUNT(DISTINCT COALESCE(visitor_id, user_id)) as visitors, MAX(created_at) as last_seen')
            ->groupBy('term')
            ->orderByDesc('total')
            ->limit(200)
            ->get();

        return [
            'rows' => $rows,
            'totalSearches' => SearchNoResult::where('created_at', '>=', $since)->count(),
            'distinctTerms' => $rows->count(),
        ];
    }
}
