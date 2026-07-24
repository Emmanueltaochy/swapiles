<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use App\Support\CsvExport;
use Filament\Resources\Pages\ListRecords;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CsvExport::action('messages', [
                'ID' => fn ($m) => $m->id,
                'Date' => fn ($m) => $m->created_at,
                'Expéditeur' => fn ($m) => $m->sender?->name,
                'Email expéditeur' => fn ($m) => $m->sender?->email,
                'Destinataire' => fn ($m) => $m->receiver?->name,
                'Email destinataire' => fn ($m) => $m->receiver?->email,
                'Annonce' => fn ($m) => $m->listing?->title,
                'Message' => fn ($m) => $m->body,
                'Lu' => fn ($m) => ! is_null($m->read_at),
            ], fn () => $this->getFilteredTableQuery()->with(['sender', 'receiver', 'listing'])->get()),
        ];
    }
}
