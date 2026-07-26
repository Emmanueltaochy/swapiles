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

        $subject = 'Ton compte vendeur Swap’îles est presque prêt';

        $body = "Bonjour " . ($user->name ?: '') . ",\n\n"
            . "Bonne nouvelle : ton compte vendeur est presque prêt, ton identité est validée.\n"
            . "Il ne manque plus que ton IBAN (ton compte bancaire) pour être payé automatiquement dès qu'un acheteur règle par carte.\n\n"
            . "Et une info qui change tout : la commission vendeur est passée à 0 %. Tu reçois désormais 100 % du prix affiché sur ton annonce.\n\n"
            . "Ajoute ton IBAN en 2 minutes ici : " . $url . "\n\n"
            . "Avec le paiement sécurisé Swap'îles, tu es payé avant même de remettre l'article.\n\n"
            . "L'équipe Swap'îles\nhttps://swapiles.com";

        Mail::raw($body, function ($mail) use ($user, $subject) {
            $mail->from('contact@swapiles.com', "Swap'Îles")
                ->to($user->email)
                ->subject($subject);
        });
    }
}
