<?php

namespace App\Filament\Resources\Reviews\Pages;

use App\Filament\Resources\Reviews\ReviewResource;
use App\Support\CsvExport;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            CsvExport::action('avis', [
                'ID' => fn ($r) => $r->id,
                'Date' => fn ($r) => $r->created_at,
                'Note' => fn ($r) => $r->rating,
                'Commentaire' => fn ($r) => $r->comment,
                'Auteur' => fn ($r) => $r->reviewer?->name,
                'Email auteur' => fn ($r) => $r->reviewer?->email,
                'Évalué' => fn ($r) => $r->reviewed?->name,
                'Email évalué' => fn ($r) => $r->reviewed?->email,
            ], fn () => $this->getFilteredTableQuery()->with(['reviewer', 'reviewed'])->get()),
        ];
    }
}
