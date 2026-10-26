<?php

namespace App\Filament\Resources\RelayPoints;

use App\Filament\Resources\RelayPoints\Pages\CreateRelayPoint;
use App\Filament\Resources\RelayPoints\Pages\EditRelayPoint;
use App\Filament\Resources\RelayPoints\Pages\ListRelayPoints;
use App\Models\RelayPoint;
use App\Support\DomTomGeo;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Points relais partenaires (commerçants). Pilote La Réunion : l'admin crée un
 * point relais, il apparaît alors comme option de retrait au paiement CB pour
 * les annonces du même territoire.
 */
class RelayPointResource extends Resource
{
    use \App\Filament\Concerns\TitresEnFrancais;

    protected static ?string $model = RelayPoint::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nom du commerçant / point relais')
                ->required()
                ->maxLength(191),
            Select::make('manager_user_id')
                ->label('Compte gérant (commerçant)')
                ->relationship('manager', 'email')
                ->searchable()
                ->preload()
                ->helperText('Le compte qui accède à l’espace relais (réception, remise, solde). Le commerçant doit d’abord créer un compte Swap’Îles.'),
            Select::make('territoire')
                ->label('Territoire')
                ->options(array_combine(DomTomGeo::territoires(), DomTomGeo::territoires()))
                ->default('La Réunion')
                ->required()
                ->native(false),
            TextInput::make('address')
                ->label('Adresse')
                ->maxLength(255),
            TextInput::make('postal_code')
                ->label('Code postal')
                ->maxLength(20),
            TextInput::make('city')
                ->label('Ville')
                ->maxLength(120),
            TextInput::make('latitude')
                ->label('Latitude (facultatif)')
                ->numeric()
                ->helperText('Copie depuis Google Maps (clic droit sur la boutique → coordonnées). Vide = centre de la commune.'),
            TextInput::make('longitude')
                ->label('Longitude (facultatif)')
                ->numeric(),
            TextInput::make('contact_name')
                ->label('Contact (facultatif)')
                ->maxLength(120),
            TextInput::make('contact_phone')
                ->label('Téléphone (facultatif)')
                ->tel()
                ->maxLength(40),
            TextInput::make('opening_hours')
                ->label('Horaires (facultatif)')
                ->placeholder('Lun–Sam 9h–18h')
                ->maxLength(191),
            Textarea::make('notes')
                ->label('Notes internes (facultatif)')
                ->rows(2)
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label('Actif (proposé au paiement)')
                ->default(true),
            \Filament\Schemas\Components\Section::make('🤝 Enseigne partenaire (RSE)')
                ->description('Badge et logo dans l’appli, et rapport d’impact mensuel à envoyer à l’enseigne.')
                ->schema([
                    TextInput::make('partenaire_enseigne')
                        ->label('Enseigne (laisser vide si ce n’est pas un partenaire)')
                        ->placeholder('Ex : Leclerc Saint-Denis')
                        ->maxLength(120),
                    \Filament\Forms\Components\FileUpload::make('partenaire_logo')
                        ->label('Logo de l’enseigne')
                        ->image()
                        ->disk('public')
                        ->directory('partenaires')
                        ->maxSize(1024),
                    \Filament\Forms\Components\DatePicker::make('partenaire_depuis')
                        ->label('Partenaire depuis le'),
                ])
                ->columnSpanFull()
                ->collapsible(),
            \Filament\Schemas\Components\Section::make('🧾 Charte et facturation')
                ->description(fn (?RelayPoint $record) => $record?->charte_acceptee_at
                    ? 'Charte ' . $record->charte_version . ' acceptée le ' . $record->charte_acceptee_at->format('d/m/Y') . ' (mandat d’autofacturation inclus).'
                    : 'Charte pas encore acceptée : le commerçant l’accepte dans son espace relais.')
                ->schema([
                    TextInput::make('raison_sociale')
                        ->label('Raison sociale (sur les factures)')
                        ->maxLength(160),
                    TextInput::make('siret')
                        ->label('SIRET')
                        ->maxLength(20)
                        ->helperText('Avec un SIRET, le relevé mensuel est une autofacture. Sans SIRET, c’est un simple relevé.'),
                    Toggle::make('assujetti_tva')
                        ->label('Le commerce facture la TVA'),
                ])
                ->columnSpanFull()
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Point relais')
                    ->weight('bold')
                    ->description(fn ($record) => $record->city)
                    ->searchable(['name', 'city']),
                TextColumn::make('territoire')
                    ->label('Territoire')
                    ->badge()
                    ->sortable(),
                TextColumn::make('partenaire_enseigne')
                    ->label('Partenaire')
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => '🤝 ' . $state),
                TextColumn::make('manager.email')
                    ->label('Contact')
                    ->default('—')
                    ->description(fn ($record) => $record->contact_phone)
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextColumn::make('a_verser')
                    ->label('À verser')
                    ->state(fn (RelayPoint $record) => \App\Support\VersementsRelais::soldes($record->id)['a_verser'])
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', ' ') . ' €')
                    ->badge()
                    ->color(fn ($state) => (float) $state >= (float) config('relais.versement_minimum', 10) ? 'warning' : 'gray')
                    ->description(fn (RelayPoint $record) => $record->charteAJour() ? 'Charte ✓' : 'Charte à accepter'),
                TextColumn::make('transactions_count')
                    ->label('Colis')
                    ->counts('transactions')
                    ->badge()
                    ->color('info'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('territoire')
                    ->label('Territoire')
                    ->options(array_combine(DomTomGeo::territoires(), DomTomGeo::territoires())),
                TernaryFilter::make('is_active')
                    ->label('Actif'),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('rapport')
                    ->label('Rapport d’impact')
                    ->icon('heroicon-o-document-chart-bar')
                    ->iconButton()
                    ->tooltip('Rapport d’impact à envoyer à l’enseigne (lien privé)')
                    ->url(fn (RelayPoint $record) => $record->lienRapport(), shouldOpenInNewTab: true),
                \Filament\Actions\Action::make('releve')
                    ->label('Relevé du mois')
                    ->icon('heroicon-o-document-arrow-down')
                    ->iconButton()
                    ->tooltip('Relevé mensuel / autofacture (PDF)')
                    ->modalHeading('Relevé mensuel du point relais')
                    ->modalSubmitActionLabel('Télécharger')
                    ->schema([
                        Select::make('mois')
                            ->label('Mois')
                            ->options(fn () => self::optionsMois())
                            ->default(now()->subMonthNoOverflow()->format('Y-m'))
                            ->required(),
                    ])
                    ->action(function (RelayPoint $record, array $data) {
                        $mois = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $data['mois'] . '-01')->startOfMonth();
                        \App\Support\VersementsRelais::enregistrer($record, $mois);

                        return response()->streamDownload(
                            fn () => print(\App\Support\VersementsRelais::pdf($record, $mois)),
                            \App\Support\VersementsRelais::nomFichier($record, $mois),
                            ['Content-Type' => 'application/pdf'],
                        );
                    }),
                \Filament\Actions\Action::make('verse')
                    ->label('Marquer versé')
                    ->icon('heroicon-o-banknotes')
                    ->iconButton()
                    ->tooltip('Noter le virement du mois')
                    ->modalHeading('Noter un virement')
                    ->modalDescription('Fais d’abord le virement depuis ta banque, puis note-le ici : le commerçant le voit dans son espace relais.')
                    ->schema([
                        Select::make('mois')
                            ->label('Mois payé')
                            ->options(fn () => self::optionsMois())
                            ->default(now()->subMonthNoOverflow()->format('Y-m'))
                            ->required(),
                        TextInput::make('reference')
                            ->label('Référence du virement (facultatif)')
                            ->maxLength(120),
                        TextInput::make('retenue')
                            ->label('Retenue (colis perdu par sa faute), en €')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Charte, article 8 : ' . number_format((float) config('relais.plafond_responsabilite', 100), 0, ',', ' ') . ' € maximum par colis. Préviens le commerçant avant. La retenue ne dépasse jamais les commissions du mois : reporte le reste le mois suivant.'),
                        TextInput::make('retenue_motif')
                            ->label('Motif de la retenue')
                            ->placeholder('Ex : vente n° 123, colis égaré')
                            ->maxLength(255),
                    ])
                    ->action(function (RelayPoint $record, array $data) {
                        $mois = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $data['mois'] . '-01')->startOfMonth();
                        $v = \App\Support\VersementsRelais::marquerVerse($record, $mois, $data['reference'] ?? null, (float) ($data['retenue'] ?? 0), $data['retenue_motif'] ?? null);
                        \Filament\Notifications\Notification::make()
                            ->title('Virement noté : ' . number_format($v->net(), 2, ',', ' ') . ' € à virer (' . $v->colis . ' colis' . ((float) $v->retenue > 0 ? ', retenue ' . number_format((float) $v->retenue, 2, ',', ' ') . ' €' : '') . ')')
                            ->success()->send();
                    }),
                EditAction::make()->iconButton()->tooltip('Modifier'),
                DeleteAction::make()->iconButton()->tooltip('Supprimer'),
            ]);
    }

    /** Les 12 derniers mois (le mois en cours en premier). */
    private static function optionsMois(): array
    {
        $options = [];
        for ($i = 0; $i < 12; $i++) {
            $m = now()->startOfMonth()->subMonths($i);
            $options[$m->format('Y-m')] = ucfirst($m->locale('fr')->translatedFormat('F Y'));
        }

        return $options;
    }

    public static function getNavigationLabel(): string
    {
        return 'Points relais';
    }

    public static function getModelLabel(): string
    {
        return 'point relais';
    }

    public static function getPluralModelLabel(): string
    {
        return 'points relais';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Points relais';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelayPoints::route('/'),
            'create' => CreateRelayPoint::route('/create'),
            'edit' => EditRelayPoint::route('/{record}/edit'),
        ];
    }
}
