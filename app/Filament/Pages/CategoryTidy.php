<?php

namespace App\Filament\Pages;

use App\Support\CategoryAudit;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Rangement des catégories.
 *
 * Le formulaire de dépôt n'a longtemps offert que Femme / Homme / Enfant : le
 * vendeur d'un réfrigérateur n'avait aucun rayon juste à choisir. Toutes les
 * annonces portent donc une catégorie « valide » sans que le contenu
 * corresponde. Cette page montre le désordre réel, propose un rangement et ne
 * l'applique que sur clic — avec un retour en arrière possible.
 */
class CategoryTidy extends Page
{
    protected static ?string $navigationLabel = 'Rangement des catégories';

    protected static ?string $title = 'Rangement des catégories';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.category-tidy';

    public function getViewData(): array
    {
        $apercu = CategoryAudit::apercu();

        return [
            'repartition' => CategoryAudit::repartition(),
            'repartitionNiveau2' => CategoryAudit::repartitionNiveau2(),
            'aRanger' => $apercu['aRanger'],
            'aReclasser' => $apercu['aReclasser'],
            'laissees' => $apercu['laissees'],
            'motsNonReconnus' => CategoryAudit::motsNonReconnus(),
            'dejaDeplacees' => CategoryAudit::nombreDeplacees(),
        ];
    }

    /** Range et reclasse toutes les annonces dont le titre est reconnu. */
    public function rangerTout(): void
    {
        $bilan = CategoryAudit::ranger();
        $total = $bilan['rangees'] + $bilan['reclassees'];

        if ($total === 0) {
            Notification::make()->warning()
                ->title('Rien à ranger')
                ->body('Aucun titre ne correspond à une règle pour le moment.')
                ->send();

            return;
        }

        Notification::make()->success()
            ->title($total . ' annonce' . ($total > 1 ? 's déplacées' : ' déplacée'))
            ->body($bilan['rangees'] . ' rangée(s) depuis un rayon invalide, '
                . $bilan['reclassees'] . ' reclassée(s) d\'après leur titre. '
                . 'Le bouton « Annuler » remet tout en place.')
            ->persistent()
            ->send();
    }

    /** Remet chaque annonce déplacée là où elle était. */
    public function annulerRangement(): void
    {
        $remises = CategoryAudit::annuler();

        Notification::make()->success()
            ->title($remises . ' annonce' . ($remises > 1 ? 's remises' : ' remise') . ' en place')
            ->send();
    }
}
