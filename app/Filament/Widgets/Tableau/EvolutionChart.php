<?php

namespace App\Filament\Widgets\Tableau;

use App\Support\TableauDeBord;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class EvolutionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Évolution';

    protected ?string $description = 'Inscriptions, annonces et ventes sur la période choisie.';

    protected int | string | array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    // Pas de rafraîchissement automatique : ces chiffres bougent peu.
    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    /** Dates lisibles : une étiquette sur deux ou trois au lieu de toutes collées. */
    protected function getOptions(): array
    {
        return [
            'scales' => ['x' => ['ticks' => ['autoSkip' => true, 'maxTicksLimit' => 10, 'maxRotation' => 0]]],
            'plugins' => ['legend' => ['position' => 'bottom']],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $e = TableauDeBord::evolutionPeriode($this->pageFilters['periode'] ?? '30j');

        return [
            'labels' => $e['labels'],
            'datasets' => [
                ['label' => 'Inscriptions', 'data' => $e['inscriptions'], 'borderColor' => '#0d9488', 'backgroundColor' => 'rgba(13,148,136,.12)', 'fill' => true, 'tension' => .3],
                ['label' => 'Annonces', 'data' => $e['annonces'], 'borderColor' => '#3b82f6', 'backgroundColor' => 'rgba(59,130,246,.08)', 'fill' => true, 'tension' => .3],
                ['label' => 'Ventes payées en ligne', 'data' => $e['ventes'], 'borderColor' => '#f59e0b', 'backgroundColor' => 'rgba(245,158,11,.1)', 'fill' => true, 'tension' => .3],
            ],
        ];
    }
}
