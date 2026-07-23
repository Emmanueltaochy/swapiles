<?php

namespace App\Http\Controllers;

use App\Models\NewsletterEvent;
use App\Models\NewsletterRecipient;
use Illuminate\Http\Request;

class NewsletterTrackingController extends Controller
{
    /** Pixel d'ouverture : GET /n/o/{token} -> gif 1x1 transparent. */
    public function open(Request $request, string $token)
    {
        try {
            $recipient = NewsletterRecipient::where('token', $token)->first();

            if ($recipient) {
                $recipient->increment('open_count');
                if (is_null($recipient->opened_at)) {
                    $recipient->forceFill(['opened_at' => now()])->save();
                }

                NewsletterEvent::create([
                    'campaign_id' => $recipient->campaign_id,
                    'recipient_id' => $recipient->id,
                    'type' => 'open',
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // GIF transparent 1x1
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /** Redirection de clic : GET /n/c/{token}?u=... -> enregistre puis redirige. */
    public function click(Request $request, string $token)
    {
        $url = (string) $request->query('u', '');

        // Sécurité : on ne redirige que vers des URLs http(s) valides.
        if (! preg_match('#^https?://#i', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url('/');
        }

        try {
            $recipient = NewsletterRecipient::where('token', $token)->first();

            if ($recipient) {
                $recipient->increment('click_count');
                if (is_null($recipient->first_clicked_at)) {
                    $recipient->forceFill(['first_clicked_at' => now()])->save();
                }

                NewsletterEvent::create([
                    'campaign_id' => $recipient->campaign_id,
                    'recipient_id' => $recipient->id,
                    'type' => 'click',
                    'url' => mb_substr($url, 0, 2000),
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->away($url);
    }
}
