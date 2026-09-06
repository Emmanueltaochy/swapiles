<?php

namespace App\Support;

use App\Jobs\SendAdminEventEmail;

class AdminEvent
{
    /**
     * Alerte à l'administration.
     *
     * @param  string|null  $key  Clé de config/admin_alerts.php. Les évènements
     *                            courants y sont désactivés : ils restent visibles
     *                            dans Admin > Activité & Emails, sans consommer
     *                            le quota d'envoi quotidien de la boîte.
     */
    public static function notify(string $title, string $message, ?string $url = null, ?string $key = null): void
    {
        if ($key !== null && ! config('admin_alerts.' . $key, true)) {
            return;
        }

        try {
            SendAdminEventEmail::dispatch($title, $message, $url);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
