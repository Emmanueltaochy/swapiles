<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Users\UserResource;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Signalements de contenu émis par les membres (annonces, membres, messages).
 * Exigence des stores Apple/Google pour toute app avec du contenu utilisateur.
 */
class ReportResource extends Resource
{
    use \App\Filament\Concerns\TitresEnFrancais;

    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Signalé le')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Report $r) => optional($r->created_at)->diffForHumans())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('État')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'open' => 'À traiter',
                        'reviewed' => 'Traité',
                        'dismissed' => 'Rejeté',
                        default => $state ?: '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'open' => 'danger',
                        'reviewed' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('reason')
                    ->label('Motif')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Report::REASONS[$state] ?? $state ?? '—')
                    ->color('warning'),

                TextColumn::make('reportable_type')
                    ->label('Cible')
                    ->formatStateUsing(fn (Report $r) => static::targetLabel($r))
                    ->color('primary')
                    ->limit(40)
                    ->tooltip(fn (Report $r) => static::targetLabel($r))
                    ->description(fn (Report $r) => $r->details ? \Illuminate\Support\Str::limit($r->details, 45) . ' — toucher pour tout voir' : 'Toucher pour tout voir'),

                TextColumn::make('reporter.name')
                    ->label('Signalé par')
                    ->default('—')
                    ->description(fn (Report $r) => $r->reporter?->email)
                    ->searchable()
                    ->url(fn (Report $r) => $r->reporter_id ? UserResource::getUrl('view', ['record' => $r->reporter_id]) : null),

            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('État')
                    ->options([
                        'open' => 'À traiter',
                        'reviewed' => 'Traité',
                        'dismissed' => 'Rejeté',
                    ])
                    ->default('open'),

                SelectFilter::make('reason')
                    ->label('Motif')
                    ->options(Report::REASONS),
            ])
            // Toucher une ligne ouvre la fiche complète du signalement.
            ->recordAction('voir')
            ->recordUrl(null)
            ->recordActions([
                Action::make('voir')
                    ->label('Voir tout')
                    ->tooltip('Voir tout le signalement')
                    ->iconButton()
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn (Report $r) => 'Signalement du ' . optional($r->created_at)->format('d/m/Y à H:i'))
                    ->modalWidth('3xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->modalContent(fn (Report $r) => view('filament.reports.fiche', static::fiche($r)))
                    ->extraModalFooterActions(fn (Report $r) => [
                        Action::make('ficheTraite')
                            ->label('Marquer traité')
                            ->color('success')
                            ->icon('heroicon-o-check-circle')
                            ->visible($r->status !== 'reviewed')
                            ->cancelParentActions()
                            ->action(fn () => $r->update(['status' => 'reviewed', 'handled_at' => now()])),
                        Action::make('ficheRejete')
                            ->label('Rejeter')
                            ->color('gray')
                            ->icon('heroicon-o-x-circle')
                            ->visible($r->status !== 'dismissed')
                            ->cancelParentActions()
                            ->action(fn () => $r->update(['status' => 'dismissed', 'handled_at' => now()])),
                        Action::make('ficheBannir')
                            ->label('Bannir le membre signalé')
                            ->color('danger')
                            ->icon('heroicon-o-no-symbol')
                            ->visible(fn () => ($m = static::membreVise($r)) && ! $m->is_banned)
                            ->requiresConfirmation()
                            ->modalHeading('Bannir ce membre ?')
                            ->modalDescription(fn () => (static::membreVise($r)?->name ?? 'Ce membre') . ' sera déconnecté, ne pourra plus accéder à son compte, et son e-mail sera bloqué. Le signalement passera en « Traité ».')
                            ->cancelParentActions()
                            ->action(fn () => static::bannir($r)),
                    ]),

                Action::make('reviewed')
                    ->label('Marquer traité')
                    ->tooltip('Marquer traité')
                    ->iconButton()
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Report $r) => $r->status !== 'reviewed')
                    ->action(fn (Report $r) => $r->update(['status' => 'reviewed', 'handled_at' => now()])),

                Action::make('dismissed')
                    ->label('Rejeter')
                    ->tooltip('Rejeter le signalement')
                    ->iconButton()
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (Report $r) => $r->status !== 'dismissed')
                    ->action(fn (Report $r) => $r->update(['status' => 'dismissed', 'handled_at' => now()])),
            ]);
    }

    /**
     * Membre visé par le signalement : le membre lui-même, le vendeur de
     * l'annonce, ou l'auteur du message.
     */
    public static function membreVise(Report $r): ?User
    {
        $cible = $r->reportable;

        return match (true) {
            $cible instanceof User => $cible,
            $cible instanceof Listing => $cible->user,
            $cible instanceof \App\Models\Message => $cible->sender,
            default => null,
        };
    }

    /** Bannit le membre visé (et bloque son e-mail), puis clôt le signalement. */
    public static function bannir(Report $r): void
    {
        $membre = static::membreVise($r);
        if (! $membre || $membre->is_banned) {
            return;
        }

        $membre->update(['is_banned' => true]);
        \App\Models\BlockedEmail::block($membre->email, 'Membre banni (signalement #' . $r->id . ')', optional(auth()->user())->id);
        $r->update(['status' => 'reviewed', 'handled_at' => now()]);

        \Filament\Notifications\Notification::make()->title('Membre banni et e-mail bloqué')->success()->send();
    }

    /** Tout ce qu'il faut pour juger un signalement, en un seul écran. */
    public static function fiche(Report $r): array
    {
        $r->loadMissing(['reporter', 'reportable']);
        $cible = $r->reportable;
        $membre = static::membreVise($r);
        $auteur = $r->reporter;

        $profil = function (?User $u): ?array {
            if (! $u) {
                return null;
            }

            $annonces = \App\Models\Listing::where('user_id', $u->id);
            $signalements = Report::query()
                ->where(fn ($q) => $q->where(fn ($x) => $x->where('reportable_type', User::class)->where('reportable_id', $u->id))
                    ->orWhere(fn ($x) => $x->where('reportable_type', Listing::class)->whereIn('reportable_id', \App\Models\Listing::where('user_id', $u->id)->select('id'))))
                ->count();

            return [
                'id' => $u->id,
                'nom' => $u->name,
                'email' => $u->email,
                'telephone' => $u->phone,
                'ville' => trim(($u->postal_code ?? '') . ' ' . ($u->city ?? '')) ?: null,
                'inscrit' => optional($u->created_at)->format('d/m/Y'),
                'anciennete' => optional($u->created_at)->diffForHumans(),
                'banni' => (bool) $u->is_banned,
                'pro' => (bool) $u->is_pro,
                'annonces' => (clone $annonces)->count(),
                'ventes' => rescue(fn () => $u->sales()->count(), 0, false),
                'achats' => rescue(fn () => $u->purchases()->count(), 0, false),
                'signalements_recus' => $signalements,
                'messages_signales' => \App\Models\Message::where('sender_id', $u->id)->whereNotNull('flagged_at')->count(),
                'url_admin' => UserResource::getUrl('view', ['record' => $u->id]),
                'url_profil' => rescue(fn () => route('profiles.show', $u), null, false),
            ];
        };

        // Autres signalements visant la même personne (ou ses annonces).
        $autres = collect();
        if ($membre) {
            $autres = Report::query()
                ->with('reporter')
                ->where('id', '!=', $r->id)
                ->where(fn ($q) => $q->where(fn ($x) => $x->where('reportable_type', User::class)->where('reportable_id', $membre->id))
                    ->orWhere(fn ($x) => $x->where('reportable_type', Listing::class)->whereIn('reportable_id', \App\Models\Listing::where('user_id', $membre->id)->select('id'))))
                ->latest()
                ->limit(10)
                ->get();
        }

        // Échanges entre la personne qui signale et le membre visé.
        $messages = collect();
        if ($membre && $auteur) {
            $messages = \App\Models\Message::query()
                ->with(['sender', 'listing'])
                ->where(fn ($q) => $q->where(fn ($x) => $x->where('sender_id', $auteur->id)->where('receiver_id', $membre->id))
                    ->orWhere(fn ($x) => $x->where('sender_id', $membre->id)->where('receiver_id', $auteur->id)))
                ->latest()
                ->limit(30)
                ->get()
                ->reverse()
                ->values();
        }

        $annonce = null;
        if ($cible instanceof Listing) {
            $annonce = [
                'titre' => $cible->title,
                'prix' => $cible->price,
                'statut' => $cible->status,
                'description' => \Illuminate\Support\Str::limit((string) $cible->description, 600),
                'url' => route('listings.show', $cible->id),
            ];
        }

        $messageVise = $cible instanceof \App\Models\Message ? $cible : null;

        return [
            'report' => $r,
            'motif' => $r->reasonLabel(),
            'etat' => match ($r->status) {
                'open' => 'À traiter',
                'reviewed' => 'Traité',
                'dismissed' => 'Rejeté',
                default => $r->status,
            },
            'cibleLibelle' => static::targetLabel($r),
            'annonce' => $annonce,
            'messageVise' => $messageVise,
            'membre' => $profil($membre),
            'auteur' => $profil($auteur),
            'autres' => $autres,
            'messages' => $messages,
            'membreId' => $membre?->id,
        ];
    }

    /** Libellé lisible de la cible signalée (annonce ou membre). */
    protected static function targetLabel(Report $r): string
    {
        return match ($r->reportable_type) {
            Listing::class => '📦 Annonce : ' . (optional($r->reportable)->title ?? '#' . $r->reportable_id),
            User::class => '👤 Membre : ' . (optional($r->reportable)->name ?? '#' . $r->reportable_id),
            \App\Models\Message::class => '💬 Message #' . $r->reportable_id,
            default => class_basename($r->reportable_type) . ' #' . $r->reportable_id,
        };
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['reporter', 'reportable']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Signalements';
    }

    public static function getModelLabel(): string
    {
        return 'signalement';
    }

    public static function getPluralModelLabel(): string
    {
        return 'signalements';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Modération';
    }

    /** Pastille : nombre de signalements à traiter. */
    public static function getNavigationBadge(): ?string
    {
        $count = Report::where('status', 'open')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
        ];
    }
}
