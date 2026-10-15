<?php

namespace App\Filament\Widgets\Push;

use App\Support\PushSuivi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Les 7 derniers jours : combien partent, combien sont ouvertes. */
class PushStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = '7 derniers jours';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $s = PushSuivi::bilan(now()->subDays(7), now());

        return [
            Stat::make('Notifications parties', number_format($s['parties'], 0, ',', ' '))
                ->description($s['automatiques'] . ' automatiques · ' . $s['manuelles'] . ' manuelles'),
            Stat::make('Ouvertes', number_format($s['ouvertes'], 0, ',', ' '))
                ->description($s['taux'] !== null ? str_replace('.', ',', (string) $s['taux']) . ' % des notifications personnelles' : 'pas encore de données')
                ->color('success'),
            Stat::make('Membres qui ont cliqué', number_format($s['membres_cliqueurs'], 0, ',', ' '))
                ->description('comptes différents'),
            Stat::make('Retenues', number_format($s['retenues'], 0, ',', ' '))
                ->description('nuit, plafond du jour ou réglages du membre')
                ->color('gray'),
        ];
    }
}
