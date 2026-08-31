<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'url',
        'read_at',
    ];

    /**
     * Chaque notification interne (message, favori, échange, transaction…)
     * déclenche AUSSI une notification push sur les appareils du membre.
     * Un seul point d'accroche couvre tous les événements de l'app.
     */
    protected static function booted(): void
    {
        static::created(function (self $notification) {
            if (! $notification->user_id) {
                return;
            }

            // On ne met en file un envoi que si le push est réellement configuré.
            if (! \App\Support\FcmService::configured()) {
                return;
            }

            $url = $notification->clickUrl();

            \App\Jobs\SendPushBroadcast::dispatch(
                title: $notification->title ?: "Swap'Îles",
                body: (string) $notification->message,
                url: $url === '#' ? null : $url,
                userId: $notification->user_id,
            );
        });
    }

    /**
     * Lien de clic normalisé : toujours relatif au domaine courant.
     *
     * Certaines notifications historiques ont été enregistrées avec une URL
     * absolue contenant un ancien domaine (ex : admin.swapiles.com). On ne
     * garde donc que le chemin (+ query) pour que le clic reste sur le domaine
     * actuel (swapiles.com), quel que soit ce qui a été stocké.
     */
    public function clickUrl(): string
    {
        $url = trim((string) $this->url);

        if ($url === '') {
            return '#';
        }

        // URL absolue -> on ne conserve que le chemin + éventuelle query.
        if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://'])) {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';
            $query = parse_url($url, PHP_URL_QUERY);

            return $query ? $path.'?'.$query : $path;
        }

        return $url;
    }
}
