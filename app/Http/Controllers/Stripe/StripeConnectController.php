<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Stripe\StripeClient;

class StripeConnectController extends Controller
{
    public function onboarding()
    {
        $user = Auth::user();

        $stripe = new StripeClient(env('STRIPE_SECRET'));

        $this->ensureStripeAccount($user, $stripe);

        $accountLink = $stripe->accountLinks->create([
            'account' => $user->stripe_account_id,
            'refresh_url' => route('stripe.connect.refresh'),
            'return_url' => route('stripe.connect.return'),
            'type' => 'account_onboarding',
        ]);

        return redirect($accountLink->url);
    }

    /**
     * Page d'activation « façon Vinted » : le vendeur reste sur Swap'Îles et
     * remplit IBAN / adresse / contact dans le composant Stripe intégré.
     */
    public function activate()
    {
        $user = Auth::user();

        try {
            $stripe = new StripeClient(env('STRIPE_SECRET'));
            $this->ensureStripeAccount($user, $stripe);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('account.wallet.index')
                ->withErrors(['stripe' => "Impossible d'initialiser l'activation du portefeuille. Réessayez dans un instant."]);
        }

        return view('account.wallet.activate');
    }

    /**
     * Renvoie le client_secret d'une Account Session pour monter le composant
     * d'onboarding intégré côté navigateur.
     */
    public function accountSession()
    {
        $user = Auth::user();

        try {
            $stripe = new StripeClient(env('STRIPE_SECRET'));
            $this->ensureStripeAccount($user, $stripe);

            $session = $stripe->accountSessions->create([
                'account' => $user->stripe_account_id,
                'components' => [
                    'account_onboarding' => ['enabled' => true],
                ],
            ]);

            return response()->json(['client_secret' => $session->client_secret]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => "Impossible de démarrer l'activation."], 500);
        }
    }

    /**
     * Fin de l'onboarding intégré : on synchronise l'état du compte et on
     * renvoie le vendeur vers son portefeuille.
     */
    public function activated()
    {
        $user = Auth::user();

        try {
            if ($user->stripe_account_id) {
                $stripe = new StripeClient(env('STRIPE_SECRET'));
                $account = $stripe->accounts->retrieve($user->stripe_account_id);

                $user->update([
                    'stripe_charges_enabled' => $account->charges_enabled,
                    'stripe_payouts_enabled' => $account->payouts_enabled,
                    'stripe_details_submitted' => $account->details_submitted,
                    'stripe_onboarding_complete' => (
                        $account->charges_enabled
                        && $account->payouts_enabled
                        && $account->details_submitted
                    ),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $message = $user->stripe_payouts_enabled
            ? '✅ Votre portefeuille est activé, vous pouvez recevoir vos paiements.'
            : 'Merci ! La vérification de votre portefeuille est en cours, cela peut prendre quelques minutes.';

        return redirect()->route('account.wallet.index')->with('status', $message);
    }

    private function ensureStripeAccount($user, StripeClient $stripe): void
    {
        if ($user->stripe_account_id) {
            // Compte déjà créé mais pas encore finalisé : on renseigne le profil
            // d'activité pour éviter que Stripe demande un « site web » au particulier.
            if (! $user->stripe_payouts_enabled) {
                try {
                    $stripe->accounts->update($user->stripe_account_id, [
                        'business_profile' => $this->sellerBusinessProfile($user),
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return;
        }

        $params = [
            'type' => 'express',
            'country' => 'FR',
            'email' => $user->email,
            'business_type' => 'individual',
            'business_profile' => $this->sellerBusinessProfile($user),
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
        ];

        try {
            $account = $stripe->accounts->create($params);
        } catch (\Throwable $e) {
            // Filet de sécurité : si le business_profile pose problème, on crée
            // quand même le compte (sans lui) pour ne jamais bloquer le vendeur.
            report($e);
            unset($params['business_profile']);
            $account = $stripe->accounts->create($params);
        }

        $user->update([
            'stripe_account_id' => $account->id,
        ]);
    }

    /**
     * Profil d'activité pré-rempli côté plateforme : le vendeur est un
     * particulier qui revend ses articles d'occasion sur Swap'Îles. En
     * fournissant l'URL (sa page profil) et une description, Stripe ne
     * demande plus au vendeur de renseigner un « site web d'entreprise ».
     */
    private function sellerBusinessProfile($user): array
    {
        // ⚠️ Stripe exige une URL ABSOLUE (https://…) pour business_profile[url].
        // Une URL relative fait échouer la création du compte (nouveaux vendeurs).
        $url = route('profiles.show', $user);
        if (! \Illuminate\Support\Str::startsWith($url, ['http://', 'https://'])) {
            $url = rtrim((string) env('APP_CANONICAL_URL', 'https://swapiles.com'), '/') . '/' . ltrim($url, '/');
        }

        return [
            'mcc' => '5931', // Used Merchandise and Secondhand Stores
            'url' => $url,
            'product_description' => "Revente d'articles d'occasion entre particuliers sur la marketplace Swap'Îles.",
        ];
    }

    public function refresh()
    {
        return redirect()->route('stripe.connect.onboarding');
    }

    public function returned()
    {
        $user = Auth::user();

        if (!$user->stripe_account_id) {
            if (
            empty(auth()->user()->address_line1) ||
            empty(auth()->user()->postal_code) ||
            empty(auth()->user()->city)
        ) {
            return redirect()->route('account.addresses.edit')
                ->with('status', 'Votre compte vendeur est presque prêt. Complétez votre adresse pour générer vos bordereaux Colissimo.');
        }

        if (empty(auth()->user()->address_line1) || empty(auth()->user()->postal_code) || empty(auth()->user()->city)) {
            return redirect()->route('account.addresses.edit')->with('status', 'Complétez votre adresse pour finaliser votre profil vendeur.');
        }

        return redirect()->route('account.dashboard');
        }

        $stripe = new StripeClient(env('STRIPE_SECRET'));

        $account = $stripe->accounts->retrieve($user->stripe_account_id);

        $user->update([
            'stripe_charges_enabled' => $account->charges_enabled,
            'stripe_payouts_enabled' => $account->payouts_enabled,
            'stripe_details_submitted' => $account->details_submitted,
            'stripe_onboarding_complete' => (
                $account->charges_enabled
                && $account->payouts_enabled
                && $account->details_submitted
            ),
        ]);

        return redirect()
            ->route('account.dashboard')
            ->with('status', 'Compte Stripe connecté.');
    }
}
