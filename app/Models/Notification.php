<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $notification) {
            if (! $notification->user_id) {
                return;
            }

            // On ne met en file un envoi que si le push est réellement configuré.
            if (! \App\Support\FcmService::configured() && ! \App\Support\ApnsService::configured()) {
                return;
            }

            // Réglages du membre : il peut avoir coupé cette catégorie.
            // Sans ce garde-fou, le seul moyen d'arrêter les notifications
            // était de supprimer son compte.
            $destinataire = $notification->user;
            if ($destinataire && ! $destinataire->accepteNotification($notification->type, 'push')) {
                return;
            }

            // Garde-fous : heures de silence dans le fuseau du membre et
            // plafond quotidien. Voir App\Support\PushPolicy.
            $decision = \App\Support\PushPolicy::decider($destinataire, $notification->type);

            if ($decision['action'] === 'ignorer') {
                // La notification reste consultable dans l'app : seul le signal
                // sonore est supprimé.
                return;
            }

            $url = $notification->clickUrl();

            $envoi = \App\Jobs\SendPushBroadcast::dispatch(
                title: $notification->title ?: "Swap'Îles",
                body: (string) $notification->message,
                url: $url === '#' ? null : $url,
                userId: $notification->user_id,
            );

            if ($decision['action'] === 'differer' && $decision['envoi_a']) {
                $envoi->delay($decision['envoi_a']);
            }
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
