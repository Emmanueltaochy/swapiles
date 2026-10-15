<?php

namespace App\Filament\Pages;

use App\Support\DonneesMarche;
use Filament\Actions\Action;
use Filament\Pages\Page;

/**
 * Données de valeur : ce que regarderait un acheteur de Swap'Îles, et les
 * tendances du marché de l'occasion dans les îles. Chiffres agrégés et
 * anonymes uniquement (voir DonneesMarche).
 */
class DonneesValeur extends Page
{
    protected static ?string $navigationLabel = 'Données de valeur';
    protected static ?string $title = 'Données de valeur';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?int $navigationSort = 1;

    protected static string | \UnitEnum | null $navigationGroup = 'Valeur & marché';

    protected string $view = 'filament.pages.donnees-valeur';

    public function getSubheading(): ?string
    {
        return 'Chiffres agrégés et anonymes : ce qu’un acheteur de l’entreprise ou une marque peut recevoir. Lignes de moins de '
            . DonneesMarche::SEUIL_ANONYMAT . ' personnes ou annonces masquées.';
    }

    public function getViewData(): array
    {
        return [
            'c' => DonneesMarche::croissance(),
            'mois' => DonneesMarche::parMois(),
            'categories' => DonneesMarche::parCategorie(),
            'iles' => DonneesMarche::parIle(),
            'marques' => DonneesMarche::marques(),
            'demande' => DonneesMarche::demandeNonSatisfaite(),
            'modes' => DonneesMarche::modesDeVente(),
            'jours' => DonneesMarche::ventesParJour(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')
                ->label('Exporter (CSV anonyme)')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => response()->streamDownload(
                    fn () => print(DonneesMarche::csv()),
                    'swapiles-donnees-anonymes-' . now()->format('Y-m-d') . '.csv',
                    ['Content-Type' => 'text/csv; charset=UTF-8'],
                )),
        ];
    }
}
