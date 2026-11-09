<?php

namespace App\Support\Agent;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alertes de l'agent à Emmanuel (prospect intéressé, résumé du matin) :
 * e-mail par la boîte no-reply@ (fiable, séparée de contact@ qui refuse
 * parfois des envois) + notification dans l'appli pour les comptes admin.
 */
class Alerte
{
    /** @return string[] */
    public static function destinataires(): array
    {
        $liste = (string) config('agent.alertes', '') ?: (string) env('ADMIN_EMAILS', 'cabinet@taochyconsulting.fr');

        return array_values(array_unique(array_filter(array_map(fn ($e) => strtolower(trim($e)), explode(',', $liste)), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    }

    public static function envoyer(string $titre, string $texte, string $lien, string $type = 'agent'): void
    {
        $corps = $texte . "\n\nOuvrir le Bureau du CEO : " . $lien . "\n\n— Ton agent directeur Swap'Îles";
        foreach (self::destinataires() as $a) {
            try {
                $mailer = Prospection::boiteSeparee() ? Mail::mailer('prospection') : Mail::mailer();
                $mailer->raw($corps, function ($m) use ($a, $titre) {
                    $m->from(Prospection::boiteSeparee() ? (string) config('agent.expediteur.adresse') : 'contact@swapiles.com', 'Agent directeur Swap’Îles')
                        ->to($a)
                        ->subject($titre);
                });
            } catch (Throwable $e) {
                report($e);
            }
        }

        try {
            $admins = array_filter(array_map('trim', explode(',', strtolower((string) env('ADMIN_EMAILS', 'cabinet@taochyconsulting.fr')))));
            foreach (User::whereIn(DB::raw('LOWER(email)'), $admins)->pluck('id') as $id) {
                Notification::create(['user_id' => $id, 'type' => $type, 'title' => Str::limit($titre, 120), 'message' => Str::limit($texte, 180), 'url' => $lien]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
