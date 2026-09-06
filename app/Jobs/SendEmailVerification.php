<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * E-mail de CONFIRMATION D'ADRESSE, envoyé seul et sans fioriture.
 *
 * Il était auparavant noyé dans l'e-mail de bienvenue, en HTML uniquement.
 * Un e-mail sans version texte, chargé de mise en forme et de liens marketing,
 * part très facilement en indésirable : beaucoup de membres n'ont jamais pu
 * confirmer leur adresse. Ici : un objet clair, une version texte ET une
 * version HTML, un seul lien.
 */
class SendEmailVerification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public int $userId)
    {
    }

    /** Lien signé valable 7 jours. */
    public static function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addDays(7),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->email || $user->email_verified_at) {
            return;
        }

        $url = self::verificationUrl($user);
        $prenom = trim((string) $user->name) !== '' ? trim((string) $user->name) : 'bonjour';

        $texte = <<<TXT
Bonjour {$prenom},

Pour confirmer votre adresse e-mail sur Swap'Îles, ouvrez ce lien :

{$url}

Ce lien est valable 7 jours. Si vous n'êtes pas à l'origine de cette
inscription, ignorez simplement ce message.

L'équipe Swap'Îles
https://swapiles.com
TXT;

        $urlHtml = e($url);
        $prenomHtml = e($prenom);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:28px;">
  <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Bonjour {$prenomHtml},</p>
  <p style="font-size:16px;line-height:1.6;margin:0 0 22px;">
    Pour confirmer votre adresse e-mail sur Swap'Îles, cliquez sur le bouton ci-dessous.
  </p>
  <p style="text-align:center;margin:0 0 22px;">
    <a href="{$urlHtml}" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;font-weight:700;padding:14px 26px;border-radius:12px;">
      Confirmer mon adresse e-mail
    </a>
  </p>
  <p style="font-size:13px;line-height:1.6;color:#6b7280;margin:0 0 8px;">
    Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :
  </p>
  <p style="font-size:13px;line-height:1.6;color:#0f766e;word-break:break-all;margin:0 0 22px;">{$urlHtml}</p>
  <p style="font-size:13px;line-height:1.6;color:#6b7280;margin:0;">
    Ce lien est valable 7 jours. Si vous n'êtes pas à l'origine de cette inscription, ignorez ce message.
  </p>
</div>
<p style="max-width:560px;margin:16px auto 0;font-size:12px;color:#9ca3af;text-align:center;">
  L'équipe Swap'Îles — <a href="https://swapiles.com" style="color:#9ca3af;">swapiles.com</a>
</p>
</body>
</html>
HTML;

        // Version texte + version HTML : c'est ce couple qui fait passer
        // l'e-mail en boîte de réception plutôt qu'en indésirable.
        Mail::html($html, function ($message) use ($user, $texte) {
            $message->from('contact@swapiles.com', "Swap'Îles")
                ->to($user->email)
                ->subject('Confirmez votre adresse e-mail')
                ->text($texte);
        });
    }

    /** Trace l'échec dans les logs : un e-mail de confirmation perdu ne doit plus passer inaperçu. */
    public function failed(\Throwable $e): void
    {
        Log::error("Envoi de l'e-mail de confirmation impossible (membre #{$this->userId}) : " . $e->getMessage());
    }
}
