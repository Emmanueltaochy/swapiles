<?php

namespace App\Jobs;

use App\Models\ListingOffer;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOfferEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 90;

    public function __construct(
        public int $offerId,
        public int $recipientId,
        public string $type
    ) {}

    public function handle(): void
    {
        $offer = ListingOffer::with(['listing', 'buyer', 'seller'])->find($this->offerId);
        $recipient = User::find($this->recipientId);

        if (!$offer || !$recipient || !$recipient->email) {
            return;
        }

        $listing = $offer->listing;
        $buyer = $offer->buyer;
        $seller = $offer->seller;

        if ($this->type === 'received') {
            $subject = 'Nouvelle offre reçue sur Swap Îles';
            $url = route('account.messages.show', ['listing' => $listing, 'user' => $buyer]);

            $body = "Bonjour,\n\n"
                . ($buyer->name ?? 'Un membre') . " vous propose " . $offer->amount . " € pour votre annonce :\n"
                . ($listing->title ?? 'Annonce Swap Îles') . "\n\n"
                . "Voir l’offre : " . $url . "\n\n"
                . "L’équipe Swap Îles";
        } elseif ($this->type === 'accepted') {
            $subject = 'Votre offre a été acceptée';
            $url = route('checkout.show', ['listing' => $listing, 'offer' => $offer->id]);

            $body = "Bonjour,\n\n"
                . "Bonne nouvelle, votre offre de " . $offer->amount . " € a été acceptée.\n\n"
                . "Annonce : " . ($listing->title ?? 'Annonce Swap Îles') . "\n\n"
                . "Finaliser mon achat : " . $url . "\n\n"
                . "L’équipe Swap Îles";
        } else {
            $subject = 'Votre offre a été refusée';
            $url = route('listings.show', $listing);

            $body = "Bonjour,\n\n"
                . "Votre offre de " . $offer->amount . " € a été refusée.\n\n"
                . "Annonce : " . ($listing->title ?? 'Annonce Swap Îles') . "\n\n"
                . "Voir l’annonce : " . $url . "\n\n"
                . "L’équipe Swap Îles";
        }

        Mail::raw($body, function ($mail) use ($recipient, $subject) {
            $mail->from('contact@swapiles.com', 'Swap Îles')
                ->to($recipient->email)
                ->subject($subject);
        });
    }
}
