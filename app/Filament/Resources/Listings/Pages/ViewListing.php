<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewListing extends ViewRecord
{
    protected static string $resource = ListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('photos')
                ->label('Photos')
                ->icon('heroicon-o-photo')
                ->color('info')
                ->modalHeading('Gérer les photos de l\'annonce')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer')
                ->modalContent(fn () => view('filament.listings.photos', [
                    'listing' => $this->getRecord()->load('images'),
                ])),
            EditAction::make(),
        ];
    }
}
