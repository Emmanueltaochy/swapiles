<?php

namespace App\Support;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Recherche par photo : l'acheteur photographie ce qu'il cherche, l'IA en
 * tire quelques mots-clés et une catégorie, puis on lance la recherche
 * classique du site (même moteur, mêmes résultats). Même budget et même
 * clé que l'IA dépôt.
 */
class RecherchePhoto
{
    /** @return array{q:string,categorie:?string} */
    public static function analyser(UploadedFile $photo, ?User $membre): array
    {
        $data = IaDepot::image($photo);
        if (! $data) {
            throw new RuntimeException('Photo illisible.');
        }

        $modele = (string) config('ia_depot.modele', 'claude-haiku-5-5');
        $client = app()->bound('ia_depot.client')
            ? app('ia_depot.client')
            : new Client(apiKey: (string) config('ia_depot.cle'), requestOptions: ['timeout' => 30, 'maxRetries' => 1]);

        try {
            $reponse = $client->messages->create(
                maxTokens: 1000,
                model: $modele,
                system: [['type' => 'text', 'text' => self::consignes(), 'cacheControl' => ['type' => 'ephemeral']]],
                messages: [['role' => 'user', 'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => $data]],
                    ['type' => 'text', 'text' => 'Que faut-il chercher pour trouver un article comme celui-ci ?'],
                ]]],
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => [
                    'type' => 'object',
                    'properties' => ['mots_cles' => ['type' => 'string'], 'categorie' => ['type' => 'string']],
                    'required' => ['mots_cles', 'categorie'],
                    'additionalProperties' => false,
                ]]],
            );
        } catch (APIStatusException|APIConnectionException $e) {
            IaDepot::noter($membre, $modele, 1, 0, 0, 'erreur', 'recherche_photo');
            Log::warning('Recherche par photo : appel en échec', ['erreur' => $e->getMessage()]);
            throw new RuntimeException('La recherche par photo est indisponible pour le moment.', 0, $e);
        }

        $entree = (int) $reponse->usage->inputTokens
            + (int) ($reponse->usage->cacheCreationInputTokens ?? 0) + (int) ($reponse->usage->cacheReadInputTokens ?? 0);
        $sortie = (int) $reponse->usage->outputTokens;

        $json = null;
        if ($reponse->stopReason !== 'refusal') {
            foreach ($reponse->content as $bloc) {
                if ($bloc->type === 'text') {
                    $json = json_decode($bloc->text, true);
                    break;
                }
            }
        }
        IaDepot::noter($membre, $modele, 1, $entree, $sortie, is_array($json) ? 'ok' : 'erreur', 'recherche_photo');
        if (! is_array($json) || trim((string) ($json['mots_cles'] ?? '')) === '') {
            throw new RuntimeException('Je n’ai pas reconnu l’article sur cette photo.');
        }

        $categorie = mb_strtolower(trim((string) ($json['categorie'] ?? '')));

        return [
            'q' => Str::limit(trim((string) $json['mots_cles']), 60, ''),
            'categorie' => array_key_exists($categorie, Categories::niveau1()) ? $categorie : null,
        ];
    }

    private static function consignes(): string
    {
        $categories = [];
        foreach (Categories::niveau1() as $cle => $c) {
            $categories[] = $cle . ' : ' . $c['label'];
        }

        return implode("\n", [
            'Tu aides un acheteur à chercher un article de seconde main sur Swap’Îles à partir d’une photo.',
            '- mots_cles : 1 à 3 mots en français, génériques, comme une personne les taperait (exemples : « robe fleurie », « poussette », « basket blanche »). Pas de marque, sauf si elle est l’essentiel de l’article.',
            '- categorie : une clé de la liste ci-dessous, ou une chaîne vide si aucune ne convient.',
            '',
            implode("\n", $categories),
        ]);
    }
}
