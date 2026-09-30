<?php

namespace App\Filament\Pages;

use App\Support\CategoryAudit;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Rangement des catégories.
 *
 * L'arbre ne couvrait que l'habillement alors que la plateforme vend aussi du
 * mobilier, du high-tech, du sport et du bricolage : ces annonces portaient une
 * catégorie fausse ou vide et restaient introuvables par la navigation. Cette
 * page montre où en sont vraiment les annonces, propose un rangement, et ne
 * l'applique que sur demande.
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
            'propositions' => $apercu['propositions'],
            'reconnues' => $apercu['reconnues'],
            'inconnues' => $apercu['inconnues'],
        ];
    }

    /** Range toutes les annonces que les règles reconnaissent. */
    public function rangerTout(): void
    {
        $deplacees = CategoryAudit::ranger();

        Notification::make()
            ->success()
            ->title($deplacees > 0
                ? $deplacees . ' annonce' . ($deplacees > 1 ? 's rangées' : ' rangée')
                : 'Rien à ranger')
            ->body($deplacees > 0
                ? 'Elles sont désormais visibles dans la navigation par catégorie.'
                : 'Toutes les annonces reconnues sont déjà à leur place.')
            ->send();
    }
}
