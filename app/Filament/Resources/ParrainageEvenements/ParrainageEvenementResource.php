<?php

namespace App\Filament\Resources\ParrainageEvenements;

use App\Filament\Resources\ParrainageEvenements\Pages\ListParrainageEvenements;
use App\Models\Parrainage;
use App\Models\ParrainageEvenement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Journal du parrainage : chaque tentative (acceptée / refusée + motif) et la mesure. */
class ParrainageEvenementResource extends Resource
{
    use \App\Filament\Concerns\TitresEnFrancais;

    protected static ?string $model = ParrainageEvenement::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Journal du parrainage';

    protected static ?string $modelLabel = 'événement';

    protected static ?string $pluralModelLabel = 'journal du parrainage';

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user:id,name,email'))
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('evenement')->label('Événement')->badge()
                    ->formatStateUsing(fn (?string $state) => ParrainageEvenement::LIBELLES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'referral_reward_granted' => 'success',
                        'referral_reward_rejected', 'referral_reward_cancelled', 'referral_code_rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')->label('Membre')->placeholder('—')->searchable()
                    ->description(fn (ParrainageEvenement $r) => $r->user?->email),
                TextColumn::make('motif')->label('Motif')->placeholder('—')
                    // Code refusé : le message d'erreur montré au membre, en clair.
                    ->formatStateUsing(fn (?string $state, ParrainageEvenement $r) => Parrainage::MOTIFS[$state]
                        ?? ($r->donnees['erreur'] ?? ucfirst(str_replace('_', ' ', (string) $state)) . '…'))
                    ->limit(50)
                    ->wrap()
                    ->description(fn (ParrainageEvenement $r) => collect([
                        $r->canal ? 'via ' . $r->canal : null,
                        $r->transaction_id ? 'commande #' . $r->transaction_id : null,
                    ])->filter()->implode(' · ') ?: null),
            ])
            ->filters([
                SelectFilter::make('evenement')->label('Événement')->options(ParrainageEvenement::LIBELLES),
                SelectFilter::make('motif')->label('Motif')->options(Parrainage::MOTIFS),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListParrainageEvenements::route('/')];
    }
}
