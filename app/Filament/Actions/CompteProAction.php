<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Support\ComptePro;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;

/**
 * Admin : régler le compte professionnel d'un membre (identité légale, remise,
 * commission) et l'autorisation de lancer des lives. Rien ne s'applique tant
 * que « Compte pro validé » n'est pas coché.
 */
class CompteProAction
{
    public static function make(): Action
    {
        return Action::make('comptePro')
            ->label('Compte pro / lives')
            ->icon('heroicon-o-building-storefront')
            ->color('info')
            ->modalHeading(fn (User $record) => 'Compte professionnel — ' . $record->name)
            ->modalDescription('Un pro vend au prix affiché (sans protection acheteur ajoutée) ; Swap’Îles prend sa commission sur ce que reçoit le pro. Rien ne change tant que « Compte pro validé » n’est pas coché.')
            ->modalSubmitActionLabel('Enregistrer')
            ->fillForm(fn (User $record) => [
                'pro_valide' => $record->pro_valide_at !== null,
                'pro_raison_sociale' => $record->pro_raison_sociale,
                'pro_siret' => $record->pro_siret,
                'pro_adresse' => $record->pro_adresse,
                'pro_telephone' => $record->pro_telephone,
                'pro_commission_pct' => $record->pro_commission_taux !== null ? round((float) $record->pro_commission_taux * 100, 2) : null,
                'pro_retrait_boutique' => (bool) $record->pro_retrait_boutique,
                'pro_livraison' => (bool) $record->pro_livraison,
                'pro_livraison_prix' => $record->pro_livraison_prix,
                'pro_livraison_delai' => $record->pro_livraison_delai,
                'pro_retours' => $record->pro_retours,
                'live_autorise' => (bool) $record->live_autorise,
            ])
            ->schema([
                Section::make('Ventes en direct')->schema([
                    Toggle::make('live_autorise')->label('Autorisé à lancer des lives')
                        ->helperText('Les autres membres peuvent seulement regarder.'),
                ]),
                Section::make('Identité du professionnel')->schema([
                    Toggle::make('pro_valide')->label('Compte pro validé')
                        ->helperText('À cocher une fois le SIRET vérifié (annuaire-entreprises.data.gouv.fr).'),
                    TextInput::make('pro_raison_sociale')->label('Raison sociale')->maxLength(160)
                        ->requiredIf('pro_valide', true),
                    TextInput::make('pro_siret')->label('SIRET (14 chiffres)')->maxLength(20)
                        ->requiredIf('pro_valide', true)
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (filled($value) && ! ComptePro::siretValide($value)) {
                                $fail('Ce SIRET n’est pas valide (14 chiffres, clé de contrôle).');
                            }
                        }),
                    TextInput::make('pro_adresse')->label('Adresse de la boutique')->maxLength(255)
                        ->helperText('Affichée sur les annonces et utilisée pour le retrait en boutique.'),
                    TextInput::make('pro_telephone')->label('Téléphone de la boutique')->tel()->maxLength(40),
                    TextInput::make('pro_commission_pct')->label('Commission Swap’Îles (%)')->numeric()->minValue(0)->maxValue(50)
                        ->placeholder(str_replace('.', ',', round((float) config('pricing.pro_commission_rate', 0.05) * 100, 2)) . ' (par défaut)')
                        ->helperText('Vide = taux par défaut. Prise sur le prix de l’article, invisible pour l’acheteur.'),
                ]),
                Section::make('Remise des commandes')->schema([
                    Toggle::make('pro_retrait_boutique')->label('Retrait gratuit en boutique (avec code)'),
                    Toggle::make('pro_livraison')->label('Livraison par la boutique')->live(),
                    TextInput::make('pro_livraison_prix')->label('Prix de la livraison (€) — 0 = offerte')->numeric()->minValue(0)->maxValue(500)
                        ->visible(fn ($get) => (bool) $get('pro_livraison')),
                    TextInput::make('pro_livraison_delai')->label('Délai annoncé (ex. « 48 h »)')->maxLength(80)
                        ->visible(fn ($get) => (bool) $get('pro_livraison')),
                    Textarea::make('pro_retours')->label('Conditions de retour du vendeur (facultatif)')->rows(3)->maxLength(1000)
                        ->helperText('Ne peut pas réduire les droits légaux : rétractation 14 jours, garantie de conformité.'),
                ]),
            ])
            ->action(function (User $record, array $data) {
                $valide = (bool) ($data['pro_valide'] ?? false);
                $record->forceFill([
                    'pro_raison_sociale' => $data['pro_raison_sociale'] ?: null,
                    'pro_siret' => filled($data['pro_siret'] ?? null) ? preg_replace('/\D/', '', $data['pro_siret']) : null,
                    'pro_adresse' => $data['pro_adresse'] ?: null,
                    'pro_telephone' => $data['pro_telephone'] ?: null,
                    'pro_commission_taux' => filled($data['pro_commission_pct'] ?? null) ? round(((float) $data['pro_commission_pct']) / 100, 4) : null,
                    'pro_retrait_boutique' => (bool) ($data['pro_retrait_boutique'] ?? false),
                    'pro_livraison' => (bool) ($data['pro_livraison'] ?? false),
                    'pro_livraison_prix' => filled($data['pro_livraison_prix'] ?? null) ? round((float) $data['pro_livraison_prix'], 2) : null,
                    'pro_livraison_delai' => $data['pro_livraison_delai'] ?? null ?: null,
                    'pro_retours' => $data['pro_retours'] ?? null ?: null,
                    'pro_valide_at' => $valide ? ($record->pro_valide_at ?? now()) : null,
                    'is_pro' => $valide ? true : $record->is_pro,
                    'live_autorise' => (bool) ($data['live_autorise'] ?? false),
                ])->save();

                Notification::make()
                    ->title(ComptePro::estPro($record) ? '🏬 Compte pro actif' : 'Enregistré (compte pro non actif)')
                    ->success()->send();
            });
    }
}
