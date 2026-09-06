<?php

namespace App\Filament\Pages;

use App\Models\Listing;
use App\Support\ListingDuplicates as Doublons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Nettoyage des annonces publiées en double (formulaire envoyé plusieurs fois
 * avant la correction anti-doublon). On garde toujours la plus ancienne.
 */
class ListingDuplicates extends Page
{
    protected static ?string $navigationLabel = 'Annonces en double';

    protected static ?string $title = 'Annonces en double';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.listing-duplicates';

    public function getViewData(): array
    {
        $groupes = Doublons::groups();

        return [
            'groupes' => $groupes,
            'enTrop' => $groupes->sum(fn (array $g) => $g['copies']->count()),
        ];
    }

    /** Supprime les copies d'un groupe et conserve l'annonce d'origine. */
    public function supprimerCopies(int $keepId): void
    {
        $origine = Listing::find($keepId);

        if (! $origine) {
            Notification::make()->danger()->title('Annonce introuvable.')->send();

            return;
        }

        $groupe = Doublons::groups(PHP_INT_MAX)
            ->first(fn (array $g) => $g['keep']->id === $origine->id);

        if (! $groupe) {
            Notification::make()->warning()->title('Plus aucun doublon pour cette annonce.')->send();

            return;
        }

        $supprimees = 0;
        foreach ($groupe['copies'] as $copie) {
            // Sécurité : on ne supprime jamais une annonce qui porte une vente.
            if ($copie->transactions()->exists()) {
                continue;
            }

            $copie->delete();
            $supprimees++;
        }

        Notification::make()
            ->success()
            ->title($supprimees . ' copie' . ($supprimees > 1 ? 's' : '') . ' supprimée' . ($supprimees > 1 ? 's' : ''))
            ->body('L’annonce d’origine « ' . $origine->title . ' » est conservée.')
            ->send();
    }
}
