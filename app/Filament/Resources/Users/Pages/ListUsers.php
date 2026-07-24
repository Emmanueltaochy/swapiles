<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\CsvExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            CsvExport::action('utilisateurs', [
                'ID' => fn ($u) => $u->id,
                'Nom' => fn ($u) => $u->name,
                'Email' => fn ($u) => $u->email,
                'Téléphone' => fn ($u) => $u->phone,
                'Île' => fn ($u) => $u->territoire,
                'Ville' => fn ($u) => $u->city,
                'Code postal' => fn ($u) => $u->postal_code,
                'Comment connu' => fn ($u) => $u->comment_connu,
                'Email vérifié' => fn ($u) => ! is_null($u->email_verified_at),
                'Paiements CB actifs' => fn ($u) => (bool) $u->stripe_charges_enabled,
                'Pro' => fn ($u) => (bool) $u->is_pro,
                'Banni' => fn ($u) => (bool) $u->is_banned,
                'Inscrit le' => fn ($u) => $u->created_at,
            ], fn () => $this->getFilteredTableQuery()->get()),
        ];
    }
}
