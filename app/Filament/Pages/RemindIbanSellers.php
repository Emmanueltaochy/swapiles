<?php

namespace App\Filament\Pages;

use App\Jobs\SendIbanReminderEmail;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Point 10 : relancer les vendeurs « identité OK mais IBAN manquant »
 * (charges_enabled = true, payouts_enabled = false). L'envoi est déclenché
 * UNIQUEMENT par un clic admin explicite (bouton + confirmation). Rien n'est
 * envoyé automatiquement.
 */
class RemindIbanSellers extends Page
{
    protected static ?string $navigationLabel = 'Relance IBAN';

    protected static ?string $title = 'Relance des vendeurs sans IBAN';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.remind-iban-sellers';

    public static function targets()
    {
        return User::whereNotNull('stripe_account_id')
            ->where('stripe_charges_enabled', true)
            ->where('stripe_payouts_enabled', false)
            ->orderBy('id')
            ->get(['id', 'name', 'email']);
    }

    public function getViewData(): array
    {
        return ['targets' => self::targets()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('Envoyer la relance IBAN')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Envoyer la relance IBAN ?')
                ->modalDescription(fn () => 'DKIM / SPF / DMARC validés en prod ? Sinon ces '
                    . self::targets()->count()
                    . ' e-mails partent en spam et la liste est grillée. De vrais e-mails partent maintenant.')
                ->modalSubmitActionLabel('Oui, DNS OK — envoyer')
                ->action(function () {
                    $count = 0;
                    foreach (self::targets() as $u) {
                        SendIbanReminderEmail::dispatch($u->id);
                        $count++;
                    }

                    Notification::make()
                        ->title("Relance envoyée à {$count} vendeur(s).")
                        ->success()
                        ->send();
                }),
        ];
    }
}
