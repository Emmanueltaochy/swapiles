<?php

namespace App\Http\Controllers;

use App\Jobs\SendListingViewedEmail;
use App\Jobs\SendMessageReceivedEmail;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ListingController extends Controller
{
    public function show(Request $request, Listing $listing)
    {
        // On autorise la consultation des annonces en ligne ET vendues
        // (sinon un clic sur une notification d'un article vendu → 404).
        abort_unless(in_array($listing->status, ['published', 'sold'], true), 404);

        // On ne compte pas les vues des robots/crawlers (chiffres gonflés).
        $isBot = \App\Support\BotDetector::isBot($request->userAgent());

        // PRÉCHARGEMENT : pour que la page s'ouvre instantanément, le navigateur
        // la télécharge dès que le doigt touche une carte — parfois juste en
        // faisant défiler la grille, sans jamais l'ouvrir. Compter ces requêtes
        // gonflait les vues et pouvait prévenir un vendeur que son annonce
        // avait été vue alors que personne ne l'avait regardée. Une page
        // préchargée ne compte donc rien côté serveur : c'est elle qui
        // signalera la vue, au moment où elle s'affiche vraiment.
        $prechargee = self::estPrechargement($request);

        if ($listing->status === 'published' && ! $isBot && ! $prechargee) {
            $listing->increment('views_count');
            $this->notifySellerOfView($request, $listing);
        }

        $listing->loadCount('favoritedBy');
        $listing->load(['images' => fn($q) => $q->orderBy('order')]);

        return view('listings.show', [
            'listing' => $listing,
            'vueACompter' => $prechargee && $listing->status === 'published' && ! $isBot,
        ]);
    }

    /**
     * La vue d'une page préchargée, signalée par la page elle-même au moment
     * où elle s'affiche réellement à l'écran.
     */
    public function recordView(Request $request, Listing $listing)
    {
        if ($listing->status === 'published' && ! \App\Support\BotDetector::isBot($request->userAgent())) {
            $listing->increment('views_count');
            $this->notifySellerOfView($request, $listing);
        }

        return response()->noContent();
    }

    /**
     * La requête est-elle un préchargement (et non une visite) ?
     *
     * Chrome et Android l'annoncent par « Sec-Purpose: prefetch » (ou
     * « prefetch;prerender »), Firefox par « X-Moz: prefetch », d'anciens
     * navigateurs par « Purpose: prefetch ». Turbo, qui précharge en
     * JavaScript, ne peut pas poser d'en-tête « Sec-… » : il envoie
     * « X-Sec-Purpose: prefetch ».
     */
    public static function estPrechargement(Request $request): bool
    {
        $entetes = strtolower(implode(' ', [
            (string) $request->header('X-Sec-Purpose'),
            (string) $request->header('Sec-Purpose'),
            (string) $request->header('Purpose'),
            (string) $request->header('X-Moz'),
        ]));

        return str_contains($entetes, 'prefetch');
    }

    /**
     * E-mail au vendeur « quelqu'un vient de regarder votre annonce ».
     *
     * Le COMPTEUR de vues, lui, enregistre toujours chaque visite : seul
     * l'envoi de l'e-mail est limité. On ne prévient jamais le vendeur de ses
     * propres vues, et l'envoi est plafonné (voir config/mail_limits.php) —
     * l'e-mail rappelle de toute façon le total des vues de l'annonce.
     */
    private function notifySellerOfView(Request $request, Listing $listing): void
    {
        try {
            if (Auth::check() && Auth::id() === $listing->user_id) {
                return;
            }

            // Un e-mail par annonce et par fenêtre — plus par VISITEUR :
            // le compte par visiteur multipliait les envois et saturait à lui
            // seul le quota quotidien de la boîte d'envoi.
            $heures = (int) config('mail_limits.listing_view.par_annonce_par_heures', 24);

            // Cache::add ne renvoie true qu'une seule fois par fenêtre : sert de verrou.
            if (! Cache::add('listing_view_email:' . $listing->id, 1, now()->addHours($heures))) {
                return;
            }

            // Plafond par vendeur : une boutique de 30 annonces ne doit pas
            // générer 30 e-mails dans la même journée.
            $maxParJour = (int) config('mail_limits.listing_view.par_vendeur_par_jour', 3);
            $compteurKey = 'listing_view_email_seller:' . $listing->user_id . ':' . now()->toDateString();

            Cache::add($compteurKey, 0, now()->addDay());
            if (Cache::increment($compteurKey) > $maxParJour) {
                return;
            }

            // Notification interne -> c'est elle qui declenche le push.
            // Sans elle, le vendeur recevait l'e-mail « annonce vue » mais
            // aucune notification sur son telephone.
            \App\Models\Notification::create([
                'user_id' => $listing->user_id,
                'type' => 'listing_viewed',
                'title' => 'Votre annonce a été vue 👀',
                'message' => '« ' . $listing->title . ' » vient d’être consultée.',
                'url' => route('listings.show', $listing, absolute: false),
            ]);

            SendListingViewedEmail::dispatch($listing->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function requestMode(Request $request, Listing $listing, string $mode)
    {
        abort_unless(Auth::check(), 403);
        abort_if(Auth::id() === $listing->user_id, 403);

        if ($listing->status !== 'published') {
            return redirect()->route('listings.show', $listing)
                ->with('status', "Cet article n'est plus disponible.");
        }

        abort_unless(in_array($mode, ['cash', 'exchange', 'don'], true), 404);

        $buyer = Auth::user();
        $seller = $listing->user;

        $label = match ($mode) {
            'cash' => 'payer en espèces',
            'exchange' => 'faire un échange',
            'don' => 'récupérer ce don',
        };

        $body = match ($mode) {
            'cash' => "💵 Bonjour, je souhaite acheter cet article en espèces lors d’une remise en main propre.",
            'exchange' => "🔄 Bonjour, je souhaite proposer un échange pour cet article.",
            'don' => "🎁 Bonjour, je souhaite récupérer ce don.",
        };

        $message = Message::create([
            'listing_id' => $listing->id,
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'body' => $body,
        ]);

        try {
            Notification::create([
                'user_id' => $seller->id,
                'type' => 'listing_' . $mode . '_request',
                'title' => 'Nouvelle demande 💬',
                'message' => ($buyer->name ?? 'Un membre') . ' souhaite ' . $label . ' : ' . $listing->title,
                'url' => route('account.messages.show', [
                    'listing' => $listing,
                    'user' => $buyer,
                ], absolute: false),
            ]);

            SendMessageReceivedEmail::dispatch($message->id, $seller->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('account.messages.show', [
                'listing' => $listing,
                'user' => $seller,
            ])
            ->with('status', 'Votre demande a été envoyée au vendeur.');
    }
}
