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

        $metrics = AnalyticsMetrics::advanced($period);

        $chartDays = AnalyticsMetrics::chartDays($period);
        $signups = AnalyticsMetrics::dailySeries(\App\Models\User::class, $chartDays);
        $listings = AnalyticsMetrics::dailySeries(\App\Models\Listing::class, $chartDays);
        $sales = AnalyticsMetrics::dailySeries(
            \App\Models\Transaction::class,
            $chartDays,
            fn ($q) => $q->whereIn('status', ['paid', 'completed'])
        );

        $events = null;
        if ($metrics['hasEvents']) {
            $events = AnalyticsMetrics::dailySeries(\App\Models\AnalyticsEvent::class, $chartDays);
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
        ]);
    }
}
