<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Commerçant qui demande à devenir point relais (à ne pas confondre avec RelayPointRequest : un membre qui veut un relais près de chez lui). */
class CandidatureRelais extends Model
{
    protected $table = 'candidatures_relais';

    public const STATUTS = [
        'nouvelle' => 'Nouvelle',
        'contactee' => 'Commerçant contacté',
        'infos_attendues' => 'En attente d’infos',
        'point_relais_cree' => 'Point relais créé',
        'sans_suite' => 'Sans suite',
    ];

    protected $attributes = ['statut' => 'nouvelle', 'source' => 'formulaire'];

    protected $fillable = [
        'user_id', 'commerce', 'contact', 'ville', 'territoire', 'telephone', 'email', 'horaires', 'message',
        'source', 'message_id', 'statut', 'relay_point_id', 'notes', 'created_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relayPoint(): BelongsTo
    {
        return $this->belongsTo(RelayPoint::class);
    }

    /** Déjà enregistrée ? (même e-mail et même commerce, ou même message reçu). */
    public static function existe(?string $email, string $commerce, ?string $messageId = null): bool
    {
        return static::query()
            ->where(function ($q) use ($email, $commerce, $messageId) {
                $q->where(fn ($q) => $q->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $email)])->whereRaw('LOWER(commerce) = ?', [mb_strtolower($commerce)]));
                if ($messageId) {
                    $q->orWhere('message_id', $messageId);
                }
            })->exists();
    }

    /** Fiche lisible (alerte, Bureau du CEO). */
    public function fiche(): string
    {
        return implode("\n", array_filter([
            'Commerce : ' . $this->commerce . ($this->ville ? ' (' . $this->ville . ')' : ''),
            $this->contact ? 'Contact : ' . $this->contact : null,
            $this->telephone ? 'Téléphone : ' . $this->telephone : null,
            $this->email ? 'E-mail : ' . $this->email : null,
            $this->horaires ? 'Horaires : ' . $this->horaires : null,
            $this->message ? "Message : " . $this->message : null,
            $this->user_id ? 'A déjà un compte Swap’Îles.' : null,
        ]));
    }
}
