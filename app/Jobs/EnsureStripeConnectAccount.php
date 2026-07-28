<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\StripeConnectAccountService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Point 19 — provisionne en arrière-plan le compte Connect (capability
 * « transfers » demandée) dès qu'un vendeur publie en paiement CB, sans lui
 * réclamer de KYC. Le compte est ainsi prêt à recevoir le virement le jour de
 * la vente. Best-effort : n'échoue jamais la publication.
 */
class EnsureStripeConnectAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public int $userId)
    {
    }

    public function handle(StripeConnectAccountService $accounts): void
    {
        $user = User::find($this->userId);

        if (! $user || $user->stripe_account_id) {
            return;
        }

        $accounts->ensureAccount($user);
    }
}
