<?php

namespace App\Filament\Pages;

use App\Support\Croissance as Chiffres;
use Filament\Actions\Action;
use Filament\Pages\Page;

/** La croissance mois par mois : les chiffres à montrer à un repreneur. */
class Croissance extends Page
{
    protected static ?string $navigationLabel = 'Croissance';

    protected static ?string $title = 'Croissance';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static string | \UnitEnum | null $navigationGroup = 'Audience';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.croissance';

    public function getSubheading(): ?string
    {
        return 'Mois par mois : volume, revenus, liquidité, fidélité et audience. Chiffres globaux et anonymes.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exporter')
                ->label('Exporter (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print(Chiffres::csv(Chiffres::mois())),
                    'swapiles-croissance-' . now()->format('Y-m-d') . '.csv',
                    ['Content-Type' => 'text/csv; charset=UTF-8'],
                )),
        ];
    }

    public function getViewData(): array
    {
        $mois = Chiffres::mois();
        $actuel = Chiffres::derniers30Jours();

        return [
            'mois' => $mois,
            'actuel' => $actuel,
            'palier' => Chiffres::palier($actuel, $mois),
        ];
    }
}
