<?php

namespace App\Filament\Pages;

use App\Support\AnalyticsMetrics;
use Filament\Pages\Page;

class AdvancedAnalytics extends Page
{
    protected static ?string $navigationLabel = 'Analyse avancée';
    protected static ?string $title = 'Analyse avancée';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.advanced-analytics';

    public function getViewData(): array
    {
        $period = request()->query('period', '30d');
        if (! array_key_exists($period, AnalyticsMetrics::periods())) {
            $period = '30d';
        }

        // Plage personnalisée (date à date), prioritaire sur la période.
        $from = $this->parseDate(request()->query('from'));
        $to = $this->parseDate(request()->query('to'));
        // Si une seule borne est fournie, on complète l'autre intelligemment.
        if ($from && ! $to) {
            $to = \Illuminate\Support\Carbon::today();
        }
        $custom = $from !== null || $to !== null;

        $metrics = AnalyticsMetrics::advanced($period, $from, $to);

        // Fenêtre des courbes : la plage choisie, sinon le nombre de jours de la période.
        if ($custom) {
            $chartStart = $from ?: \Illuminate\Support\Carbon::today()->subDays(30);
            $chartEnd = $to ?: \Illuminate\Support\Carbon::today();
            $signups = AnalyticsMetrics::dailySeriesBetween(\App\Models\User::class, $chartStart, $chartEnd);
            $listings = AnalyticsMetrics::dailySeriesBetween(\App\Models\Listing::class, $chartStart, $chartEnd);
            $sales = AnalyticsMetrics::dailySeriesBetween(\App\Models\Transaction::class, $chartStart, $chartEnd, fn ($q) => $q->whereIn('status', ['paid', 'completed']));
            $events = $metrics['hasEvents'] ? AnalyticsMetrics::dailySeriesBetween(\App\Models\AnalyticsEvent::class, $chartStart, $chartEnd) : null;
            $chartDays = count($signups['labels']);
        } else {
            $chartDays = AnalyticsMetrics::chartDays($period);
            $signups = AnalyticsMetrics::dailySeries(\App\Models\User::class, $chartDays);
            $listings = AnalyticsMetrics::dailySeries(\App\Models\Listing::class, $chartDays);
            $sales = AnalyticsMetrics::dailySeries(\App\Models\Transaction::class, $chartDays, fn ($q) => $q->whereIn('status', ['paid', 'completed']));
            $events = $metrics['hasEvents'] ? AnalyticsMetrics::dailySeries(\App\Models\AnalyticsEvent::class, $chartDays) : null;
        }

        return array_merge($metrics, [
            'periods' => AnalyticsMetrics::periods(),
            'series' => [
                'signups' => $signups,
                'listings' => $listings,
                'sales' => $sales,
                'events' => $events,
            ],
            'chartDays' => $chartDays,
            'todayHourly' => AnalyticsMetrics::todayHourlyVisitors(),
            'todayConcurrent' => AnalyticsMetrics::todayConcurrent(),
        ]);
    }

    private function parseDate(?string $value): ?\Illuminate\Support\Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
