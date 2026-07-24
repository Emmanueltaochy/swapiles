<?php

namespace App\Support;

/**
 * Géolocalisation approximative (centre-bourg) des communes des territoires
 * Swap'Îles, pour la carte des membres. La valeur stockée (ville) est mise en
 * correspondance avec une commune ; sinon le membre est placé au centre de son
 * île (localisation approximative).
 */
class DomTomGeo
{
    /** [lat, lng, zoom] par territoire. */
    private const CENTERS = [
        'La Réunion' => [-21.1151, 55.5364, 10],
        'Martinique' => [14.6415, -61.0242, 11],
        'Guadeloupe' => [16.2500, -61.5500, 10],
        'Guyane'     => [4.6000, -52.9000, 8],
        'Mayotte'    => [-12.8200, 45.1600, 11],
    ];

    /** @var array<string,array<string,array{0:float,1:float,2:string}>> */
    private const COMMUNES = [
        'La Réunion' => [
            'saintdenis' => [-20.8789, 55.4481, 'Saint-Denis'],
            'saintemarie' => [-20.8967, 55.5495, 'Sainte-Marie'],
            'saintesuzanne' => [-20.9083, 55.6056, 'Sainte-Suzanne'],
            'saintandre' => [-20.9622, 55.6506, 'Saint-André'],
            'braspanon' => [-21.0000, 55.6786, 'Bras-Panon'],
            'salazie' => [-21.0294, 55.5411, 'Salazie'],
            'saintbenoit' => [-21.0339, 55.7139, 'Saint-Benoît'],
            'laplainedespalmistes' => [-21.1339, 55.6339, 'La Plaine-des-Palmistes'],
            'plainedespalmistes' => [-21.1339, 55.6339, 'La Plaine-des-Palmistes'],
            'sainterose' => [-21.1275, 55.7922, 'Sainte-Rose'],
            'saintphilippe' => [-21.3597, 55.7686, 'Saint-Philippe'],
            'saintjoseph' => [-21.3792, 55.6194, 'Saint-Joseph'],
            'petiteile' => [-21.3506, 55.5686, 'Petite-Île'],
            'saintpierre' => [-21.3419, 55.4778, 'Saint-Pierre'],
            'letampon' => [-21.2783, 55.5153, 'Le Tampon'],
            'tampon' => [-21.2783, 55.5153, 'Le Tampon'],
            'entredeux' => [-21.2333, 55.4667, 'Entre-Deux'],
            'saintlouis' => [-21.2861, 55.4114, 'Saint-Louis'],
            'cilaos' => [-21.1336, 55.4717, 'Cilaos'],
            'letangsale' => [-21.2650, 55.3644, "L'Étang-Salé"],
            'etangsale' => [-21.2650, 55.3644, "L'Étang-Salé"],
            'lesavirons' => [-21.2417, 55.3389, 'Les Avirons'],
            'avirons' => [-21.2417, 55.3389, 'Les Avirons'],
            'saintleu' => [-21.1706, 55.2894, 'Saint-Leu'],
            'troisbassins' => [-21.0989, 55.2953, 'Trois-Bassins'],
            'saintpaul' => [-21.0096, 55.2707, 'Saint-Paul'],
            'leport' => [-20.9375, 55.2925, 'Le Port'],
            'port' => [-20.9375, 55.2925, 'Le Port'],
            'lapossession' => [-20.9247, 55.3347, 'La Possession'],
            'possession' => [-20.9247, 55.3347, 'La Possession'],
        ],
        'Martinique' => [
            'fortdefrance' => [14.6161, -61.0588, 'Fort-de-France'],
            'lelamentin' => [14.6089, -60.9997, 'Le Lamentin'],
            'lamentin' => [14.6089, -60.9997, 'Le Lamentin'],
            'lerobert' => [14.6772, -60.9403, 'Le Robert'],
            'robert' => [14.6772, -60.9403, 'Le Robert'],
            'schoelcher' => [14.6144, -61.0997, 'Schœlcher'],
            'saintemarie' => [14.7789, -60.9931, 'Sainte-Marie'],
            'lefrancois' => [14.6153, -60.9036, 'Le François'],
            'francois' => [14.6153, -60.9036, 'Le François'],
            'ducos' => [14.5825, -60.9700, 'Ducos'],
            'rivierepilote' => [14.4844, -60.8967, 'Rivière-Pilote'],
            'lemarin' => [14.4694, -60.8683, 'Le Marin'],
            'marin' => [14.4694, -60.8683, 'Le Marin'],
            'sainteluce' => [14.4681, -60.9256, 'Sainte-Luce'],
            'latrinite' => [14.7381, -60.9628, 'La Trinité'],
            'trinite' => [14.7381, -60.9628, 'La Trinité'],
            'saintjoseph' => [14.6672, -61.0342, 'Saint-Joseph'],
            'lestroisilets' => [14.5389, -61.0347, 'Les Trois-Îlets'],
            'troisilets' => [14.5389, -61.0347, 'Les Trois-Îlets'],
            'lelorrain' => [14.8342, -61.0553, 'Le Lorrain'],
            'levauclin' => [14.5461, -60.8394, 'Le Vauclin'],
            'vauclin' => [14.5461, -60.8394, 'Le Vauclin'],
            'rivieresalee' => [14.5306, -60.9750, 'Rivière-Salée'],
            'legrosmorne' => [14.7167, -60.9781, 'Le Gros-Morne'],
            'grosmorne' => [14.7167, -60.9781, 'Le Gros-Morne'],
            'sainteanne' => [14.4442, -60.8514, 'Sainte-Anne'],
            'lediamant' => [14.4831, -61.0272, 'Le Diamant'],
            'diamant' => [14.4831, -61.0272, 'Le Diamant'],
            'casepilote' => [14.6461, -61.1319, 'Case-Pilote'],
            'lecarbet' => [14.7100, -61.1178, 'Le Carbet'],
            'carbet' => [14.7100, -61.1178, 'Le Carbet'],
            'saintpierre' => [14.7422, -61.1758, 'Saint-Pierre'],
            'lemarigot' => [14.8025, -60.9575, 'Le Marigot'],
            'bassepointe' => [14.8697, -61.1214, 'Basse-Pointe'],
            'lemornerouge' => [14.7719, -61.1381, 'Le Morne-Rouge'],
            'mornerouge' => [14.7719, -61.1381, 'Le Morne-Rouge'],
            'lesansesdarlet' => [14.4886, -61.0847, "Les Anses-d'Arlet"],
            'saintesprit' => [14.5556, -60.9294, 'Saint-Esprit'],
        ],
        'Guadeloupe' => [
            'lesabymes' => [16.2712, -61.5049, 'Les Abymes'],
            'abymes' => [16.2712, -61.5049, 'Les Abymes'],
            'pointeapitre' => [16.2410, -61.5340, 'Pointe-à-Pitre'],
            'legosier' => [16.2058, -61.4919, 'Le Gosier'],
            'gosier' => [16.2058, -61.4919, 'Le Gosier'],
            'baiemahault' => [16.2683, -61.5876, 'Baie-Mahault'],
            'lemoule' => [16.3336, -61.3486, 'Le Moule'],
            'moule' => [16.3336, -61.3486, 'Le Moule'],
            'sainteanne' => [16.2258, -61.3806, 'Sainte-Anne'],
            'petitbourg' => [16.1906, -61.5906, 'Petit-Bourg'],
            'basseterre' => [15.9985, -61.7261, 'Basse-Terre'],
            'sainterose' => [16.3319, -61.6969, 'Sainte-Rose'],
            'lelamentin' => [16.2694, -61.6314, 'Lamentin'],
            'lamentin' => [16.2694, -61.6314, 'Lamentin'],
            'capesterrebelleeau' => [16.0447, -61.5647, 'Capesterre-Belle-Eau'],
            'gourbeyre' => [15.9878, -61.6889, 'Gourbeyre'],
            'saintfrancois' => [16.2522, -61.2708, 'Saint-François'],
            'morancealeau' => [16.3306, -61.5169, "Morne-à-l'Eau"],
            'mornealeau' => [16.3306, -61.5169, "Morne-à-l'Eau"],
            'petitcanal' => [16.3781, -61.4914, 'Petit-Canal'],
            'portlouis' => [16.4200, -61.5306, 'Port-Louis'],
            'deshaies' => [16.2969, -61.7947, 'Deshaies'],
            'bouillante' => [16.1339, -61.7686, 'Bouillante'],
            'troisrivieres' => [15.9694, -61.6408, 'Trois-Rivières'],
            'vieuxhabitants' => [16.0578, -61.7622, 'Vieux-Habitants'],
            'baillif' => [16.0244, -61.7442, 'Baillif'],
            'saintclaude' => [16.0244, -61.6931, 'Saint-Claude'],
            'pointenoire' => [16.2339, -61.7883, 'Pointe-Noire'],
            'ansebertrand' => [16.4728, -61.5061, 'Anse-Bertrand'],
            'goyave' => [16.1319, -61.5731, 'Goyave'],
            'vieuxfort' => [15.9506, -61.7050, 'Vieux-Fort'],
            'capesterredemariegalante' => [15.8992, -61.2261, 'Capesterre-de-Marie-Galante'],
            'grandbourg' => [15.8833, -61.3167, 'Grand-Bourg'],
            'saintlouis' => [15.9575, -61.3117, 'Saint-Louis'],
            'terredehaut' => [15.8667, -61.5833, 'Terre-de-Haut'],
            'terredebas' => [15.8433, -61.6408, 'Terre-de-Bas'],
            'ladesirade' => [16.3167, -61.0833, 'La Désirade'],
            'desirade' => [16.3167, -61.0833, 'La Désirade'],
        ],
        'Guyane' => [
            'cayenne' => [4.9227, -52.3269, 'Cayenne'],
            'matoury' => [4.8483, -52.3306, 'Matoury'],
            'remiremontjoly' => [4.8878, -52.2731, 'Rémire-Montjoly'],
            'remire' => [4.8878, -52.2731, 'Rémire-Montjoly'],
            'kourou' => [5.1594, -52.6503, 'Kourou'],
            'saintlaurentdumaroni' => [5.4980, -54.0300, 'Saint-Laurent-du-Maroni'],
            'saintlaurent' => [5.4980, -54.0300, 'Saint-Laurent-du-Maroni'],
            'macouria' => [4.9236, -52.4083, 'Macouria'],
            'mana' => [5.6603, -53.7783, 'Mana'],
            'maripasoula' => [3.6400, -54.0300, 'Maripasoula'],
            'apatou' => [5.1583, -54.3417, 'Apatou'],
            'grandsanti' => [4.2506, -54.3781, 'Grand-Santi'],
            'papaichton' => [3.8506, -54.2000, 'Papaïchton'],
            'saintgeorges' => [3.8931, -51.8064, 'Saint-Georges'],
            'sinnamary' => [5.3781, -52.9600, 'Sinnamary'],
            'iracoubo' => [5.4808, -53.2094, 'Iracoubo'],
            'roura' => [4.7264, -52.3239, 'Roura'],
            'montsinery' => [4.8931, -52.5069, 'Montsinéry-Tonnegrande'],
            'regina' => [4.3169, -52.1319, 'Régina'],
            'awala' => [5.7439, -53.9269, 'Awala-Yalimapo'],
            'camopi' => [3.1667, -52.3333, 'Camopi'],
            'saul' => [3.6178, -53.2083, 'Saül'],
        ],
        'Mayotte' => [
            'mamoudzou' => [-12.7806, 45.2278, 'Mamoudzou'],
            'koungou' => [-12.7358, 45.2044, 'Koungou'],
            'dembeni' => [-12.8419, 45.1719, 'Dembéni'],
            'dzaoudzi' => [-12.7886, 45.2581, 'Dzaoudzi'],
            'pamandzi' => [-12.7972, 45.2811, 'Pamandzi'],
            'sada' => [-12.8547, 45.1128, 'Sada'],
            'bandraboua' => [-12.7000, 45.1219, 'Bandraboua'],
            'boueni' => [-12.9047, 45.0906, 'Bouéni'],
            'chiconi' => [-12.8347, 45.1075, 'Chiconi'],
            'chirongui' => [-12.9317, 45.1508, 'Chirongui'],
            'kanikeli' => [-12.9583, 45.1039, 'Kani-Kéli'],
            'mtsamboro' => [-12.7261, 45.0653, 'Mtsamboro'],
            'mtsangamouji' => [-12.7867, 45.0917, 'M\'Tsangamouji'],
            'ouangani' => [-12.8567, 45.1361, 'Ouangani'],
            'tsingoni' => [-12.7883, 45.1006, 'Tsingoni'],
            'acoua' => [-12.7208, 45.0578, 'Acoua'],
            'bandrele' => [-12.9075, 45.1922, 'Bandrélé'],
        ],
    ];

    public static function territoires(): array
    {
        return array_keys(self::CENTERS);
    }

    /** [lat, lng, zoom] du centre d'un territoire. */
    public static function center(string $territoire): array
    {
        return self::CENTERS[$territoire] ?? self::CENTERS['La Réunion'];
    }

    public static function normalize(?string $city): string
    {
        $s = mb_strtolower(trim((string) $city));

        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ù' => 'u', 'ç' => 'c',
            '’' => '', "'" => '',
        ]);

        $s = preg_replace('/\bste\b/', 'sainte', $s);
        $s = preg_replace('/\bst\b/', 'saint', $s);

        return preg_replace('/[^a-z0-9]/', '', $s);
    }

    /** Coordonnées [lat, lng] d'une ville dans un territoire, ou null si inconnue. */
    public static function coords(string $territoire, ?string $city): ?array
    {
        $key = self::normalize($city);
        if ($key === '' || ! isset(self::COMMUNES[$territoire])) {
            return null;
        }

        if (isset(self::COMMUNES[$territoire][$key])) {
            return [self::COMMUNES[$territoire][$key][0], self::COMMUNES[$territoire][$key][1]];
        }

        return null;
    }
}
