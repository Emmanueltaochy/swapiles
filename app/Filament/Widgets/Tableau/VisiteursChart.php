<?php

namespace App\Filament\Widgets\Tableau;

use App\Support\TableauDeBord;
use Filament\Widgets\ChartWidget;

class VisiteursChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Visiteurs uniques par jour';

    protected ?string $description = '30 derniers jours, robots exclus.';

    protected ?string $maxHeight = '260px';

    // Pas de rafraîchissement automatique : ces chiffres bougent peu.
    protected ?string $pollingInterval = null;

    protected static bool $isDiscovered = false;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $labels = [];
        for ($i = 29; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('d/m');
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Visiteurs',
                'data' => TableauDeBord::visiteursParJour(30),
                'backgroundColor' => 'rgba(13,148,136,.75)',
                'borderRadius' => 4,
            ]],
        ];
    }
}
