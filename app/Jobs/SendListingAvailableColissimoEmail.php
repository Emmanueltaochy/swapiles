<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail à un acheteur qui avait signalé son intérêt : l'article est désormais
 * livrable en Colissimo, il peut l'acheter (et le retrouve dans ses favoris).
 */
class SendListingAvailableColissimoEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $listingId,
        public int $buyerId,
    ) {
    }

    public function handle(): void
    {
        $listing = Listing::find($this->listingId);
        $buyer = User::find($this->buyerId);

        if (! $listing || ! $buyer || ! $buyer->email) {
            return;
        }

        $title = e($listing->title);
        $url = e(route('listings.show', $listing));
        $favUrl = e(url('/favoris'));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:600px;margin:0 auto;padding:28px 16px;">
  <div style="background:#fff;border-radius:24px;overflow:hidden;border:1px solid #e5e7eb;">
    <div style="padding:28px;text-align:center;background:#0f766e;color:#fff;">
      <div style="font-size:28px;font-weight:900;">Swap'Îles 🌴</div>
      <div style="font-size:14px;margin-top:6px;">Bonne nouvelle !</div>
    </div>
    <div style="padding:30px;">
      <h1 style="font-size:22px;margin:0 0 14px;">C'est disponible ! 🎉</h1>
      <p style="font-size:16px;line-height:1.7;color:#374151;margin:0 0 16px;">
        L'article <strong>« {$title} »</strong> que vous vouliez est maintenant
        <strong>disponible en livraison Colissimo</strong> — vous pouvez l'acheter et le faire livrer sur votre île !
      </p>

      <div style="text-align:center;margin:24px 0;">
        <a href="{$url}" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;font-weight:800;padding:14px 26px;border-radius:14px;">
          Voir l'article & acheter
        </a>
      </div>
      <p style="font-size:14px;line-height:1.6;color:#6b7280;text-align:center;margin:0;">
        Vous le retrouvez aussi dans <a href="{$favUrl}" style="color:#0f766e;">vos favoris ❤️</a>.
      </p>
    </div>
    <div style="padding:22px 30px;background:#f9fafb;color:#6b7280;font-size:13px;text-align:center;">
      <strong>L'équipe Swap'Îles</strong><br>
      <a href="https://swapiles.com" style="color:#0f766e;">swapiles.com</a>
    </div>
  </div>
</div>
</body>
</html>
HTML;

        Mail::html($html, function ($message) use ($buyer) {
            $message->from('contact@swapiles.com', "Swap'Îles")
                ->to($buyer->email)
                ->subject('🎉 L\'article que vous vouliez est disponible en livraison !');
        });
    }
}
