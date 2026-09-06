<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Authentification de l'espace d'administration.
 *
 * Identique à celle de Filament, à une différence près : un membre connecté qui
 * n'est PAS administrateur est renvoyé vers le site public au lieu de tomber sur
 * un « 403 Forbidden » sans issue (ce qui arrivait à tout membre se connectant
 * par la page de connexion de l'admin).
 */
class FilamentAdminAuthenticate extends FilamentAuthenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return; /** @phpstan-ignore-line */
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user instanceof FilamentUser) {
            if (! $user->canAccessPanel($panel)) {
                // Membre ordinaire : on le renvoie sur le site, toujours connecté.
                throw new HttpResponseException(
                    redirect()->route('home')->with(
                        'status',
                        "Cet espace est réservé à l'administration de Swap'Îles."
                    )
                );
            }

            return;
        }

        abort_if(config('app.env') !== 'local', 403);
    }
}
