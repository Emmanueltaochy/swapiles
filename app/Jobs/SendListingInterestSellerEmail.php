<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Support\Territoires;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail au vendeur : un acheteur d'une autre île veut son article mais ne
 * peut pas l'acheter faute de Colissimo. Incite à activer la CB en ligne.
 */
class SendListingInterestSellerEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $listingId,
        public int $buyerId,
        public ?string $buyerTerritoire = null,
    ) {
    }

    public function handle(): void
    {
        $listing = Listing::with('user')->find($this->listingId);

        if (! $listing || ! $listing->user || ! $listing->user->email) {
            return;
        }

        $seller = $listing->user;
        $ile = e(Territoires::origin($this->buyerTerritoire));
        $title = e($listing->title);
        $editUrl = e(route('account.listings.edit', $listing));
        $walletUrl = e(route('account.wallet.index'));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;">
<div style="max-width:600px;margin:0 auto;padding:28px 16px;">
  <div style="background:#fff;border-radius:24px;overflow:hidden;border:1px solid #e5e7eb;">
    <div style="padding:28px;text-align:center;background:#0f766e;color:#fff;">
      <div style="font-size:28px;font-weight:900;">Swap'Îles 🌴</div>
      <div style="font-size:14px;margin-top:6px;">Une vente vous attend !</div>
    </div>
    <div style="padding:30px;">
      <h1 style="font-size:22px;margin:0 0 14px;">Un acheteur veut votre article ! 🌍</h1>
      <p style="font-size:16px;line-height:1.7;color:#374151;margin:0 0 14px;">
        Bonne nouvelle : un acheteur <strong>{$ile}</strong> est intéressé par votre article
        <strong>« {$title} »</strong>.
      </p>
      <p style="font-size:16px;line-height:1.7;color:#374151;margin:0 0 18px;">
        ⚠️ Mais il ne peut pas l'acheter : vous n'avez pas encore activé <strong>Colissimo</strong> et le
        <strong>paiement par carte</strong>. Activez-les pour vendre à <strong>toutes les îles</strong> et conclure cette vente !
      </p>

      <div style="text-align:center;margin:24px 0;">
        <a href="{$walletUrl}" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;font-weight:800;padding:14px 26px;border-radius:14px;">
          Activer mon portefeuille (CB)
        </a>
      </div>
      <p style="font-size:14px;line-height:1.6;color:#6b7280;text-align:center;margin:0;">
        Puis modifiez votre annonce pour cocher Colissimo :<br>
        <a href="{$editUrl}" style="color:#0f766e;">Modifier mon annonce →</a>
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

        Mail::html($html, function ($message) use ($seller) {
            $message->from('contact@swapiles.com', "Swap'Îles")
                ->to($seller->email)
                ->subject('🌍 Un acheteur veut votre article — activez Colissimo pour vendre !');
        });
    }
}
