<?php

namespace App\Filament\Pages;

use App\Support\DossierPartenaire;
use App\Support\TerritoireContext;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;

/** Dossier PDF pour les entreprises (RSE, points relais), avec les chiffres du jour. */
class DossierPartenaires extends Page
{
    protected static ?string $navigationLabel = 'Dossier partenaires';

    protected static ?string $title = 'Dossier partenaires (PDF)';

    protected static ?string $slug = 'dossier-partenaires';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string | \UnitEnum | null $navigationGroup = 'Valeur & marché';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dossier-partenaires';

    public function getSubheading(): ?string
    {
        return 'Le dossier à envoyer aux entreprises : présentation, chiffres réels du jour, impact et formules de partenariat.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Télécharger le PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->modalHeading('Télécharger le dossier partenaires')
                ->modalSubmitActionLabel('Télécharger')
                ->schema([
                    Select::make('ile')
                        ->label('Ajouter une page « Focus » sur une île ?')
                        ->placeholder('Non, toutes les îles')
                        ->options(array_combine(TerritoireContext::LABELS, TerritoireContext::LABELS)),
                ])
                ->action(function (array $data) {
                    $ile = $data['ile'] ?? null;

                    return response()->streamDownload(
                        fn () => print(DossierPartenaire::pdf($ile)),
                        DossierPartenaire::nomFichier($ile),
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
        ];
    }

    public function getViewData(): array
    {
        return ['d' => DossierPartenaire::donnees()];
    }
}
