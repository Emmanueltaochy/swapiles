<?php

namespace App\Support;

/**
 * Arbre des categories, source unique.
 *
 * Il vivait en dur dans le JavaScript du formulaire de depot : le menu de
 * navigation et la recherche ne pouvaient donc pas le lire, et les
 * sous-categories restaient invisibles tant qu'on n'etait pas en train de
 * publier une annonce. Le formulaire lit maintenant cet arbre-ci, ce qui
 * garantit que ce qu'un vendeur choisit est exactement ce qu'un acheteur
 * peut parcourir.
 */
class Categories
{
    /**
     * Trois niveaux : categorie => sous-categorie => type d'article.
     */
    public const ARBRE = [
        'femme' => [
            'label' => 'Femme',
            'emoji' => '👗',
            'enfants' => [
                'vetements' => [
                    'label' => 'Vêtements',
                    'enfants' => [
                        'robes' => 'Robes',
                        'hauts-et-t-shirts' => 'Hauts et t-shirts',
                        'jeans-pantalons-shorts' => 'Jeans, pantalons, shorts',
                        'jupes' => 'Jupes',
                        'ensembles-combi' => 'Ensembles / combinaisons',
                        'maillots-de-bain' => 'Maillots de bain',
                        'sous-vetements' => 'Sous-vêtements',
                    ],
                ],
                'chaussures' => [
                    'label' => 'Chaussures',
                    'enfants' => [
                        'baskets' => 'Baskets',
                        'sandales' => 'Sandales',
                        'talons' => 'Talons',
                        'bottes-bottines' => 'Bottes / bottines',
                        'savates' => 'Savates',
                    ],
                ],
                'accessoires' => [
                    'label' => 'Accessoires',
                    'enfants' => [
                        'sacs-a-main' => 'Sacs à main',
                        'sacs-a-dos' => 'Sacs à dos',
                        'bijoux' => 'Bijoux',
                        'montres' => 'Montres',
                        'ceintures' => 'Ceintures',
                        'bananes' => 'Bananes',
                    ],
                ],
            ],
        ],
        'homme' => [
            'label' => 'Homme',
            'emoji' => '👕',
            'enfants' => [
                'vetements' => [
                    'label' => 'Vêtements',
                    'enfants' => [
                        'hauts-et-t-shirts' => 'Hauts et t-shirts',
                        'jeans-pantalons-shorts' => 'Jeans, pantalons, shorts',
                        'costumes' => 'Costumes',
                        'maillots-de-bain' => 'Maillots de bain',
                    ],
                ],
                'chaussures-homme' => [
                    'label' => 'Chaussures',
                    'enfants' => [
                        'baskets' => 'Baskets',
                        'savates-sandales' => 'Savates / sandales',
                        'bottes' => 'Bottes',
                    ],
                ],
                'accessoires' => [
                    'label' => 'Accessoires',
                    'enfants' => [
                        'montres' => 'Montres',
                        'ceintures' => 'Ceintures',
                        'sacs-a-dos' => 'Sacs à dos',
                        'bijoux' => 'Bijoux',
                    ],
                ],
            ],
        ],
        'enfant' => [
            'label' => 'Enfant',
            'emoji' => '🧸',
            'enfants' => [
                'vetements-enfants' => [
                    'label' => 'Vêtements enfants',
                    'enfants' => [
                        'robes-de-ceremonie' => 'Robes de cérémonie',
                        'hauts-et-t-shirts' => 'Hauts et t-shirts',
                        'jeans-pantalons-shorts' => 'Jeans, pantalons, shorts',
                        'pyjamas' => 'Pyjamas',
                        'bodies' => 'Bodies',
                        'costumes-de-carnaval-enfant' => 'Costumes de carnaval',
                        'costumes-de-ceremonie-enfant' => 'Costumes de cérémonie',
                    ],
                ],
                'chaussures-enfants' => [
                    'label' => 'Chaussures enfants',
                    'enfants' => [
                        'baskets' => 'Baskets',
                        'sandales' => 'Sandales',
                        'chaussons' => 'Chaussons',
                    ],
                ],
                'puericulture' => [
                    'label' => 'Puériculture',
                    'enfants' => [
                        'poussettes' => 'Poussettes',
                        'sieges-auto' => 'Sièges auto',
                        'lits-bebe' => 'Lits bébé',
                        'porte-bebes-echarpes' => 'Porte-bébés / écharpes',
                        'chaises-hautes' => 'Chaises hautes',
                        'biberons' => 'Biberons',
                    ],
                ],
                'jeux-enfant' => [
                    'label' => 'Jeux / jouets',
                    'enfants' => [
                        'jouets-d-eveil' => 'Jouets d’éveil',
                        'jeux-educatifs' => 'Jeux éducatifs',
                        'puzzles' => 'Puzzles',
                        'jeux-de-societe' => 'Jeux de société',
                        'jeux-exterieurs-plage-jardin' => 'Jeux extérieurs / plage / jardin',
                    ],
                ],
            ],
        ],
        'maison' => [
            'label' => 'Maison',
            'emoji' => '🏠',
            'enfants' => [
                'meubles' => [
                    'label' => 'Meubles',
                    'enfants' => [
                        'canapes-fauteuils' => 'Canapés / fauteuils',
                        'tables-chaises' => 'Tables / chaises',
                        'rangements-etageres' => 'Rangements / étagères',
                        'lits-matelas' => 'Lits / matelas',
                        'meubles-exterieur' => 'Meubles d\'extérieur',
                    ],
                ],
                'decoration' => [
                    'label' => 'Décoration',
                    'enfants' => [
                        'luminaires' => 'Luminaires',
                        'cadres-tableaux' => 'Cadres / tableaux',
                        'miroirs' => 'Miroirs',
                        'tapis' => 'Tapis',
                        'artisanat-local' => 'Artisanat local',
                        'plantes-artificielles' => 'Plantes artificielles',
                    ],
                ],
                'cuisine-arts-de-la-table' => [
                    'label' => 'Cuisine / arts de la table',
                    'enfants' => [
                        'vaisselle' => 'Vaisselle',
                        'ustensiles' => 'Ustensiles',
                        'petit-electromenager' => 'Petit électroménager',
                        'rangement-cuisine' => 'Rangement cuisine',
                    ],
                ],
                'electromenager' => [
                    'label' => 'Électroménager',
                    'enfants' => [
                        'refrigerateurs-congelateurs' => 'Réfrigérateurs / congélateurs',
                        'lave-linge-seche-linge' => 'Lave-linge / sèche-linge',
                        'climatiseurs-ventilateurs' => 'Climatiseurs / ventilateurs',
                        'fours-micro-ondes' => 'Fours / micro-ondes',
                    ],
                ],
                'linge-de-maison' => [
                    'label' => 'Linge de maison',
                    'enfants' => [
                        'draps-parures' => 'Draps / parures',
                        'serviettes' => 'Serviettes',
                        'rideaux' => 'Rideaux',
                        'moustiquaires' => 'Moustiquaires',
                    ],
                ],
            ],
        ],
        'high-tech' => [
            'label' => 'High-tech',
            'emoji' => '📱',
            'enfants' => [
                'telephonie' => [
                    'label' => 'Téléphonie',
                    'enfants' => [
                        'smartphones' => 'Smartphones',
                        'coques-protections' => 'Coques / protections',
                        'chargeurs-cables' => 'Chargeurs / câbles',
                        'montres-connectees' => 'Montres connectées',
                    ],
                ],
                'informatique' => [
                    'label' => 'Informatique',
                    'enfants' => [
                        'ordinateurs-portables' => 'Ordinateurs portables',
                        'tablettes' => 'Tablettes',
                        'ecrans' => 'Écrans',
                        'claviers-souris' => 'Claviers / souris',
                        'disques-stockage' => 'Disques / stockage',
                    ],
                ],
                'image-et-son' => [
                    'label' => 'Image et son',
                    'enfants' => [
                        'televiseurs' => 'Téléviseurs',
                        'enceintes' => 'Enceintes',
                        'casques-ecouteurs' => 'Casques / écouteurs',
                        'appareils-photo' => 'Appareils photo',
                        'drones' => 'Drones',
                    ],
                ],
                'jeux-video' => [
                    'label' => 'Jeux vidéo',
                    'enfants' => [
                        'consoles' => 'Consoles',
                        'jeux' => 'Jeux',
                        'manettes-accessoires' => 'Manettes / accessoires',
                    ],
                ],
            ],
        ],
        'sport-loisirs' => [
            'label' => 'Sport & loisirs',
            'emoji' => '🏄',
            'enfants' => [
                'plage-et-mer' => [
                    'label' => 'Plage et mer',
                    'enfants' => [
                        'surf-bodyboard' => 'Surf / bodyboard',
                        'paddle-kayak' => 'Paddle / kayak',
                        'palmes-masques-tubas' => 'Palmes / masques / tubas',
                        'combinaisons' => 'Combinaisons',
                    ],
                ],
                'fitness-musculation' => [
                    'label' => 'Fitness / musculation',
                    'enfants' => [
                        'halteres-poids' => 'Haltères / poids',
                        'tapis-machines' => 'Tapis / machines',
                        'accessoires-fitness' => 'Accessoires fitness',
                    ],
                ],
                'velos-trottinettes' => [
                    'label' => 'Vélos / trottinettes',
                    'enfants' => [
                        'velos' => 'Vélos',
                        'velos-electriques' => 'Vélos électriques',
                        'trottinettes' => 'Trottinettes',
                        'casques-protections' => 'Casques / protections',
                        'pieces-velo' => 'Pièces vélo',
                    ],
                ],
                'randonnee-camping' => [
                    'label' => 'Randonnée / camping',
                    'enfants' => [
                        'sacs-de-randonnee' => 'Sacs de randonnée',
                        'tentes' => 'Tentes',
                        'chaussures-de-randonnee' => 'Chaussures de randonnée',
                    ],
                ],
                'peche-chasse' => [
                    'label' => 'Pêche / chasse',
                    'enfants' => [
                        'cannes-moulinets' => 'Cannes / moulinets',
                        'leurres-accessoires' => 'Leurres / accessoires',
                    ],
                ],
                'sports-collectifs' => [
                    'label' => 'Sports collectifs',
                    'enfants' => [
                        'football' => 'Football',
                        'basket' => 'Basket',
                        'rugby' => 'Rugby',
                        'autres-sports' => 'Autres sports',
                    ],
                ],
            ],
        ],
        'beaute-sante' => [
            'label' => 'Beauté & santé',
            'emoji' => '💄',
            'enfants' => [
                'parfums' => [
                    'label' => 'Parfums',
                    'enfants' => [
                        'parfums-femme' => 'Parfums femme',
                        'parfums-homme' => 'Parfums homme',
                    ],
                ],
                'maquillage' => [
                    'label' => 'Maquillage',
                    'enfants' => [
                        'teint' => 'Teint',
                        'yeux' => 'Yeux',
                        'levres' => 'Lèvres',
                        'ongles' => 'Ongles',
                    ],
                ],
                'soins' => [
                    'label' => 'Soins',
                    'enfants' => [
                        'visage' => 'Visage',
                        'corps' => 'Corps',
                        'solaires' => 'Solaires',
                        'cheveux' => 'Cheveux',
                    ],
                ],
                'appareils-beaute' => [
                    'label' => 'Appareils de beauté',
                    'enfants' => [
                        'seche-cheveux-lisseurs' => 'Sèche-cheveux / lisseurs',
                        'tondeuses-rasoirs' => 'Tondeuses / rasoirs',
                    ],
                ],
            ],
        ],
        'culture-loisirs' => [
            'label' => 'Culture',
            'emoji' => '📚',
            'enfants' => [
                'livres' => [
                    'label' => 'Livres',
                    'enfants' => [
                        'romans' => 'Romans',
                        'bandes-dessinees-mangas' => 'Bandes dessinées / mangas',
                        'scolaire-etudes' => 'Scolaire / études',
                        'cuisine-loisirs-creatifs' => 'Cuisine / loisirs créatifs',
                        'jeunesse' => 'Jeunesse',
                    ],
                ],
                'films-series-musique' => [
                    'label' => 'Films / séries / musique',
                    'enfants' => [
                        'dvd-blu-ray' => 'DVD / Blu-ray',
                        'vinyles-cd' => 'Vinyles / CD',
                    ],
                ],
                'instruments-de-musique' => [
                    'label' => 'Instruments de musique',
                    'enfants' => [
                        'guitares' => 'Guitares',
                        'claviers-pianos' => 'Claviers / pianos',
                        'percussions' => 'Percussions',
                        'accessoires-musique' => 'Accessoires musique',
                    ],
                ],
                'jeux-de-societe-puzzles' => [
                    'label' => 'Jeux de société / puzzles',
                    'enfants' => [
                        'jeux-de-societe-adultes' => 'Jeux de société',
                        'puzzles-adultes' => 'Puzzles',
                        'cartes-collection' => 'Cartes de collection',
                    ],
                ],
            ],
        ],
        'auto-moto' => [
            'label' => 'Auto & moto',
            'emoji' => '🛵',
            'enfants' => [
                'deux-roues' => [
                    'label' => 'Deux-roues',
                    'enfants' => [
                        'scooters' => 'Scooters',
                        'motos' => 'Motos',
                        'casques' => 'Casques',
                        'equipement-pilote' => 'Équipement pilote',
                    ],
                ],
                'pieces-accessoires-auto' => [
                    'label' => 'Pièces / accessoires auto',
                    'enfants' => [
                        'pneus-jantes' => 'Pneus / jantes',
                        'pieces-moteur' => 'Pièces moteur',
                        'autoradios-gps' => 'Autoradios / GPS',
                        'accessoires-interieur' => 'Accessoires intérieur',
                    ],
                ],
                'entretien-vehicule' => [
                    'label' => 'Entretien véhicule',
                    'enfants' => [
                        'outillage-auto' => 'Outillage auto',
                        'produits-entretien' => 'Produits d\'entretien',
                    ],
                ],
            ],
        ],
        'jardin-bricolage' => [
            'label' => 'Jardin & bricolage',
            'emoji' => '🪴',
            'enfants' => [
                'jardin' => [
                    'label' => 'Jardin',
                    'enfants' => [
                        'plantes-boutures' => 'Plantes / boutures',
                        'pots-jardinieres' => 'Pots / jardinières',
                        'outils-de-jardin' => 'Outils de jardin',
                        'barbecues-planchas' => 'Barbecues / planchas',
                        'piscines-spas' => 'Piscines / spas',
                    ],
                ],
                'bricolage' => [
                    'label' => 'Bricolage',
                    'enfants' => [
                        'outillage-electroportatif' => 'Outillage électroportatif',
                        'outillage-a-main' => 'Outillage à main',
                        'quincaillerie' => 'Quincaillerie',
                        'peinture-materiaux' => 'Peinture / matériaux',
                        'echelles-escabeaux' => 'Échelles / escabeaux',
                    ],
                ],
            ],
        ],
        'animaux' => [
            'label' => 'Animaux',
            'emoji' => '🐾',
            'enfants' => [
                'chiens-chats' => [
                    'label' => 'Chiens / chats',
                    'enfants' => [
                        'niches-couchages' => 'Niches / couchages',
                        'laisses-colliers' => 'Laisses / colliers',
                        'gamelles' => 'Gamelles',
                        'transport-animaux' => 'Transport animaux',
                    ],
                ],
                'autres-animaux' => [
                    'label' => 'Autres animaux',
                    'enfants' => [
                        'aquariophilie' => 'Aquariophilie',
                        'cages-volieres' => 'Cages / volières',
                        'basse-cour' => 'Basse-cour',
                    ],
                ],
            ],
        ],
    ];

    /**
     * Niveau 1 seul, avec son emoji : de quoi construire une rangee de
     * pastilles ou une entree de menu sans parcourir tout l'arbre.
     *
     * @return array<string, array{label: string, emoji: string}>
     */
    public static function niveau1(): array
    {
        $sortie = [];

        foreach (self::ARBRE as $cle => $categorie) {
            $sortie[$cle] = ['label' => $categorie['label'], 'emoji' => $categorie['emoji']];
        }

        return $sortie;
    }

    /**
     * Sous-categories d'une categorie, vide si la categorie est inconnue.
     *
     * @return array<string, string> cle => libelle
     */
    public static function sousCategories(?string $niveau1): array
    {
        $categorie = self::ARBRE[self::normaliser($niveau1)] ?? null;

        if (! $categorie) {
            return [];
        }

        return array_map(fn (array $n2) => $n2['label'], $categorie['enfants']);
    }

    /**
     * Types d'article d'une sous-categorie.
     *
     * @return array<string, string> cle => libelle
     */
    public static function typesArticle(?string $niveau1, ?string $niveau2): array
    {
        $categorie = self::ARBRE[self::normaliser($niveau1)] ?? null;

        if (! $categorie) {
            return [];
        }

        return $categorie['enfants'][self::normaliser($niveau2)]['enfants'] ?? [];
    }

    /**
     * Libelle lisible d'une cle, a n'importe quel niveau.
     *
     * Les annonces anciennes portent parfois « Femme » avec une majuscule :
     * on compare toujours sur la forme normalisee.
     */
    public static function label(?string $cle): ?string
    {
        $recherche = self::normaliser($cle);

        if ($recherche === null) {
            return null;
        }

        foreach (self::ARBRE as $c1 => $n1) {
            if ($c1 === $recherche) {
                return $n1['label'];
            }

            foreach ($n1['enfants'] as $c2 => $n2) {
                if ($c2 === $recherche) {
                    return $n2['label'];
                }

                foreach ($n2['enfants'] as $c3 => $label3) {
                    if ($c3 === $recherche) {
                        return $label3;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Forme attendue par le JavaScript du formulaire de depot.
     *
     * @return array<string, array<string, array{label: string, children: array<string, string>}>>
     */
    public static function pourJavascript(): array
    {
        $sortie = [];

        foreach (self::ARBRE as $cle1 => $n1) {
            $sortie[$cle1] = [];

            foreach ($n1['enfants'] as $cle2 => $n2) {
                $sortie[$cle1][$cle2] = [
                    'label' => $n2['label'],
                    'children' => $n2['enfants'],
                ];
            }
        }

        return $sortie;
    }

    /**
     * FICHE DE CHAMPS : ce que le formulaire de dépôt demande, selon le rayon.
     *
     * Le formulaire était pensé pour les vêtements : « Taille », « Neuf avec
     * étiquette », « Location vêtement »… Absurde pour un frigo ou un livre.
     * La colonne « taille » sert maintenant à la caractéristique qui compte
     * dans chaque rayon (pointure, dimensions, capacité, contenance) et
     * disparaît quand rien ne s'y prête. Les valeurs stockées ne changent pas :
     * seuls les libellés s'adaptent, les filtres de recherche restent justes.
     *
     * @return array{
     *     taille: ?array{label: string, exemple: string},
     *     marque: ?array{label: string, exemple: string},
     *     etat: string,
     *     location: bool,
     *     titre: string
     * }
     */
    public static function fiche(?string $niveau1, ?string $niveau2 = null): array
    {
        $n1 = self::normaliser($niveau1);
        $n2 = self::normaliser($niveau2);

        $taille = fn (string $label, string $exemple) => ['label' => $label, 'exemple' => $exemple];
        $marque = fn (string $exemple, string $label = 'Marque') => ['label' => $label, 'exemple' => $exemple];

        // Habillement : ce que le formulaire faisait déjà.
        $vetement = [
            'taille' => $taille('Taille', 'Ex : M, 38, 40'),
            'marque' => $marque('Ex : Zara, Nike'),
            'etat' => 'textile',
            'location' => true,
            'titre' => 'Ex : Robe Zara noire taille M',
        ];

        $objet = fn (?array $t, ?array $m, string $titre) => [
            'taille' => $t,
            'marque' => $m,
            'etat' => 'objet',
            'location' => false,
            'titre' => $titre,
        ];

        switch ($n1) {
            case 'femme':
            case 'homme':
                if (in_array($n2, ['chaussures', 'chaussures-homme'], true)) {
                    return array_merge($vetement, ['taille' => $taille('Pointure', 'Ex : 38, 42'), 'marque' => $marque('Ex : Nike, Adidas'), 'titre' => 'Ex : Baskets Nike Air blanches 40']);
                }
                if ($n2 === 'accessoires') {
                    return array_merge($vetement, ['taille' => null, 'marque' => $marque('Ex : Michael Kors, Casio'), 'titre' => 'Ex : Sac à main en cuir noir']);
                }

                return $vetement;

            case 'enfant':
                if ($n2 === 'chaussures-enfants') {
                    return array_merge($vetement, ['taille' => $taille('Pointure', 'Ex : 24, 30'), 'marque' => $marque('Ex : Geox, Nike'), 'titre' => 'Ex : Sandales enfant pointure 26']);
                }
                if ($n2 === 'puericulture') {
                    return $objet(null, $marque('Ex : Chicco, Bébé Confort'), 'Ex : Poussette canne pliable');
                }
                if ($n2 === 'jeux-enfant') {
                    return $objet($taille('Âge conseillé', 'Ex : dès 3 ans'), $marque('Ex : Lego, Vtech'), 'Ex : Lego Duplo ferme complète');
                }

                return array_merge($vetement, ['taille' => $taille('Taille / âge', 'Ex : 6 ans, 12 mois'), 'marque' => $marque('Ex : Okaïdi, Kiabi'), 'titre' => 'Ex : Lot de bodies 6 mois']);

            case 'maison':
                return match ($n2) {
                    'electromenager' => $objet($taille('Dimensions / capacité', 'Ex : 60 x 180 cm, 300 L, 7 kg'), $marque('Ex : Whirlpool, Samsung'), 'Ex : Réfrigérateur combiné 300 L'),
                    'cuisine-arts-de-la-table' => $objet(null, $marque('Ex : Seb, Moulinex (facultatif)'), 'Ex : Robot pâtissier avec accessoires'),
                    default => $objet($taille('Dimensions', 'Ex : 120 x 60 cm'), $marque('Ex : Ikea, Maisons du Monde (facultatif)'), 'Ex : Table basse en bois 100 x 50 cm'),
                };

            case 'high-tech':
                return match ($n2) {
                    'telephonie' => $objet($taille('Capacité', 'Ex : 128 Go'), $marque('Ex : Apple, Samsung'), 'Ex : iPhone 13 128 Go bleu'),
                    'informatique' => $objet($taille('Écran / capacité', 'Ex : 13 pouces, 512 Go'), $marque('Ex : Apple, HP, Logitech'), 'Ex : Clavier Bluetooth pour iPad'),
                    'image-et-son' => $objet($taille('Taille d\'écran', 'Ex : 55 pouces (si écran)'), $marque('Ex : Sony, JBL'), 'Ex : Enceinte JBL Flip 6'),
                    default => $objet(null, $marque('Ex : Sony, Nintendo'), 'Ex : Nintendo Switch avec 2 jeux'),
                };

            case 'sport-loisirs':
                return match ($n2) {
                    'plage-et-mer' => $objet($taille('Taille / longueur', 'Ex : M, 6\'2'), $marque('Ex : Quiksilver, Decathlon'), 'Ex : Planche de surf 6\'2'),
                    'velos-trottinettes' => $objet($taille('Taille / roues', 'Ex : M, 26 pouces'), $marque('Ex : Btwin, Xiaomi'), 'Ex : VTT 27,5 pouces taille M'),
                    'randonnee-camping' => $objet($taille('Taille / pointure', 'Ex : 42, 40 L'), $marque('Ex : Quechua, The North Face'), 'Ex : Sac de randonnée 40 L'),
                    'sports-collectifs' => $objet($taille('Taille', 'Ex : M, ballon taille 5'), $marque('Ex : Adidas, Kipsta'), 'Ex : Ballon de basket taille 7'),
                    default => $objet(null, $marque('Ex : Decathlon, Rapala'), 'Ex : Haltères 2 x 10 kg'),
                };

            case 'beaute-sante':
                return match ($n2) {
                    'parfums', 'soins' => $objet($taille('Contenance', 'Ex : 50 ml'), $marque('Ex : Dior, Nina Ricci'), 'Ex : Eau de parfum 50 ml'),
                    'maquillage' => $objet($taille('Teinte', 'Ex : Beige clair, 02'), $marque('Ex : Maybelline, MAC'), 'Ex : Palette de fards à paupières'),
                    default => $objet(null, $marque('Ex : Philips, Babyliss'), 'Ex : Lisseur Babyliss'),
                };

            case 'culture-loisirs':
                return match ($n2) {
                    'livres' => $objet(null, $marque('Ex : Victor Hugo, Eiichirō Oda', 'Auteur'), 'Ex : Lot de mangas One Piece tomes 1 à 10'),
                    'films-series-musique' => $objet(null, $marque('Ex : Kassav\', Disney', 'Artiste / studio'), 'Ex : Coffret DVD Harry Potter'),
                    'instruments-de-musique' => $objet(null, $marque('Ex : Yamaha, Fender'), 'Ex : Guitare classique Yamaha'),
                    default => $objet($taille('Âge conseillé', 'Ex : dès 8 ans'), $marque('Ex : Asmodee, Ravensburger'), 'Ex : Puzzle 1000 pièces complet'),
                };

            case 'auto-moto':
                return match ($n2) {
                    'deux-roues' => $objet($taille('Taille / cylindrée', 'Ex : M, 125 cm³'), $marque('Ex : Yamaha, Peugeot'), 'Ex : Casque intégral taille M'),
                    'pieces-accessoires-auto' => $objet($taille('Dimensions / référence', 'Ex : 195/65 R15'), $marque('Ex : Michelin, Bosch'), 'Ex : 4 pneus 195/65 R15'),
                    default => $objet(null, $marque('Ex : Bosch, Facom'), 'Ex : Cric hydraulique 2 tonnes'),
                };

            case 'jardin-bricolage':
                return match ($n2) {
                    'jardin' => $objet($taille('Dimensions', 'Ex : 40 cm de haut, pot de 20 cm'), $marque('Ex : Gardena, Weber (facultatif)'), 'Ex : Boutures de bougainvillier'),
                    default => $objet(null, $marque('Ex : Bosch, Makita'), 'Ex : Perceuse visseuse sans fil 18 V'),
                };

            case 'animaux':
                return match ($n2) {
                    'chiens-chats' => $objet($taille('Taille', 'Ex : S, M, L'), $marque('Ex : Trixie (facultatif)'), 'Ex : Panier pour chien taille M'),
                    default => $objet($taille('Dimensions / volume', 'Ex : 60 L, 80 x 40 cm'), $marque('Ex : Tetra (facultatif)'), 'Ex : Aquarium 60 L complet'),
                };
        }

        // Catégorie pas encore choisie (ou ancienne valeur) : formulaire
        // d'origine, le plus complet.
        return $vetement;
    }

    /**
     * Toutes les fiches, pour que le formulaire s'adapte au choix du rayon
     * sans recharger la page. Clé « niveau1 » (fiche par défaut du rayon) et
     * « niveau1/niveau2 ».
     *
     * @return array<string, array>
     */
    public static function fichesPourJavascript(): array
    {
        $fiches = ['' => self::fiche(null)];

        foreach (self::ARBRE as $cle1 => $n1) {
            $fiches[$cle1] = self::fiche($cle1);

            foreach (array_keys($n1['enfants']) as $cle2) {
                $fiches[$cle1 . '/' . $cle2] = self::fiche($cle1, $cle2);
            }
        }

        return $fiches;
    }

    private static function normaliser(?string $cle): ?string
    {
        return filled($cle) ? mb_strtolower(trim($cle)) : null;
    }
}
