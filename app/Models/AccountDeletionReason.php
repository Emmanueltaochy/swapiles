<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Motif de suppression d'un compte, conservé de façon ANONYME.
 *
 * Aucun lien vers le membre : ni identifiant, ni e-mail, ni nom. On garde
 * seulement le motif, la date, l'ancienneté du compte et le fait qu'il y ait
 * eu des ventes — de quoi comprendre les départs sans conserver de donnée
 * personnelle après une demande de suppression.
 */
class AccountDeletionReason extends Model
{
    public $timestamps = false;

    protected $fillable = ['reason', 'details', 'days_since_signup', 'had_sales', 'created_at'];

    protected $casts = [
        'had_sales' => 'boolean',
        'created_at' => 'datetime',
    ];

    /** Motifs proposés, dans l'ordre d'affichage. */
    public const MOTIFS = [
        'trop_notifications' => 'Je recevais trop de notifications ou d’e-mails',
        'pas_utile' => 'Je n’en ai plus l’utilité',
        'pas_de_ventes' => 'Je n’arrivais pas à vendre',
        'probleme_technique' => 'L’application ou le site ne fonctionnait pas bien',
        'mauvaise_experience' => 'Mauvaise expérience avec un membre',
        'confidentialite' => 'Je ne souhaite plus que mes données soient conservées',
        'autre' => 'Autre raison',
    ];

    public function motifLabel(): string
    {
        return self::MOTIFS[$this->reason] ?? $this->reason;
    }
}
