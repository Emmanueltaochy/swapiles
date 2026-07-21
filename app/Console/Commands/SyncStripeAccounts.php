<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class SyncStripeAccounts extends Command
{
    protected $signature = 'stripe:sync-accounts {--email= : Synchroniser un email précis}';

    protected $description = 'Synchronise les comptes Stripe Connect utilisateurs';

    public function handle(): int
    {
        $stripe = new StripeClient(env('STRIPE_SECRET'));

        $query = User::whereNotNull('stripe_account_id');

        if ($this->option('email')) {
            $query->where('email', $this->option('email'));
        }

        $users = $query->get();

        $this->info("Utilisateurs Stripe trouvés : " . $users->count());

        foreach ($users as $user) {
            try {
                $account = $stripe->accounts->retrieve($user->stripe_account_id);

                $user->forceFill([
                    'stripe_charges_enabled' => $account->charges_enabled ? 1 : 0,
                    'stripe_payouts_enabled' => $account->payouts_enabled ? 1 : 0,
                    'stripe_details_submitted' => $account->details_submitted ? 1 : 0,
                ])->save();

                $this->info("✅ {$user->email}");
                $this->line("account: {$user->stripe_account_id}");
                $this->line("charges_enabled: " . ($account->charges_enabled ? 'true' : 'false'));
                $this->line("payouts_enabled: " . ($account->payouts_enabled ? 'true' : 'false'));
                $this->line("details_submitted: " . ($account->details_submitted ? 'true' : 'false'));
                $this->newLine();

            } catch (\Exception $e) {
                $this->error("❌ {$user->email}");
                $this->error($e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
