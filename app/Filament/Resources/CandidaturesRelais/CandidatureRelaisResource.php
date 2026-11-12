<?php

namespace App\Filament\Resources\CandidaturesRelais;

use App\Filament\Resources\CandidaturesRelais\Pages\ListCandidaturesRelais;
use App\Models\CandidatureRelais;
use App\Models\RelayPoint;
use App\Models\User;
use App\Support\DomTomGeo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Commerçants qui veulent DEVENIR point relais (formulaire « Devenir point relais »). */
class CandidatureRelaisResource extends Resource
{
    use \App\Filament\Concerns\TitresEnFrancais;

    protected static ?string $model = CandidatureRelais::class;

    protected static ?string $slug = 'candidatures-relais';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Commerces candidats';

    protected static ?string $modelLabel = 'commerce candidat';

    protected static ?string $pluralModelLabel = 'commerces candidats';

    protected static string | \UnitEnum | null $navigationGroup = 'Points relais';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Reçue le')->date('d/m/Y')->sortable(),
                TextColumn::make('commerce')->label('Commerce')->weight('bold')->searchable()
                    ->description(fn (CandidatureRelais $r) => collect([$r->ville, $r->territoire])->filter()->implode(' · ')),
                TextColumn::make('contact')->label('Contact')->searchable()->placeholder('—')
                    ->description(fn (CandidatureRelais $r) => $r->user_id ? 'A un compte Swap’Îles' : 'Pas encore de compte'),
                TextColumn::make('telephone')->label('Téléphone')->placeholder('—')
                    ->url(fn (CandidatureRelais $r) => $r->telephone ? 'tel:' . preg_replace('/[^0-9+]/', '', $r->telephone) : null),
                TextColumn::make('email')->label('E-mail')->placeholder('—')->toggleable(),
                TextColumn::make('horaires')->label('Horaires')->placeholder('à demander')->limit(40)->toggleable(),
                TextColumn::make('message')->label('Message')->placeholder('—')->limit(60)->wrap()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('statut')->label('Suivi')->badge()
                    ->formatStateUsing(fn (?string $state) => CandidatureRelais::STATUTS[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'nouvelle' => 'warning', 'contactee', 'infos_attendues' => 'info', 'point_relais_cree' => 'success', default => 'gray',
                    }),
                TextColumn::make('notes')->label('Notes')->placeholder('—')->limit(60)->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('statut')->label('Suivi')->options(CandidatureRelais::STATUTS),
                SelectFilter::make('territoire')->label('Île')->options(array_combine(DomTomGeo::territoires(), DomTomGeo::territoires())),
            ])
            ->recordActions([
                Action::make('creerRelais')
                    ->label('Créer le point relais')
                    ->icon('heroicon-o-map-pin')
                    ->color('success')
                    ->visible(fn (CandidatureRelais $r) => ! $r->relay_point_id)
                    ->modalDescription('Le point relais apparaît sur la carte dès qu’il est actif. À sa première connexion, le commerçant accepte la charte.')
                    ->fillForm(fn (CandidatureRelais $r) => [
                        'name' => $r->commerce,
                        'compte_email' => $r->user?->email ?? $r->email,
                        'territoire' => $r->territoire ?: 'La Réunion',
                        'city' => $r->ville,
                        'contact_name' => $r->contact,
                        'contact_phone' => $r->telephone,
                        'opening_hours' => $r->horaires,
                        'is_active' => true,
                    ])
                    ->schema([
                        TextInput::make('name')->label('Nom du point relais')->required()->maxLength(191),
                        TextInput::make('compte_email')->label('E-mail du compte Swap’Îles du commerçant')->email()
                            ->rule(fn () => function (string $attribut, $valeur, \Closure $echec) {
                                if (filled($valeur) && ! User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($valeur))])->exists()) {
                                    $echec('Aucun compte Swap’Îles avec cet e-mail : le commerçant doit d’abord créer son compte.');
                                }
                            })
                            ->helperText('Le compte qui valide les dépôts et retraits. Laissez vide si le commerçant n’a pas encore de compte.'),
                        Select::make('territoire')->label('Île')->options(array_combine(DomTomGeo::territoires(), DomTomGeo::territoires()))->required()->native(false),
                        TextInput::make('address')->label('Adresse')->required()->maxLength(191),
                        TextInput::make('postal_code')->label('Code postal')->required()->maxLength(10),
                        TextInput::make('city')->label('Ville')->required()->maxLength(120),
                        TextInput::make('contact_name')->label('Contact')->maxLength(120),
                        TextInput::make('contact_phone')->label('Téléphone')->maxLength(40),
                        TextInput::make('opening_hours')->label('Horaires d’ouverture')->maxLength(191),
                        Toggle::make('is_active')->label('Actif (visible sur la carte)'),
                    ])
                    ->action(function (CandidatureRelais $r, array $data) {
                        $email = mb_strtolower(trim((string) ($data['compte_email'] ?? '')));
                        unset($data['compte_email']);
                        $data['manager_user_id'] = $email !== '' ? User::whereRaw('LOWER(email) = ?', [$email])->value('id') : null;
                        $relais = RelayPoint::create($data);
                        $r->update(['statut' => 'point_relais_cree', 'relay_point_id' => $relais->id,
                            'notes' => trim(($r->notes ? $r->notes . "\n" : '') . now()->format('d/m/Y') . ' : point relais créé')]);
                        Notification::make()->title('Point relais créé 🏪')->body($relais->name . ' — ' . $relais->city)->success()->send();
                    }),
                Action::make('suivi')
                    ->label('Suivi')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->schema([
                        Select::make('statut')->label('Où en est cette candidature ?')->options(CandidatureRelais::STATUTS)->required(),
                        Textarea::make('notes')->label('Notes')->rows(3),
                    ])
                    ->fillForm(fn (CandidatureRelais $r) => ['statut' => $r->statut, 'notes' => $r->notes])
                    ->action(fn (CandidatureRelais $r, array $data) => $r->update($data)),
                Action::make('ecrire')
                    ->label('Écrire')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn (CandidatureRelais $r) => filled($r->email))
                    ->url(fn (CandidatureRelais $r) => 'mailto:' . $r->email . '?subject=' . rawurlencode('Re: Point relais partenaire — ' . $r->commerce . ($r->ville ? ' (' . $r->ville . ')' : ''))),
                DeleteAction::make(),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = CandidatureRelais::where('statut', 'nouvelle')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListCandidaturesRelais::route('/')];
    }
}
