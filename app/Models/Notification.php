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
