<?php

namespace App\Filament\Widgets\Tableau;

use App\Support\TableauDeBord;
use Filament\Widgets\ChartWidget;

class AnnoncesParIleChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Annonces en ligne par île';

    protected ?string $maxHeight = '260px';

    // Pas de rafraîchissement automatique : ces chiffres bougent peu.
    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $parIle = TableauDeBord::annoncesParIle();

        return [
            'labels' => array_map(fn ($ile, $n) => $ile . ' (' . $n . ')', array_keys($parIle), $parIle),
            'datasets' => [[
                'data' => array_values($parIle),
                'backgroundColor' => ['#0d9488', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#94a3b8'],
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['position' => 'right']], 'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
