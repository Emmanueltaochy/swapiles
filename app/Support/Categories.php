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

    private static function normaliser(?string $cle): ?string
    {
        return filled($cle) ? mb_strtolower(trim($cle)) : null;
    }
}
