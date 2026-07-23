<?php

namespace App\Support;

/**
 * Coordonnées approximatives (centre-bourg) des 24 communes de La Réunion.
 *
 * Les adresses des utilisateurs ne contiennent pas de coordonnées GPS : on
 * localise donc chaque membre au centre de sa commune (ville renseignée), avec
 * un léger décalage côté carte pour éviter que les points se superposent.
 */
class ReunionCommunes
{
    /** @var array<string,array{0:float,1:float,2:string}> clé normalisée => [lat, lng, nom] */
    private const COMMUNES = [
        'saintdenis'            => [-20.8789, 55.4481, 'Saint-Denis'],
        'saintemarie'           => [-20.8967, 55.5495, 'Sainte-Marie'],
        'saintesuzanne'         => [-20.9083, 55.6056, 'Sainte-Suzanne'],
        'saintandre'            => [-20.9622, 55.6506, 'Saint-André'],
        'braspanon'             => [-21.0000, 55.6786, 'Bras-Panon'],
        'salazie'               => [-21.0294, 55.5411, 'Salazie'],
        'saintbenoit'           => [-21.0339, 55.7139, 'Saint-Benoît'],
        'laplainedespalmistes'  => [-21.1339, 55.6339, 'La Plaine-des-Palmistes'],
        'plainedespalmistes'    => [-21.1339, 55.6339, 'La Plaine-des-Palmistes'],
        'sainterose'            => [-21.1275, 55.7922, 'Sainte-Rose'],
        'saintphilippe'         => [-21.3597, 55.7686, 'Saint-Philippe'],
        'saintjoseph'           => [-21.3792, 55.6194, 'Saint-Joseph'],
        'petiteile'             => [-21.3506, 55.5686, 'Petite-Île'],
        'saintpierre'           => [-21.3419, 55.4778, 'Saint-Pierre'],
        'letampon'              => [-21.2783, 55.5153, 'Le Tampon'],
        'tampon'                => [-21.2783, 55.5153, 'Le Tampon'],
        'entredeux'             => [-21.2333, 55.4667, 'Entre-Deux'],
        'saintlouis'            => [-21.2861, 55.4114, 'Saint-Louis'],
        'cilaos'                => [-21.1336, 55.4717, 'Cilaos'],
        'letangsale'            => [-21.2650, 55.3644, "L'Étang-Salé"],
        'etangsale'             => [-21.2650, 55.3644, "L'Étang-Salé"],
        'lesavirons'            => [-21.2417, 55.3389, 'Les Avirons'],
        'avirons'               => [-21.2417, 55.3389, 'Les Avirons'],
        'saintleu'              => [-21.1706, 55.2894, 'Saint-Leu'],
        'troisbassins'          => [-21.0989, 55.2953, 'Trois-Bassins'],
        'saintpaul'             => [-21.0096, 55.2707, 'Saint-Paul'],
        'leport'                => [-20.9375, 55.2925, 'Le Port'],
        'port'                  => [-20.9375, 55.2925, 'Le Port'],
        'lapossession'          => [-20.9247, 55.3347, 'La Possession'],
        'possession'            => [-20.9247, 55.3347, 'La Possession'],
    ];

    /** Centre de l'île (repli). */
    public const CENTER = [-21.1151, 55.5364];

    /** Normalise un nom de ville pour la comparaison. */
    public static function normalize(?string $city): string
    {
        $s = mb_strtolower(trim((string) $city));

        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ù' => 'u', 'ç' => 'c',
            '’' => '', "'" => '',
        ]);

        // « st » / « ste » -> « saint » / « sainte »
        $s = preg_replace('/\bste\b/', 'sainte', $s);
        $s = preg_replace('/\bst\b/', 'saint', $s);

        return preg_replace('/[^a-z0-9]/', '', $s);
    }

    /** Coordonnées [lat, lng] pour une ville, ou null si non reconnue. */
    public static function coords(?string $city): ?array
    {
        $key = self::normalize($city);

        if ($key === '') {
            return null;
        }

        if (isset(self::COMMUNES[$key])) {
            return [self::COMMUNES[$key][0], self::COMMUNES[$key][1]];
        }

        return null;
    }
}
