<?php

namespace App\Support\Agent;

use App\Models\Listing;
use App\Models\ProspectRse;
use App\Models\User;
use App\Support\ProspectionRelais;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prospection des organismes publics (mairies, CCAS, intercommunalités…)
 * pour le réemploi et le pouvoir d'achat. Adresses publiques de l'annuaire
 * officiel de l'administration (service-public.fr, données ouvertes).
 * Un premier mail, au plus une relance, désinscription en un clic.
 */
class ProspectionRse
{
    public const API = 'https://api-lannuaire.service-public.fr/api/explore/v2.1/catalog/datasets/api-lannuaire-administration/records';

    /** Département de chaque île (début du code INSEE des communes). */
    public const DEPARTEMENTS = ['La Réunion' => '974', 'Guadeloupe' => '971', 'Martinique' => '972', 'Guyane' => '973', 'Mayotte' => '976'];

    /** Ordre d'envoi : les plus concernés par le réemploi et le pouvoir d'achat d'abord. */
    public const PRIORITE = ['epci', 'ccas', 'mairie', 'cg', 'cr', 'ctu', 'mission_locale', 'prefecture'];

    /**
     * Ajoute les organismes d'une île (sans doublon, sans toucher au suivi).
     *
     * @return array{trouves:int,nouveaux:int,avec_email:int}
     */
    public static function importer(string $ile): array
    {
        $dep = self::DEPARTEMENTS[$ile] ?? null;
        if (! $dep) {
            return ['trouves' => 0, 'nouveaux' => 0, 'avec_email' => 0];
        }
        $types = array_keys((array) config('agent.rse_types', []));
        $filtre = implode(' or ', array_map(fn ($t) => 'pivot like "%' . $t . '%"', $types));
        $where = 'startswith(code_insee_commune, "' . $dep . '") and (' . $filtre . ')';

        $trouves = $nouveaux = $avecEmail = 0;
        for ($offset = 0; $offset < 1000; $offset += 100) {
            $reponse = Http::timeout(60)->retry(2, 2000, throw: false)
                ->withHeaders(['User-Agent' => 'Swapiles/1.0 (+https://swapiles.com)'])
                ->get(self::API, ['where' => $where, 'limit' => 100, 'offset' => $offset,
                    'select' => 'id,nom,adresse_courriel,pivot,site_internet,telephone,adresse,code_insee_commune']);
            if (! $reponse->successful()) {
                throw new \RuntimeException('Annuaire indisponible (HTTP ' . $reponse->status() . ')');
            }
            $lignes = (array) $reponse->json('results', []);
            foreach ($lignes as $r) {
                $trouves++;
                $res = self::enregistrer($ile, (array) $r);
                $nouveaux += $res === 'nouveau' ? 1 : 0;
            }
            if (count($lignes) < 100) {
                break;
            }
        }
        $avecEmail = ProspectRse::where('territoire', $ile)->whereNotNull('email')->count();

        return ['trouves' => $trouves, 'nouveaux' => $nouveaux, 'avec_email' => $avecEmail];
    }

    /** Champ de l'annuaire parfois encodé en JSON dans une chaîne. */
    private static function json(mixed $v): array
    {
        if (is_string($v)) {
            $v = json_decode($v, true);
        }

        return is_array($v) ? $v : [];
    }

    /** @return string 'nouveau' | 'connu' | 'ignore' */
    public static function enregistrer(string $ile, array $r): string
    {
        $nom = trim((string) ($r['nom'] ?? ''));
        $pivot = self::json($r['pivot'] ?? null)[0] ?? [];
        $type = (string) ($pivot['type_service_local'] ?? '');
        if ($nom === '' || ! array_key_exists($type, (array) config('agent.rse_types', []))) {
            return 'ignore';
        }
        $sourceId = (string) ($r['id'] ?? '') ?: 'h' . md5($nom . '|' . ($r['code_insee_commune'] ?? ''));
        $adresse = self::json($r['adresse'] ?? null)[0] ?? [];
        $site = self::json($r['site_internet'] ?? null)[0]['valeur'] ?? null;
        $tel = self::json($r['telephone'] ?? null)[0]['valeur'] ?? null;
        $email = null;
        foreach (preg_split('/[;,\s]+/', (string) ($r['adresse_courriel'] ?? '')) as $candidat) {
            if ($email = ProspectionRelais::emailValide($candidat)) {
                break;
            }
        }

        $p = ProspectRse::firstOrNew(['source_id' => Str::limit($sourceId, 120, '')]);
        $nouveau = ! $p->exists;
        $p->fill([
            'nom' => Str::limit($nom, 197, '…'),
            'type' => $type,
            'territoire' => $ile,
            'ville' => isset($adresse['nom_commune']) ? Str::limit(trim(preg_replace('/\s+cedex.*$/i', '', $adresse['nom_commune'])), 120, '') : $p->ville,
            'code_postal' => isset($adresse['code_postal']) ? Str::limit($adresse['code_postal'], 10, '') : $p->code_postal,
            'site_web' => $site ? Str::limit($site, 255, '') : $p->site_web,
            'telephone' => $tel ? Str::limit($tel, 40, '') : $p->telephone,
        ]);
        // On ne remplace pas une adresse déjà connue (ni le suivi).
        if (blank($p->email) && $email) {
            $p->email = $email;
        }
        if (blank($p->email) && $p->site_web && $nouveau) {
            try {
                $p->email = ProspectionRelais::emailDuSite($p->site_web);
            } catch (Throwable $e) {
                // site injoignable : tant pis
            }
        }
        $p->save();

        return $nouveau ? 'nouveau' : 'connu';
    }

    /** Chiffres publics de l'île, pour personnaliser le mail. */
    public static function chiffresIle(string $ile): array
    {
        return cache()->remember('agent:rse:chiffres:' . md5($ile), 3600, fn () => [
            'membres' => User::where('territoire', $ile)->count(),
            'annonces' => Listing::where('status', 'published')->where('territoire', $ile)->count(),
            'dons' => Listing::where('status', 'published')->where('territoire', $ile)
                ->where(fn ($q) => $q->where('listing_type', 'don')->orWhere('price', '<=', 0))->count(),
        ]);
    }

    public static function donnees(ProspectRse $p, bool $relance = false): array
    {
        return [
            'p' => $p,
            'relance' => $relance,
            'chiffres' => self::chiffresIle($p->territoire),
            'lien' => route('rse.prospection.clic', $p->jeton),
            'lienDesinscription' => route('rse.prospection.desinscription', $p->jeton),
            'contact' => config('partenaires.contact'),
            'signature' => config('agent.signature'),
        ];
    }

    public static function sujet(ProspectRse $p, bool $relance = false): string
    {
        $lieu = $p->ville ?: $p->territoire;

        return ($relance ? 'Re : ' : '') . match ($p->type) {
            'ccas' => 'Pouvoir d’achat à ' . $lieu . ' : s’équiper gratuitement ou à petit prix grâce au réemploi',
            'epci' => 'Réemploi et réduction des déchets sur votre territoire : une solution locale déjà en place',
            default => 'Réemploi et pouvoir d’achat à ' . $lieu . ' : une solution locale, sans coût pour vous',
        };
    }

    /** Premier mail. False si non contactable ou plafond atteint. */
    public static function envoyer(ProspectRse $p): bool
    {
        if (blank($p->email) || $p->statut !== 'nouveau' || Prospection::resteAujourdhui() <= 0) {
            return false;
        }
        Prospection::envoyer('emails.prospection-rse', self::donnees($p), $p->email, $p->nom, self::sujet($p));
        $p->forceFill(['statut' => 'contacte', 'contacte_at' => now(), 'nb_envois' => $p->nb_envois + 1])->save();

        return true;
    }

    /** Organismes à relancer : un seul rappel, sans réponse ni clic, au bout de N jours. */
    public static function aRelancer()
    {
        return ProspectRse::query()->where('statut', 'contacte')->where('nb_envois', 1)
            ->whereNull('repondu_at')->whereNull('clique_at')
            ->where('contacte_at', '<=', now()->subDays((int) config('agent.relance_jours', 7)));
    }

    public static function relancer(ProspectRse $p): bool
    {
        if (blank($p->email) || $p->statut !== 'contacte' || $p->nb_envois !== 1 || Prospection::resteAujourdhui() <= 0) {
            return false;
        }
        Prospection::envoyer('emails.prospection-rse', self::donnees($p, true), $p->email, $p->nom, self::sujet($p, true));
        $p->forceFill(['statut' => 'relance', 'relance_at' => now(), 'nb_envois' => 2])->save();

        return true;
    }

    /** Prochains organismes à contacter, les plus concernés d'abord, La Réunion en premier. */
    public static function prochains(int $max)
    {
        $ordreType = 'CASE type ' . implode(' ', array_map(fn ($t, $i) => "WHEN '{$t}' THEN {$i}", self::PRIORITE, array_keys(self::PRIORITE))) . ' ELSE 99 END';
        $ordreIle = 'CASE territoire ' . implode(' ', array_map(fn ($t, $i) => "WHEN '" . addslashes($t) . "' THEN {$i}", array_keys(self::DEPARTEMENTS), range(0, count(self::DEPARTEMENTS) - 1))) . ' ELSE 99 END';

        return ProspectRse::query()->contactable()->orderByRaw($ordreIle)->orderByRaw($ordreType)->orderBy('id')->limit($max)->get();
    }
}
