<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditListing extends EditRecord
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
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
