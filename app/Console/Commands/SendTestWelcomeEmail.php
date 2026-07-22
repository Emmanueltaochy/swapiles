<?php

namespace App\Console\Commands;

use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Console\Command;

class SendTestWelcomeEmail extends Command
{
    protected $signature = 'swapiles:welcome-test {email? : E-mail cible (sinon le dernier compte créé)}';

    protected $description = "Envoie l'e-mail de bienvenue + confirmation à un utilisateur (test).";

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = $email
            ? User::where('email', $email)->first()
            : User::latest('id')->first();

        if (!$user) {
            $this->error($email ? "Aucun utilisateur avec l'email {$email}." : 'Aucun utilisateur trouvé.');

            return self::FAILURE;
        }

        if (!$user->email) {
            $this->error("Le compte #{$user->id} n'a pas d'adresse e-mail.");

            return self::FAILURE;
        }

        // Envoi synchrone (indépendant du worker) : teste aussi la config mail.
        (new SendWelcomeEmail($user->id))->handle();

        $this->info("✅ E-mail de bienvenue envoyé à {$user->email} (compte #{$user->id} — {$user->name}).");

        return self::SUCCESS;
    }
}
