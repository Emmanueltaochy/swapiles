<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\AnalyticsMetrics;
use App\Support\TableauDeBord;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class AdvancedAnalytics extends Page
{
    protected static ?string $navigationLabel = 'Analyse avancée';
    protected static ?string $title = 'Analyse avancée';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?int $navigationSort = 3;

    protected static string | \UnitEnum | null $navigationGroup = 'Audience';

    protected string $view = 'filament.pages.advanced-analytics';

    public function getSubheading(): ?string
    {
        return 'Visiteurs humains uniquement (robots exclus) · mêmes chiffres que le tableau de bord.';
    }

    public function getViewData(): array
    {
        $period = (string) request()->query('period', '30j');
        if (! array_key_exists($period, TableauDeBord::PERIODES)) {
            $period = '30j';
        }

        // Plage personnalisée (date à date), prioritaire sur la période.
        $from = $this->parseDate(request()->query('from'));
        $to = $this->parseDate(request()->query('to'));
        if ($from && ! $to) {
            $to = Carbon::today();
        }

        $metrics = AnalyticsMetrics::advanced($period, $from, $to);

        $debutCourbe = $metrics['from']
            ? Carbon::parse($metrics['from'])
            : (User::query()->min('created_at') ? Carbon::parse(User::query()->min('created_at')) : today()->subDays(29));

        return $metrics + [
            'periods' => AnalyticsMetrics::periods(),
            'aujourdhui' => AnalyticsMetrics::aujourdhui(),
            'activite' => AnalyticsMetrics::activiteParJour($debutCourbe, $to ?: today()),
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
