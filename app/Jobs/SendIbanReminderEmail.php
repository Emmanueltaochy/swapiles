<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Point 10 : relance ciblée « il te manque juste ton IBAN ». Envoyée UNIQUEMENT
 * sur action admin explicite (jamais automatiquement). Le lien mène au
 * portefeuille, d'où le vendeur lance l'ajout de son IBAN (onboarding Stripe).
 */
class SendIbanReminderEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public int $userId)
    {
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user || blank($user->email)) {
            return;
        }

        // Sécurité : on ne relance QUE les comptes réellement dans ce cas
        // (identité OK, versements pas encore actifs).
        if (!$user->stripe_account_id || !$user->stripe_charges_enabled || $user->stripe_payouts_enabled) {
            return;
        }

        $url = route('account.wallet.index');

        $subject = '💶 Il te manque juste ton IBAN pour être payé sur Swap’Îles';

        $body = "Bonjour " . ($user->name ?: '') . ",\n\n"
            . "Bonne nouvelle : ton compte vendeur est presque prêt, ton identité est validée ✅.\n"
            . "Il ne manque plus que ton IBAN (ton compte bancaire) pour être payé automatiquement dès qu'un acheteur règle par carte.\n\n"
            . "Ajoute ton IBAN en 2 minutes ici : " . $url . "\n\n"
            . "Une fois fait, tes annonces passent en paiement sécurisé et tu es payé après chaque remise confirmée — zéro impayé, zéro avance.\n\n"
            . "L'équipe Swap'Îles\nhttps://swapiles.com";

        Mail::raw($body, function ($mail) use ($user, $subject) {
            $mail->from('contact@swapiles.com', "Swap'Îles")
                ->to($user->email)
                ->subject($subject);
        });
    }
}
