<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Stripe\StripeClient;

/**
 * Création / provisionnement d'un compte Stripe Connect (Express) vendeur.
 * Centralisé ici pour être réutilisé par l'onboarding (StripeConnectController)
 * ET par le provisionnement en arrière-plan à la publication (point 19).
 *
 * Le compte demande uniquement la capability « transfers » : Swap'Îles encaisse
 * l'acheteur sur le compte plateforme (separate charges and transfers) puis vire
 * le vendeur plus tard. Le vendeur n'a donc besoin d'un dossier complet qu'au
 * moment du virement, pas de la vente.
 */
class StripeConnectAccountService
{
    private ?StripeClient $stripe;

    public function __construct(?StripeClient $stripe = null)
    {
        $this->stripe = $stripe;
    }

    private function client(): StripeClient
    {
        return $this->stripe ??= new StripeClient(env('STRIPE_SECRET'));
    }

    /**
     * Garantit qu'un compte Connect existe pour ce vendeur, sans exiger de KYC.
     * Idempotent et best-effort : renvoie l'id du compte, ou null si la création
     * échoue (la publication d'annonce ne doit jamais être bloquée par Stripe).
     */
    public function ensureAccount(User $user): ?string
    {
        if ($user->stripe_account_id) {
            return $user->stripe_account_id;
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
            try {
                $account = $this->client()->accounts->create($params);
            } catch (\Throwable $e) {
                // Filet : si le business_profile pose problème, on crée quand
                // même le compte (sans lui) pour ne jamais bloquer le vendeur.
                report($e);
                unset($params['business_profile']);
                $account = $this->client()->accounts->create($params);
            }
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $user->update(['stripe_account_id' => $account->id]);

        return $account->id;
    }

    /**
     * Profil d'activité pré-rempli : le vendeur est un particulier qui revend
     * ses articles d'occasion. En fournissant l'URL (page profil) et une
     * description, Stripe ne réclame pas de « site web d'entreprise ».
     *
     * ⚠️ Stripe exige une URL ABSOLUE (https://…) pour business_profile[url].
     *
     * @return array<string,string>
     */
    public function sellerBusinessProfile(User $user): array
    {
        $url = route('profiles.show', $user);
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = rtrim((string) env('APP_CANONICAL_URL', 'https://swapiles.com'), '/') . '/' . ltrim($url, '/');
        }

        return [
            'mcc' => '5931', // Used Merchandise and Secondhand Stores
            'url' => $url,
            'product_description' => "Revente d'articles d'occasion entre particuliers sur la marketplace Swap'Îles.",
        ];
    }
}
