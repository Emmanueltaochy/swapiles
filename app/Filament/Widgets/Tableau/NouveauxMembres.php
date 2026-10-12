<?php

namespace App\Filament\Widgets\Tableau;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NouveauxMembres extends TableWidget
{
    protected static ?int $sort = 8;

    protected static ?string $heading = 'Nouveaux membres';

    protected static bool $isDiscovered = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->withCount('listings')->latest()->limit(8))
            ->paginated(false)
            ->recordUrl(fn (User $u) => UserResource::getUrl('view', ['record' => $u]))
            ->columns([
                TextColumn::make('name')->label('Membre')->limit(22),
                TextColumn::make('territoire')->label('Île')->placeholder('—'),
                TextColumn::make('listings_count')->label('Annonces'),
                TextColumn::make('created_at')->label('Inscrit')->since(),
            ]);
    }
}
