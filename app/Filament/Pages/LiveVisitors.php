<?php

namespace App\Filament\Pages;

use App\Models\LiveVisit;
use Filament\Pages\Page;

class LiveVisitors extends Page
{
    protected static ?string $navigationLabel = 'En direct';
    protected static ?string $title = 'Visiteurs en direct';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.live-visitors';

    public function getLiveVisits()
    {
        return LiveVisit::query()
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->latest('last_seen_at')
            ->limit(100)
            ->get();
    }
}
