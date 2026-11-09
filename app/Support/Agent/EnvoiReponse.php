<?php

namespace App\Support\Agent;

use App\Models\AgentReponse;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Envoie au prospect la réponse préparée par le CEO, depuis contact@ et
 * signée Emmanuel, dans le même fil que son message. Jamais deux fois, et
 * seulement après la confirmation d'Emmanuel (bouton ou « oui, envoie »).
 */
class EnvoiReponse
{
    public static function disponible(): bool
    {
        return filled(config('mail.mailers.contact.username')) && filled(config('mail.mailers.contact.password'));
    }

    /** Le brouillon sans la signature écrite à la main (la vraie signature est ajoutée). */
    public static function texte(AgentReponse $r): string
    {
        $t = trim((string) $r->brouillon);
        $t = preg_replace('/\n*\s*(Emmanuel Taochy|Emmanuel)\s*[,–-]?\s*(fondateur[^\n]*)?\s*$/iu', '', $t);

        return trim((string) $t);
    }

    /** Peut-on encore l'envoyer ? null si oui, sinon la raison (dite à l'oral). */
    public static function empechement(?AgentReponse $r): ?string
    {
        return match (true) {
            ! $r => 'je ne retrouve pas ce message',
            $r->envoye_at !== null => 'la réponse a déjà été envoyée le ' . $r->envoye_at->format('d/m à H:i'),
            blank($r->brouillon) => 'il n’y a pas de réponse préparée pour ce prospect',
            ! filter_var($r->expediteur, FILTER_VALIDATE_EMAIL) => 'l’adresse e-mail du prospect est invalide',
            ! self::disponible() => 'la boîte contact@ n’est pas branchée pour l’envoi',
            default => null,
        };
    }

    public static function envoyer(AgentReponse $r): void
    {
        if ($raison = self::empechement($r)) {
            throw new RuntimeException($raison);
        }
        $texte = self::texte($r);
        $sujet = preg_match('/^\s*re\s*:/i', (string) $r->sujet) ? (string) $r->sujet : 'Re: ' . ($r->sujet ?: 'Swap’Îles');
        $p = $r->prospect();
        $idOrigine = str_starts_with($r->message_id, '<') ? $r->message_id : null;

        $envoye = Mail::mailer('contact')->send(['html' => 'emails.reponse-prospect', 'text' => 'emails.reponse-prospect-texte'], ['texte' => $texte],
            function (Message $m) use ($r, $sujet, $p, $idOrigine) {
                $m->from('contact@swapiles.com', 'Emmanuel Taochy – Swap’Îles')
                    ->to($r->expediteur, $p?->nom)
                    ->subject($sujet);
                if ($idOrigine) {
                    // Dans le même fil de discussion que son message.
                    $m->getSymfonyMessage()->getHeaders()->addIdHeader('In-Reply-To', trim($idOrigine, '<>'));
                    $m->getSymfonyMessage()->getHeaders()->addIdHeader('References', trim($idOrigine, '<>'));
                }
            });

        $r->forceFill(['envoye_at' => now(), 'texte_envoye' => Str::limit($texte, 4000), 'vu_at' => $r->vu_at ?? now()])->save();
        if ($p) {
            $p->forceFill(['notes' => trim(($p->notes ? $p->notes . "\n" : '') . now()->format('d/m/Y') . ' : réponse envoyée par e-mail')])->save();
        }

        // Copie dans « Envoyés » de contact@, pour la retrouver dans la boîte (au mieux).
        try {
            $brut = $envoye?->getSymfonySentMessage()?->toString();
            if ($brut && BoiteReponses::configuree() && ! app()->runningUnitTests()) {
                $imap = new Imap();
                $imap->connecter((string) config('agent.imap.hote'), (int) config('agent.imap.port'), (string) config('agent.imap.utilisateur'), (string) config('agent.imap.mot_de_passe'));
                try {
                    if ($dossier = $imap->dossierEnvoyes()) {
                        $imap->ajouter($dossier, $brut);
                    }
                } finally {
                    $imap->deconnecter();
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
