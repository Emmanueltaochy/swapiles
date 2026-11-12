<?php

namespace App\Support;

use App\Models\CandidatureRelais;
use App\Models\User;
use App\Support\Agent\BoiteReponses;
use App\Support\Agent\Courriel;
use App\Support\Agent\Imap;

/**
 * Récupère dans la boîte contact@ les demandes « Point relais partenaire »
 * envoyées par le formulaire avant qu'elles soient enregistrées dans l'admin.
 * Lecture seule, sans doublon (identifiant du message, ou e-mail + commerce).
 */
class CandidaturesRelaisBoite
{
    /** @return array{lus:int,ajoutees:int} */
    public static function importer(int $jours = 120, ?Imap $imap = null): array
    {
        if (! BoiteReponses::configuree()) {
            return ['lus' => 0, 'ajoutees' => 0];
        }
        $imap ??= new Imap();
        $imap->connecter((string) config('agent.imap.hote'), (int) config('agent.imap.port'), (string) config('agent.imap.utilisateur'), (string) config('agent.imap.mot_de_passe'));
        try {
            $imap->choisir('INBOX');
            $uids = $imap->rechercher('SUBJECT "relais partenaire" SINCE ' . now()->subDays($jours)->format('j-M-Y'));
            $ajoutees = 0;
            foreach (array_slice($uids, -300) as $uid) {
                $ajoutees += self::depuisMessage($imap->message($uid)) ? 1 : 0;
            }

            return ['lus' => count($uids), 'ajoutees' => $ajoutees];
        } finally {
            $imap->deconnecter();
        }
    }

    /** Crée la candidature à partir du mail du formulaire. False si illisible ou déjà connue. */
    public static function depuisMessage(string $brut): bool
    {
        $entetes = Courriel::entetes($brut);
        $texte = Courriel::texte($brut);
        $champ = fn (string $nom) => preg_match('/^\s*' . $nom . '\s*:\s*(.+)$/miu', $texte, $m) ? trim($m[1]) : null;

        $commerce = $champ('Commerce');
        if (! $commerce || ! str_contains($texte, 'point relais partenaire')) {
            return false;
        }
        $email = $champ('E-mail');
        $email = $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_strtolower($email) : null;
        $messageId = isset($entetes['message-id']) ? mb_substr($entetes['message-id'], 0, 250) : null;
        if (CandidatureRelais::existe($email, $commerce, $messageId)) {
            return false;
        }
        $message = preg_match('/^\s*Message\s*:\s*(.*)$/msu', $texte, $m) ? trim($m[1]) : null;
        $compte = $email ? User::whereRaw('LOWER(email) = ?', [$email])->first() : null;
        $sans = fn (?string $v) => $v === null || in_array(trim($v), ['—', '-', ''], true) ? null : $v;

        CandidatureRelais::create([
            'user_id' => $compte?->id,
            'commerce' => mb_substr($commerce, 0, 160),
            'contact' => mb_substr((string) $champ('Contact'), 0, 120) ?: null,
            'ville' => mb_substr((string) $champ('Ville'), 0, 120) ?: null,
            'territoire' => $compte?->territoire,
            'telephone' => mb_substr((string) $champ('Téléphone'), 0, 40) ?: null,
            'email' => $email,
            'horaires' => $sans(mb_substr((string) $champ('Horaires'), 0, 191)),
            'message' => $sans($message ? mb_substr($message, 0, 2000) : null),
            'source' => 'boite_mail',
            'message_id' => $messageId,
            'created_at' => rescue(fn () => \Carbon\Carbon::parse($entetes['date'] ?? 'now'), now(), false),
        ]);

        return true;
    }
}
