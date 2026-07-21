<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendSellerPublishedListingEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 90;

    public function __construct(
        public int $listingId,
        public int $recipientId
    ) {}

    public function handle(): void
    {
        $listing = Listing::with('user')->find($this->listingId);
        $recipient = User::find($this->recipientId);

        if (!$listing || !$recipient || !$recipient->email) {
            return;
        }

        $url = route('listings.show', $listing);

        $body = "Bonjour,\n\n"
            . ($listing->user->name ?? 'Un vendeur que vous suivez') . " vient de publier une nouvelle annonce sur Swap Îles.\n\n"
            . "Annonce : " . $listing->title . "\n"
            . "Prix : " . number_format((float) $listing->price, 2, ',', ' ') . " €\n\n"
            . "Voir l’annonce : " . $url . "\n\n"
            . "L’équipe Swap Îles";

        Mail::raw($body, function ($mail) use ($recipient) {
            $mail->from('contact@swapiles.com', 'Swap Îles')
                ->to($recipient->email)
                ->subject('Nouvelle annonce sur Swap Îles');
        });
    }
}
