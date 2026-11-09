<?php

namespace App\Support\Agent;

use App\Models\ProspectRse;
use App\Models\RelaisProspect;
use App\Models\Reglage;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi des mails de prospection (points relais et organismes publics) :
 * boîte no-reply@ séparée, réponses vers contact@, plafond du jour commun
 * avec montée en douceur, envois répartis sur la journée.
 */
class Prospection
{
    public const DEBUT = 'agent.prospection.debut';

    public const PAUSE = 'agent.pause';

    public const RSE_VALIDE = 'agent.rse.valide';

    /** La boîte no-reply@ est-elle branchée (secrets GitHub) ? */
    public static function boiteSeparee(): bool
    {
        return filled(config('mail.mailers.prospection.username')) && filled(config('mail.mailers.prospection.password'));
    }

    public static function enPause(): bool
    {
        return ! config('agent.actif') || (bool) Reglage::lire(self::PAUSE, false);
    }

    public static function rseValidee(): bool
    {
        return (bool) Reglage::lire(self::RSE_VALIDE, false);
    }

    /** Jours depuis le premier envoi par la boîte séparée (0 le premier jour). */
    public static function anciennete(): int
    {
        $debut = Reglage::lire(self::DEBUT);

        return $debut ? (int) floor(now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($debut)->startOfDay(), true)) : 0;
    }

    /**
     * Mails de prospection par jour au plus, toutes prospections confondues.
     * Sans boîte séparée : l'ancien plafond des points relais (boîte contact@).
     */
    public static function plafondJour(): int
    {
        if (! self::boiteSeparee()) {
            return (int) config('relais.prospection_par_jour', 40);
        }
        $jours = self::anciennete();
        foreach ((array) config('agent.montee', []) as $avant => $plafond) {
            if ($jours < (int) $avant) {
                return (int) $plafond;
            }
        }

        return (int) config('agent.plafond_max', 80);
    }

    public static function envoyesAujourdhui(): int
    {
        $debut = today();

        return RelaisProspect::where('contacte_at', '>=', $debut)->count()
            + ProspectRse::where('contacte_at', '>=', $debut)->count()
            + ProspectRse::where('relance_at', '>=', $debut)->count();
    }

    public static function resteAujourdhui(): int
    {
        return max(0, self::plafondJour() - self::envoyesAujourdhui());
    }

    /**
     * Part à envoyer maintenant : le reste du jour réparti sur les heures
     * restantes (pour ne pas tout envoyer d'un coup, ce que les filtres
     * anti-spam n'aiment pas).
     */
    public static function partDeCetteHeure(?\Carbon\CarbonInterface $maintenant = null): int
    {
        $h = ($maintenant ?? now())->hour;
        $fin = (int) config('agent.heure_fin', 16);
        if ($h < (int) config('agent.heure_debut', 8) || $h > $fin) {
            return 0;
        }

        return (int) ceil(self::resteAujourdhui() / ($fin - $h + 1));
    }

    /**
     * Envoie un mail de prospection. De no-reply@ (réponses vers contact@)
     * quand la boîte séparée est branchée, sinon de contact@ comme avant.
     */
    public static function envoyer(string $vue, array $donnees, string $a, ?string $nom, string $sujet): void
    {
        $separee = self::boiteSeparee();
        if ($separee && ! Reglage::lire(self::DEBUT)) {
            Reglage::ecrire(self::DEBUT, now()->toDateString());
        }
        $reponses = (string) config('agent.expediteur.reponses', 'contact@swapiles.com');
        $desinscription = $donnees['lienDesinscription'] ?? null;
        $remplir = function (Message $mail) use ($separee, $reponses, $a, $nom, $sujet, $desinscription) {
            $mail->from($separee ? (string) config('agent.expediteur.adresse') : 'contact@swapiles.com', $separee ? (string) config('agent.expediteur.nom') : "Swap'Îles")
                ->replyTo($reponses, "Emmanuel Taochy (Swap'Îles)")
                ->to($a, $nom)
                ->subject($sujet);
            // Lien de désinscription reconnu par les messageries (Gmail, Outlook).
            if ($desinscription) {
                $mail->getSymfonyMessage()->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $desinscription . '>');
            }
        };
        ($separee ? Mail::mailer('prospection') : Mail::mailer())->send($vue, $donnees, $remplir);
    }
}
