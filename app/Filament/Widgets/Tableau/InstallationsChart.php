<?php

namespace App\Filament\Widgets\Tableau;

use App\Support\InstallationsAppli;
use Filament\Widgets\ChartWidget;

class InstallationsChart extends ChartWidget
{
    protected ?string $heading = 'Nouvelles installations ouvertes, par jour';

    protected ?string $description = '30 derniers jours.';

    protected ?string $maxHeight = '280px';

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $s = InstallationsAppli::nouvellesParJour(30);

        return [
            'labels' => $s['labels'],
            'datasets' => [
                ['label' => 'iPhone / iPad', 'data' => $s['ios'], 'backgroundColor' => 'rgba(15,23,42,.8)', 'borderRadius' => 3, 'stack' => 'a'],
                ['label' => 'Android', 'data' => $s['android'], 'backgroundColor' => 'rgba(13,148,136,.8)', 'borderRadius' => 3, 'stack' => 'a'],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'ticks' => ['precision' => 0]]]];
    }
}
