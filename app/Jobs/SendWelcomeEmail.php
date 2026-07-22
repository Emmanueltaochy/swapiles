<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $userId
    ) {
    }

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user || !$user->email) {
            return;
        }

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addDays(7),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $name = e($user->name ?: 'et bienvenue');
        $verifyUrl = e($verifyUrl);
        $searchUrl = e(route('search'));
        $depositUrl = e(url('/deposer-une-annonce'));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:600px;margin:0 auto;padding:28px 16px;">
  <div style="background:#fff;border-radius:24px;overflow:hidden;border:1px solid #e5e7eb;">
    <div style="padding:30px;text-align:center;background:#0f766e;color:#fff;">
      <div style="font-size:30px;font-weight:900;">Swap'Îles 🌴</div>
      <div style="font-size:14px;margin-top:6px;">La marketplace seconde main des îles</div>
    </div>
    <div style="padding:30px;">
      <h1 style="font-size:23px;margin:0 0 16px;">Bienvenue {$name} ! 🎉</h1>
      <p style="font-size:16px;line-height:1.7;color:#374151;margin:0 0 16px;">
        Votre compte Swap'Îles est créé. Vous pouvez dès maintenant acheter, vendre, échanger et donner
        entre les îles, en toute sécurité.
      </p>

      <div style="text-align:center;margin:26px 0;">
        <a href="{$verifyUrl}" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;font-weight:800;padding:14px 26px;border-radius:14px;">
          Confirmer mon adresse e-mail
        </a>
      </div>
      <p style="font-size:13px;line-height:1.6;color:#6b7280;margin:0 0 22px;text-align:center;">
        Confirmer votre adresse renforce la sécurité de votre compte (facultatif, mais recommandé).
      </p>

      <div style="border-top:1px solid #e5e7eb;padding-top:20px;">
        <p style="font-size:15px;font-weight:700;margin:0 0 12px;">Pour commencer :</p>
        <p style="font-size:15px;line-height:1.7;color:#374151;margin:0;">
          🔍 <a href="{$searchUrl}" style="color:#0f766e;font-weight:700;">Explorer les annonces</a> près de chez vous<br>
          📸 <a href="{$depositUrl}" style="color:#0f766e;font-weight:700;">Déposer votre première annonce</a> (c'est gratuit)
        </p>
      </div>
    </div>
    <div style="padding:22px 30px;background:#f9fafb;color:#6b7280;font-size:13px;line-height:1.6;text-align:center;">
      À très vite sur les îles,<br>
      <strong>L'équipe Swap'Îles</strong><br>
      <a href="https://swapiles.com" style="color:#0f766e;">swapiles.com</a>
    </div>
  </div>
</div>
</body>
</html>
HTML;

        Mail::html($html, function ($message) use ($user) {
            $message->from('contact@swapiles.com', "Swap'Îles")
                ->to($user->email)
                ->subject("Bienvenue sur Swap'Îles 🌴");
        });
    }
}
