<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendListingViewedEmail implements ShouldQueue
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

        if (!$listing || !$listing->user || !$listing->user->email) {
            return;
        }

        $seller = $listing->user;
        $url = route('listings.show', $listing);

        $subject = '👀 Quelqu’un vient de regarder votre annonce';

        $body = "Bonjour,\n\n"
            . "Bonne nouvelle : quelqu’un vient de consulter votre annonce sur Swap Îles.\n\n"
            . "Annonce : " . $listing->title . "\n"
            . "Vues au total : " . ($listing->views_count ?? 0) . "\n\n"
            . "Pensez à répondre vite aux messages pour conclure vos ventes plus rapidement.\n\n"
            . "Voir mon annonce : " . $url . "\n\n"
            . "L'équipe Swap Îles\n"
            . "https://swapiles.com";

        Mail::raw($body, function ($mail) use ($seller, $subject) {
            $mail->from('contact@swapiles.com', 'Swap Îles')
                ->to($seller->email)
                ->subject($subject);
        });
    }
}
