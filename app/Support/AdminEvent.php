<?php

namespace App\Support;

use App\Jobs\SendAdminEventEmail;

class AdminEvent
{
    public static function notify(string $title, string $message, ?string $url = null): void
    {
        try {
            SendAdminEventEmail::dispatch($title, $message, $url);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
