<?php

namespace App\Support;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * IA dépôt : à partir des photos, Claude propose les champs de l'annonce
 * (titre, description, catégorie, état, marque, taille, prix, poids).
 * Le vendeur relit et corrige avant de publier : rien n'est publié seul.
 *
 * Masqué tant que la clé ANTHROPIC_API_KEY n'est pas renseignée. Garde-fous :
 * un nombre d'appels par membre et par jour, et un plafond de dépense mensuel.
 */
class IaDepot
{
    public const ETATS = ['Neuf avec étiquette', 'Neuf sans étiquette', 'Très bon état', 'Bon état', 'Satisfaisant'];

    /** Le bouton s'affiche-t-il ? (clé présente et budget du mois pas épuisé) */
    public static function disponible(): bool
    {
        return filled(config('ia_depot.cle')) && self::depenseDuMois() < (float) config('ia_depot.budget_mensuel_usd', 10);
    }

    public static function resteAujourdhui(User $membre): int
    {
        $faits = DB::table('ia_depot_usages')->where('user_id', $membre->id)->where('fonction', 'depot')->where('created_at', '>=', today())->count();

        return max(0, (int) config('ia_depot.limite_par_jour', 20) - $faits);
    }

    public static function depenseDuMois(): float
    {
        return (float) DB::table('ia_depot_usages')->where('created_at', '>=', now()->startOfMonth())->sum('cout_usd');
    }

    /**
     * Chiffres pour l'admin : mois en cours et 30 derniers jours (totaux, sans nom de membre).
     *
     * @return array<string,mixed>
     */
    public static function bilan(): array
    {
        $mois = DB::table('ia_depot_usages')->where('created_at', '>=', now()->startOfMonth());
        $appels = (clone $mois)->count();
        $ok = (clone $mois)->where('statut', 'ok')->count();
        $cout = (float) (clone $mois)->sum('cout_usd');

        return [
            'active' => filled(config('ia_depot.cle')),
            'disponible' => self::disponible(),
            'modele' => (string) config('ia_depot.modele'),
            'budget' => (float) config('ia_depot.budget_mensuel_usd', 10),
            'limite_jour' => (int) config('ia_depot.limite_par_jour', 20),
            'appels' => $appels,
            'reussis' => $ok,
            'membres' => (clone $mois)->whereNotNull('user_id')->distinct()->count('user_id'),
            'depots' => (clone $mois)->where('fonction', 'depot')->count(),
            'recherches_photo' => (clone $mois)->where('fonction', 'recherche_photo')->count(),
            'cout' => round($cout, 4),
            'cout_moyen' => $appels > 0 ? round($cout / $appels, 5) : 0.0,
            'par_jour' => DB::table('ia_depot_usages')
                ->where('created_at', '>=', now()->subDays(29)->startOfDay())
                ->selectRaw('DATE(created_at) as jour, COUNT(*) as appels, SUM(cout_usd) as cout')
                ->groupBy('jour')->orderByDesc('jour')->get()
                ->map(fn ($l) => ['jour' => (string) $l->jour, 'appels' => (int) $l->appels, 'cout' => round((float) $l->cout, 4)])
                ->all(),
        ];
    }

    /**
     * Les dernières utilisations, une par ligne, pour l'admin : qui, quoi,
     * l'annonce publiée ensuite (dépôt) ou la recherche obtenue (photo).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function journal(int $limite = 100): array
    {
        $lignes = DB::table('ia_depot_usages')
            ->leftJoin('users', 'users.id', '=', 'ia_depot_usages.user_id')
            ->leftJoin('listings', function ($j) {
                $j->on('listings.submission_token', '=', 'ia_depot_usages.jeton_depot')
                    ->whereNotNull('ia_depot_usages.jeton_depot');
            })
            ->orderByDesc('ia_depot_usages.id')
            ->limit($limite)
            ->get([
                'ia_depot_usages.*', 'users.name as membre_nom', 'users.email as membre_email',
                'listings.id as annonce_id', 'listings.title as annonce_titre', 'listings.status as annonce_statut',
            ]);

        return $lignes->map(function ($l) {
            $date = \Illuminate\Support\Carbon::parse($l->created_at);
            $probable = $l->fonction === 'depot' && ! $l->annonce_id && $l->user_id
                ? self::annonceProbable((int) $l->user_id, $date)
                : null;

            return [
                'date' => $date,
                'fonction' => $l->fonction,
                'membre_id' => $l->user_id,
                'membre' => $l->membre_nom,
                'annonce_id' => $l->annonce_id ?? $probable?->id,
                'annonce' => $l->annonce_titre ?? $probable?->title,
                'annonce_statut' => $l->annonce_statut ?? $probable?->status,
                // Retrouvée par le membre et l'heure, pas par le jeton du formulaire.
                'annonce_probable' => $probable !== null,
                'resultat' => $l->resultat,
                'statut' => $l->statut,
                'photos' => (int) $l->photos,
                'cout' => (float) $l->cout_usd,
            ];
        })->all();
    }

    /**
     * Annonce publiée par le membre dans les 3 h qui suivent l'utilisation,
     * quand le jeton ne la relie pas : la page de dépôt a été rechargée entre
     * l'IA et la publication (nouveau jeton), ou l'appli a été rouverte.
     */
    private static function annonceProbable(int $membreId, \Illuminate\Support\Carbon $date): ?object
    {
        return DB::table('listings')
            ->where('user_id', $membreId)
            ->whereBetween('created_at', [$date->copy()->subMinute(), $date->copy()->addHours(3)])
            ->orderBy('created_at')
            ->first(['id', 'title', 'status']);
    }

    /**
     * @param  array<int,UploadedFile>  $photos
     * @return array{titre:string,description:string,categorie_1:?string,categorie_2:?string,categorie_3:?string,etat:?string,marque:string,taille:string,prix:?int,poids_g:?int}
     */
    public static function analyser(User $membre, array $photos, ?string $jeton = null): array
    {
        $photos = array_slice(array_values($photos), 0, (int) config('ia_depot.photos_max', 4));
        $images = array_values(array_filter(array_map(fn (UploadedFile $f) => self::image($f), $photos)));
        if ($images === []) {
            throw new RuntimeException('Aucune photo lisible.');
        }

        $modele = (string) config('ia_depot.modele', 'claude-haiku-5-5');
        $contenu = [];
        foreach ($images as $data) {
            $contenu[] = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => $data]];
        }
        $contenu[] = ['type' => 'text', 'text' => 'Voici les photos de l’article à vendre. Remplis la fiche.'];

        // Dans les tests, un faux client est enregistré sous « ia_depot.client » : aucun appel payant.
        $client = app()->bound('ia_depot.client')
            ? app('ia_depot.client')
            : new Client(apiKey: (string) config('ia_depot.cle'), requestOptions: ['timeout' => 40, 'maxRetries' => 1]);

        try {
            $reponse = $client->messages->create(
                maxTokens: 4000,
                model: $modele,
                system: [['type' => 'text', 'text' => self::consignes(), 'cacheControl' => ['type' => 'ephemeral']]],
                messages: [['role' => 'user', 'content' => $contenu]],
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::schema()]],
            );
        } catch (APIStatusException|APIConnectionException $e) {
            self::noter($membre, $modele, count($images), 0, 0, 'erreur', 'depot', $jeton);
            Log::warning('IA dépôt : appel en échec', ['erreur' => $e->getMessage()]);
            throw new RuntimeException('L’IA est indisponible pour le moment.', 0, $e);
        }

        $entree = (int) $reponse->usage->inputTokens
            + (int) ($reponse->usage->cacheCreationInputTokens ?? 0) + (int) ($reponse->usage->cacheReadInputTokens ?? 0);
        $sortie = (int) $reponse->usage->outputTokens;

        if ($reponse->stopReason === 'refusal') {
            self::noter($membre, $modele, count($images), $entree, $sortie, 'refus', 'depot', $jeton);
            throw new RuntimeException('L’IA n’a pas pu analyser ces photos.');
        }

        $json = null;
        foreach ($reponse->content as $bloc) {
            if ($bloc->type === 'text') {
                $json = json_decode($bloc->text, true);
                break;
            }
        }
        $champs = is_array($json) ? self::nettoyer($json) : null;
        self::noter($membre, $modele, count($images), $entree, $sortie, $champs ? 'ok' : 'erreur', 'depot', $jeton,
            $champs ? trim($champs['titre'] . ($champs['prix'] ? ' · ' . $champs['prix'] . ' €' : '')) : null);
        if (! $champs) {
            throw new RuntimeException('Réponse de l’IA illisible.');
        }

        return $champs;
    }

    /** Ne garde que des valeurs que le formulaire accepte (catégorie existante, état connu…). */
    public static function nettoyer(array $json): array
    {
        [$c1, $c2, $c3] = array_pad(explode('/', (string) ($json['categorie'] ?? '')), 3, null);
        $arbre = Categories::pourJavascript();
        if (! isset($arbre[$c1])) {
            $c1 = $c2 = $c3 = null;
        } elseif (! isset($arbre[$c1][$c2])) {
            $c2 = $c3 = null;
        } elseif (! isset($arbre[$c1][$c2]['children'][$c3])) {
            $c3 = null;
        }

        $prix = isset($json['prix']) && is_numeric($json['prix']) ? (int) round((float) $json['prix']) : null;
        $poids = isset($json['poids_g']) && is_numeric($json['poids_g']) ? (int) round((float) $json['poids_g']) : null;

        return [
            'titre' => Str::limit(trim((string) ($json['titre'] ?? '')), 80, ''),
            'description' => Str::limit(trim((string) ($json['description'] ?? '')), 1500, ''),
            'categorie_1' => $c1,
            'categorie_2' => $c2,
            'categorie_3' => $c3,
            'etat' => in_array($json['etat'] ?? null, self::ETATS, true) ? $json['etat'] : null,
            'marque' => Str::limit(trim((string) ($json['marque'] ?? '')), 60, ''),
            'taille' => Str::limit(trim((string) ($json['taille'] ?? '')), 40, ''),
            'prix' => $prix !== null && $prix > 0 && $prix <= 5000 ? $prix : null,
            'poids_g' => $poids !== null && $poids > 0 && $poids <= 30000 ? $poids : null,
        ];
    }

    /** Photo réduite en JPEG (côté max configurable), encodée en base64. */
    public static function image(UploadedFile $fichier): ?string
    {
        $source = @imagecreatefromstring((string) file_get_contents($fichier->getRealPath()));
        if (! $source) {
            return null;
        }

        $l = imagesx($source);
        $h = imagesy($source);
        $max = (int) config('ia_depot.cote_max_px', 1024);
        $ratio = min(1, $max / max($l, $h));
        $image = $source;
        if ($ratio < 1) {
            $image = imagescale($source, (int) round($l * $ratio), (int) round($h * $ratio));
        }

        ob_start();
        imagejpeg($image, null, 80);
        $jpeg = (string) ob_get_clean();

        return base64_encode($jpeg);
    }

    /** Note un appel (coût estimé). $fonction : depot | recherche_photo. */
    public static function noter(?User $membre, string $modele, int $photos, int $entree, int $sortie, string $statut, string $fonction = 'depot', ?string $jeton = null, ?string $resultat = null): void
    {
        [$prixEntree, $prixSortie] = config('ia_depot.tarifs.' . $modele, [0, 0]);

        DB::table('ia_depot_usages')->insert([
            'user_id' => $membre?->id,
            'fonction' => $fonction,
            'jeton_depot' => $jeton ? Str::limit($jeton, 64, '') : null,
            'resultat' => $resultat ? Str::limit($resultat, 250) : null,
            'modele' => $modele,
            'photos' => $photos,
            'jetons_entree' => $entree,
            'jetons_sortie' => $sortie,
            'cout_usd' => round(($entree * $prixEntree + $sortie * $prixSortie) / 1_000_000, 6),
            'statut' => $statut,
            'created_at' => now(),
        ]);
    }

    private static function consignes(): string
    {
        $categories = [];
        foreach (Categories::pourJavascript() as $c1 => $n2s) {
            foreach ($n2s as $c2 => $n2) {
                foreach ($n2['children'] as $c3 => $libelle) {
                    $categories[] = "{$c1}/{$c2}/{$c3} : " . (Categories::label($c1) ?? $c1) . ' > ' . $n2['label'] . ' > ' . $libelle;
                }
            }
        }

        return implode("\n", [
            'Tu aides un particulier à déposer une annonce de seconde main sur Swap’Îles (La Réunion, Antilles, Guyane, Mayotte).',
            'À partir des photos, remplis la fiche en français, avec un ton simple et honnête.',
            '- titre : court et précis (type d’article, marque si visible, couleur, taille si lisible), 60 caractères au plus.',
            '- description : 2 à 4 phrases sur ce qu’on voit (matière, couleur, détails, défauts visibles). N’invente rien qu’on ne voit pas.',
            '- categorie : exactement une ligne de la liste ci-dessous, sous la forme categorie/sous-categorie/type.',
            '- etat : une valeur parmi ' . implode(', ', self::ETATS) . ', ou une chaîne vide si on ne peut pas juger.',
            '- marque et taille : seulement si elles sont lisibles sur une étiquette ou évidentes, sinon une chaîne vide.',
            '- prix : un prix de seconde main raisonnable en euros, entier, pour une vente rapide dans les îles.',
            '- poids_g : poids estimé de l’article emballé, en grammes.',
            '',
            'Catégories possibles :',
            implode("\n", $categories),
        ]);
    }

    private static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'titre' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'categorie' => ['type' => 'string'],
                'etat' => ['type' => 'string'],
                'marque' => ['type' => 'string'],
                'taille' => ['type' => 'string'],
                'prix' => ['type' => 'integer'],
                'poids_g' => ['type' => 'integer'],
            ],
            'required' => ['titre', 'description', 'categorie', 'etat', 'marque', 'taille', 'prix', 'poids_g'],
            'additionalProperties' => false,
        ];
    }
}
