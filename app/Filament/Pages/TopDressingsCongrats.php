<?php

namespace App\Filament\Pages;

use App\Jobs\SendTopDressingsCongratsEmails;
use App\Support\DressingLeaderboard;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Admin : aperçu du Top des dressings + bouton d'envoi de l'e-mail de
 * félicitations à ces vendeurs (avec le lien vers le classement public).
 */
class TopDressingsCongrats extends Page
{
    protected static ?string $navigationLabel = 'Féliciter le Top dressings';
    protected static ?string $title = 'Féliciter le Top des dressings';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';
    protected static ?int $navigationSort = 32;

    protected string $view = 'filament.pages.top-dressings-congrats';

    public static function getNavigationGroup(): ?string
    {
        return 'Communauté';
    }

    protected function getHeaderActions(): array
    {
        $count = DressingLeaderboard::top()->count();

        return [
            Action::make('sendCongrats')
                ->label($count > 0 ? "Envoyer les félicitations ({$count})" : 'Aucun dressing à féliciter')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->disabled($count === 0)
                ->requiresConfirmation()
                ->modalHeading('Envoyer l’e-mail de félicitations ?')
                ->modalDescription("Un e-mail « Bravo, tu es dans le Top {$count} » sera envoyé à chacun de ces {$count} vendeurs, avec le lien vers le classement. ⚠️ Le DKIM n’est pas encore vérifié : l’e-mail peut tomber en spam. À envoyer en connaissance de cause.")
                ->modalSubmitActionLabel('Envoyer maintenant')
                ->action(function () {
                    SendTopDressingsCongratsEmails::dispatch();

                    Notification::make()
                        ->title('Envoi lancé 🎉')
                        ->body('Les e-mails de félicitations partent vers le Top des dressings.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getViewData(): array
    {
        return [
            'top' => DressingLeaderboard::top(),
        ];
    }
}
