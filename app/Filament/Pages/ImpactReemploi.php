<?php

namespace App\Filament\Pages;

use App\Support\ImpactReemploi as Impact;
use Filament\Actions\Action;
use Filament\Pages\Page;

/** Impact du réemploi : chiffres pour les dossiers de subvention (ADEME), la presse et les repreneurs. */
class ImpactReemploi extends Page
{
    protected static ?string $navigationLabel = 'Impact réemploi';

    protected static ?string $title = 'Impact réemploi';

    protected static ?string $slug = 'impact-reemploi';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static string | \UnitEnum | null $navigationGroup = 'Valeur & marché';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.impact-reemploi';

    public function getSubheading(): ?string
    {
        return 'Articles qui ont trouvé une seconde vie sur Swap’Îles, déchets évités et CO₂ économisé (estimations).';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exporter')
                ->label('Exporter (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print(Impact::csv(Impact::bilan())),
                    'swapiles-impact-reemploi-' . now()->format('Y-m-d') . '.csv',
                    ['Content-Type' => 'text/csv; charset=UTF-8'],
                )),
        ];
    }

    public function getViewData(): array
    {
        return [
            'b' => Impact::bilan(),
            'b30' => Impact::bilan(now()->subDays(30)),
            'facteurs' => config('impact.categories'),
            'defaut' => config('impact.defaut'),
            'kmVoiture' => config('impact.co2_par_km_voiture'),
        ];
    }
}
