<?php

namespace App\Jobs;

use App\Models\Listing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail envoyé au vendeur juste après la publication d'une annonce pour
 * l'inciter à la partager sur ses réseaux sociaux : c'est la communauté qui
 * fait la promotion de Swap'Îles.
 */
class SendListingPublishedShareEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $listingId
    ) {
    }

    public function handle(): void
    {
        $listing = Listing::with('user')->find($this->listingId);

        if (! $listing || ! $listing->user || ! $listing->user->email) {
            return;
        }

        $seller = $listing->user;
        $url = route('listings.show', $listing);

        $shareText = rawurlencode('Découvrez « ' . $listing->title . ' » sur Swap\'Îles 🌴 ' . $url);
        $shareUrl = rawurlencode($url);

        $waUrl = 'https://wa.me/?text=' . $shareText;
        $fbUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . $shareUrl;
        $xUrl = 'https://twitter.com/intent/tweet?text=' . rawurlencode('Découvrez « ' . $listing->title . ' » sur Swap\'Îles 🌴') . '&url=' . $shareUrl;
        $mailUrl = 'mailto:?subject=' . rawurlencode('À voir sur Swap\'Îles : ' . $listing->title)
            . '&body=' . $shareText;

        $title = e($listing->title);
        $listingUrl = e($url);
        $name = e($seller->name ?: 'à vous');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:600px;margin:0 auto;padding:28px 16px;">
  <div style="background:#fff;border-radius:24px;overflow:hidden;border:1px solid #e5e7eb;">
    <div style="padding:30px;text-align:center;background:#0f766e;color:#fff;">
      <div style="font-size:30px;font-weight:900;">Swap'Îles 🌴</div>
      <div style="font-size:14px;margin-top:6px;">Votre annonce est en ligne !</div>
    </div>
    <div style="padding:30px;">
      <h1 style="font-size:22px;margin:0 0 16px;">Bravo {$name} ! 🎉</h1>
      <p style="font-size:16px;line-height:1.7;color:#374151;margin:0 0 12px;">
        Votre annonce <strong>« {$title} »</strong> est maintenant visible par toute la communauté.
      </p>
      <p style="font-size:16px;line-height:1.7;color:#374151;margin:0 0 20px;">
        👉 <strong>Le secret pour vendre plus vite&nbsp;?</strong> Partagez-la sur vos réseaux sociaux&nbsp;!
        Plus votre annonce est vue, plus vous avez de chances de la vendre rapidement.
      </p>

      <div style="text-align:center;margin:24px 0;">
        <a href="{$waUrl}" style="display:inline-block;background:#25D366;color:#fff;text-decoration:none;font-weight:800;padding:12px 20px;border-radius:12px;margin:4px;">
          Partager sur WhatsApp
        </a>
        <a href="{$fbUrl}" style="display:inline-block;background:#1877F2;color:#fff;text-decoration:none;font-weight:800;padding:12px 20px;border-radius:12px;margin:4px;">
          Partager sur Facebook
        </a>
        <a href="{$xUrl}" style="display:inline-block;background:#111827;color:#fff;text-decoration:none;font-weight:800;padding:12px 20px;border-radius:12px;margin:4px;">
          Partager sur X
        </a>
        <a href="{$mailUrl}" style="display:inline-block;background:#6b7280;color:#fff;text-decoration:none;font-weight:800;padding:12px 20px;border-radius:12px;margin:4px;">
          Envoyer par e-mail
        </a>
      </div>

      <div style="text-align:center;margin:22px 0 6px;">
        <a href="{$listingUrl}" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;font-weight:800;padding:14px 26px;border-radius:14px;">
          Voir mon annonce
        </a>
      </div>
      <p style="font-size:13px;line-height:1.6;color:#6b7280;text-align:center;margin:14px 0 0;">
        Lien de votre annonce :<br>
        <a href="{$listingUrl}" style="color:#0f766e;word-break:break-all;">{$listingUrl}</a>
      </p>
    </div>
    <div style="padding:22px 30px;background:#f9fafb;color:#6b7280;font-size:13px;line-height:1.6;text-align:center;">
      Merci de faire vivre les îles,<br>
      <strong>L'équipe Swap'Îles</strong><br>
      <a href="https://swapiles.com" style="color:#0f766e;">swapiles.com</a>
    </div>
  </div>
</div>
</body>
</html>
HTML;

        Mail::html($html, function ($message) use ($seller, $title) {
            $message->from('contact@swapiles.com', "Swap'Îles")
                ->to($seller->email)
                ->subject('🚀 Partagez votre annonce et vendez plus vite !');
        });
    }
}
