<?php

namespace App\Support\Agent;

use RuntimeException;

/**
 * Lecteur IMAP minimal (lecture seule, connexion chiffrée) : assez pour
 * repérer les réponses des prospects dans la boîte contact@ sans dépendre
 * d'une extension PHP absente du serveur. Ne marque rien comme lu
 * (BODY.PEEK) et ne supprime jamais rien.
 */
class Imap
{
    /** @var resource|null */
    private $flux = null;

    private int $numero = 0;

    public function connecter(string $hote, int $port, string $utilisateur, string $motDePasse): void
    {
        $contexte = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $flux = @stream_socket_client('ssl://' . $hote . ':' . $port, $code, $message, 20, STREAM_CLIENT_CONNECT, $contexte);
        if (! $flux) {
            throw new RuntimeException('Connexion IMAP impossible (code ' . $code . ')');
        }
        stream_set_timeout($flux, 30);
        $this->flux = $flux;
        $this->ligne(); // message d'accueil
        $this->commande('LOGIN ' . $this->chaine($utilisateur) . ' ' . $this->chaine($motDePasse));
    }

    /** @return int UIDVALIDITY de la boîte */
    public function choisir(string $boite = 'INBOX'): int
    {
        $r = $this->commande('EXAMINE ' . $this->chaine($boite)); // lecture seule
        foreach ($r['lignes'] as $l) {
            if (preg_match('/UIDVALIDITY (\d+)/', $l, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    /** @return int[] UID des messages reçus depuis la date */
    public function depuis(\DateTimeInterface $date): array
    {
        $r = $this->commande('UID SEARCH SINCE ' . $date->format('j-M-Y'));
        foreach ($r['lignes'] as $l) {
            if (str_starts_with($l, '* SEARCH')) {
                return array_map('intval', array_filter(explode(' ', trim(substr($l, 8)))));
            }
        }

        return [];
    }

    public function entetes(int $uid): string
    {
        $r = $this->commande('UID FETCH ' . $uid . ' (BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE MESSAGE-ID AUTO-SUBMITTED X-AUTOREPLY)])');

        return $r['literaux'][0] ?? '';
    }

    public function message(int $uid, int $max = 80000): string
    {
        $r = $this->commande('UID FETCH ' . $uid . ' (BODY.PEEK[]<0.' . $max . '>)');

        return $r['literaux'][0] ?? '';
    }

    public function deconnecter(): void
    {
        if ($this->flux) {
            try {
                $this->commande('LOGOUT');
            } catch (\Throwable $e) {
            }
            fclose($this->flux);
            $this->flux = null;
        }
    }

    private function chaine(string $s): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }

    private function ligne(): string
    {
        $l = fgets($this->flux);
        if ($l === false) {
            throw new RuntimeException('IMAP : connexion interrompue');
        }

        return $l;
    }

    /** @return array{lignes:string[],literaux:string[]} */
    private function commande(string $commande): array
    {
        $tag = 'A' . (++$this->numero);
        fwrite($this->flux, $tag . ' ' . $commande . "\r\n");
        $lignes = [];
        $literaux = [];
        while (true) {
            $l = $this->ligne();
            if (preg_match('/\{(\d+)\}\r\n$/', $l, $m)) {
                $n = (int) $m[1];
                $lu = '';
                while (strlen($lu) < $n) {
                    $morceau = fread($this->flux, $n - strlen($lu));
                    if ($morceau === false || $morceau === '') {
                        throw new RuntimeException('IMAP : lecture interrompue');
                    }
                    $lu .= $morceau;
                }
                $literaux[] = $lu;
            }
            if (str_starts_with($l, $tag . ' ')) {
                if (! preg_match('/^' . $tag . ' OK/i', $l)) {
                    throw new RuntimeException('IMAP : commande refusée (' . strtok($commande, ' ') . ')');
                }
                break;
            }
            $lignes[] = rtrim($l, "\r\n");
        }

        return ['lignes' => $lignes, 'literaux' => $literaux];
    }
}
