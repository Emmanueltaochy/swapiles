<?php

namespace App\Filament\Widgets\Tableau;

use App\Support\TableauDeBord;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class VisiteursChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 5;

    protected ?string $heading = 'Visiteurs uniques par jour';

    protected ?string $description = 'Humains confirmés par leur navigateur (robots exclus), comptés depuis le 12 octobre 2026.';

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
        $jours = TableauDeBord::joursGraphique($this->pageFilters['periode'] ?? '30j');
        $labels = [];
        for ($i = $jours - 1; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('d/m');
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Visiteurs',
                'data' => TableauDeBord::visiteursParJour($jours),
                'backgroundColor' => 'rgba(13,148,136,.75)',
                'borderRadius' => 4,
            ]],
        ];
    }
}
