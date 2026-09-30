<?php

namespace App\Support;

use App\Models\Listing;

/**
 * Range une annonce dans l'arbre des catégories à partir de son texte.
 *
 * L'arbre ne couvrait que l'habillement (femme / homme / enfant) alors que la
 * plateforme vend aussi du mobilier, du high-tech, du sport, de la puériculture
 * et du bricolage. Ces annonces-là portaient donc une catégorie fausse ou vide,
 * et restaient introuvables par la navigation.
 *
 * Le classement est volontairement prudent : une annonce qu'aucune règle ne
 * reconnaît n'est PAS déplacée. Mieux vaut la laisser où elle est que la ranger
 * au mauvais endroit — un article mal classé est plus difficile à retrouver
 * qu'un article non classé, que la recherche texte trouve encore.
 */
class CategoryClassifier
{
    /**
     * Règles, de la plus précise à la plus générale.
     *
     * Chaque règle : [motif, niveau1, niveau2, niveau3].
     * La première qui correspond gagne, d'où l'ordre : « chaise haute » doit
     * passer avant « chaise », et « maillot de bain » avant « maillot ».
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function regles(): array
    {
        return [
            // --- Puériculture et enfant : avant tout le reste, car « lit bébé »
            //     ne doit pas tomber dans « lits / matelas » de la maison.
            ['poussette|landau|combine trio', 'enfant', 'puericulture', 'poussettes'],
            ['siege auto|siege-auto|cosy bebe|nacelle', 'enfant', 'puericulture', 'sieges-auto'],
            ['lit bebe|lit parapluie|berceau|couffin', 'enfant', 'puericulture', 'lits-bebe'],
            ['porte bebe|porte-bebe|echarpe de portage', 'enfant', 'puericulture', 'porte-bebes-echarpes'],
            ['chaise haute', 'enfant', 'puericulture', 'chaises-hautes'],
            ['biberon|tire lait|tire-lait|chauffe biberon|sterilisateur', 'enfant', 'puericulture', 'biberons'],
            ['jouet d eveil|jouet eveil|tapis d eveil|hochet|portique', 'enfant', 'jeux-enfant', 'jouets-d-eveil'],
            ['jeu educatif|jeux educatifs|montessori', 'enfant', 'jeux-enfant', 'jeux-educatifs'],
            ['toboggan|balancoire|piscine gonflable|trampoline|jeu de plage', 'enfant', 'jeux-enfant', 'jeux-exterieurs-plage-jardin'],
            ['chausson bebe|chaussons bebe', 'enfant', 'chaussures-enfants', 'chaussons'],
            ['body bebe|bodies bebe|barboteuse', 'enfant', 'vetements-enfants', 'bodies'],
            ['pyjama bebe|grenouillere|gigoteuse|turbulette', 'enfant', 'vetements-enfants', 'pyjamas'],

            // --- High-tech
            ['iphone|samsung galaxy|smartphone|telephone portable|xiaomi|huawei|pixel ', 'high-tech', 'telephonie', 'smartphones'],
            ['coque telephone|coque iphone|coque samsung|verre trempe|protection ecran', 'high-tech', 'telephonie', 'coques-protections'],
            ['chargeur|cable usb|cable lightning|powerbank|batterie externe', 'high-tech', 'telephonie', 'chargeurs-cables'],
            ['montre connectee|apple watch|smartwatch|bracelet connecte', 'high-tech', 'telephonie', 'montres-connectees'],
            ['macbook|ordinateur portable|pc portable|laptop|chromebook', 'high-tech', 'informatique', 'ordinateurs-portables'],
            ['ipad|tablette tactile|galaxy tab', 'high-tech', 'informatique', 'tablettes'],
            ['ecran pc|moniteur|ecran d ordinateur', 'high-tech', 'informatique', 'ecrans'],
            ['clavier|souris sans fil|souris gamer', 'high-tech', 'informatique', 'claviers-souris'],
            ['disque dur|ssd|cle usb|carte sd|carte memoire', 'high-tech', 'informatique', 'disques-stockage'],
            ['televiseur|television|tv led|tv oled|smart tv|ecran tv', 'high-tech', 'image-et-son', 'televiseurs'],
            ['enceinte|barre de son|jbl|bluetooth speaker|sono', 'high-tech', 'image-et-son', 'enceintes'],
            ['casque audio|ecouteurs|airpods|casque bluetooth', 'high-tech', 'image-et-son', 'casques-ecouteurs'],
            ['appareil photo|reflex|canon eos|nikon|gopro|camera', 'high-tech', 'image-et-son', 'appareils-photo'],
            ['drone', 'high-tech', 'image-et-son', 'drones'],
            ['playstation|ps4|ps5|xbox|nintendo switch|console de jeu', 'high-tech', 'jeux-video', 'consoles'],
            ['manette|joystick|dualshock|joycon', 'high-tech', 'jeux-video', 'manettes-accessoires'],
            ['jeu video|jeux video|jeu ps4|jeu ps5|jeu switch|jeu xbox', 'high-tech', 'jeux-video', 'jeux'],

            // --- Électroménager et maison
            ['refrigerateur|frigo|congelateur|combine frigo', 'maison', 'electromenager', 'refrigerateurs-congelateurs'],
            ['lave linge|lave-linge|machine a laver|seche linge|seche-linge', 'maison', 'electromenager', 'lave-linge-seche-linge'],
            ['climatiseur|climatisation|ventilateur|brasseur d air', 'maison', 'electromenager', 'climatiseurs-ventilateurs'],
            ['four electrique|micro onde|micro-onde|four encastrable', 'maison', 'electromenager', 'fours-micro-ondes'],
            ['robot cuisine|mixeur|blender|cafetiere|bouilloire|friteuse|airfryer|grille pain', 'maison', 'cuisine-arts-de-la-table', 'petit-electromenager'],
            ['vaisselle|assiette|couverts|verres a|service de table', 'maison', 'cuisine-arts-de-la-table', 'vaisselle'],
            ['casserole|poele|ustensile|marmite|cocotte', 'maison', 'cuisine-arts-de-la-table', 'ustensiles'],
            ['canape|fauteuil|banquette', 'maison', 'meubles', 'canapes-fauteuils'],
            ['table basse|table a manger|table de salle|chaise|tabouret|bureau ', 'maison', 'meubles', 'tables-chaises'],
            ['armoire|commode|etagere|bibliotheque|buffet|dressing meuble', 'maison', 'meubles', 'rangements-etageres'],
            ['matelas|sommier|lit double|lit simple|tete de lit', 'maison', 'meubles', 'lits-matelas'],
            ['salon de jardin|transat|bain de soleil|hamac', 'maison', 'meubles', 'meubles-exterieur'],
            ['lampe|luminaire|lustre|applique|guirlande lumineuse', 'maison', 'decoration', 'luminaires'],
            ['cadre|tableau deco|poster encadre|toile deco', 'maison', 'decoration', 'cadres-tableaux'],
            ['miroir', 'maison', 'decoration', 'miroirs'],
            ['tapis', 'maison', 'decoration', 'tapis'],
            ['vannerie|artisanat|calebasse|objet creole', 'maison', 'decoration', 'artisanat-local'],
            ['moustiquaire', 'maison', 'linge-de-maison', 'moustiquaires'],
            ['drap|parure de lit|housse de couette|taie d oreiller', 'maison', 'linge-de-maison', 'draps-parures'],
            ['serviette de bain|drap de bain|serviette de toilette', 'maison', 'linge-de-maison', 'serviettes'],
            ['rideau|voilage', 'maison', 'linge-de-maison', 'rideaux'],

            // --- Sport et loisirs
            ['surf|bodyboard|planche de surf|longboard surf', 'sport-loisirs', 'plage-et-mer', 'surf-bodyboard'],
            ['paddle|kayak|canoe', 'sport-loisirs', 'plage-et-mer', 'paddle-kayak'],
            ['palme|masque de plongee|tuba|snorkeling', 'sport-loisirs', 'plage-et-mer', 'palmes-masques-tubas'],
            ['combinaison de plongee|shorty neoprene|neoprene', 'sport-loisirs', 'plage-et-mer', 'combinaisons'],
            ['haltere|poids de musculation|kettlebell|banc de musculation', 'sport-loisirs', 'fitness-musculation', 'halteres-poids'],
            ['tapis de course|velo elliptique|rameur|tapis de yoga', 'sport-loisirs', 'fitness-musculation', 'tapis-machines'],
            ['velo electrique|vae|velo a assistance', 'sport-loisirs', 'velos-trottinettes', 'velos-electriques'],
            ['trottinette', 'sport-loisirs', 'velos-trottinettes', 'trottinettes'],
            ['velo|vtt|bicyclette', 'sport-loisirs', 'velos-trottinettes', 'velos'],
            ['sac de randonnee|sac a dos de rando', 'sport-loisirs', 'randonnee-camping', 'sacs-de-randonnee'],
            ['tente de camping|tente 2 places|tente 4 places', 'sport-loisirs', 'randonnee-camping', 'tentes'],
            ['chaussure de randonnee|chaussures de rando', 'sport-loisirs', 'randonnee-camping', 'chaussures-de-randonnee'],
            ['canne a peche|moulinet', 'sport-loisirs', 'peche-chasse', 'cannes-moulinets'],
            ['leurre|hamecon|fil de peche', 'sport-loisirs', 'peche-chasse', 'leurres-accessoires'],
            ['ballon de foot|maillot de foot|crampon', 'sport-loisirs', 'sports-collectifs', 'football'],
            ['ballon de basket|maillot nba', 'sport-loisirs', 'sports-collectifs', 'basket'],

            // --- Auto / moto
            ['scooter|cyclomoteur|50cc', 'auto-moto', 'deux-roues', 'scooters'],
            ['moto |motocross|125cc|roadster', 'auto-moto', 'deux-roues', 'motos'],
            ['casque moto|casque integral|casque jet', 'auto-moto', 'deux-roues', 'casques'],
            ['blouson moto|gant moto|bottes moto', 'auto-moto', 'deux-roues', 'equipement-pilote'],
            ['pneu|jante', 'auto-moto', 'pieces-accessoires-auto', 'pneus-jantes'],
            ['autoradio|gps voiture|support telephone voiture', 'auto-moto', 'pieces-accessoires-auto', 'autoradios-gps'],
            ['plaquette de frein|alternateur|demarreur|courroie', 'auto-moto', 'pieces-accessoires-auto', 'pieces-moteur'],

            // --- Jardin / bricolage
            ['plante|bouture|orchidee|palmier|graines', 'jardin-bricolage', 'jardin', 'plantes-boutures'],
            ['pot de fleur|jardiniere', 'jardin-bricolage', 'jardin', 'pots-jardinieres'],
            ['tondeuse a gazon|debroussailleuse|secateur|taille haie|arrosoir', 'jardin-bricolage', 'jardin', 'outils-de-jardin'],
            ['barbecue|plancha', 'jardin-bricolage', 'jardin', 'barbecues-planchas'],
            ['piscine|jacuzzi|spa gonflable', 'jardin-bricolage', 'jardin', 'piscines-spas'],
            ['perceuse|visseuse|meuleuse|scie circulaire|ponceuse|compresseur', 'jardin-bricolage', 'bricolage', 'outillage-electroportatif'],
            ['tournevis|marteau|cle a molette|pince |boite a outils', 'jardin-bricolage', 'bricolage', 'outillage-a-main'],
            ['peinture|enduit|carrelage|parquet|placo', 'jardin-bricolage', 'bricolage', 'peinture-materiaux'],
            ['echelle|escabeau|echafaudage', 'jardin-bricolage', 'bricolage', 'echelles-escabeaux'],

            // --- Culture
            ['bande dessinee|manga|comics|bd ', 'culture-loisirs', 'livres', 'bandes-dessinees-mangas'],
            ['livre scolaire|manuel scolaire|annales|prepa', 'culture-loisirs', 'livres', 'scolaire-etudes'],
            ['livre|roman|bouquin', 'culture-loisirs', 'livres', 'romans'],
            ['dvd|blu ray|blu-ray|coffret serie', 'culture-loisirs', 'films-series-musique', 'dvd-blu-ray'],
            ['vinyle|33 tours|cd album', 'culture-loisirs', 'films-series-musique', 'vinyles-cd'],
            ['guitare|ukulele|basse electrique', 'culture-loisirs', 'instruments-de-musique', 'guitares'],
            ['piano|clavier arrangeur|synthetiseur', 'culture-loisirs', 'instruments-de-musique', 'claviers-pianos'],
            ['batterie musique|djembe|percussion|cajon', 'culture-loisirs', 'instruments-de-musique', 'percussions'],
            ['jeu de societe|jeux de societe', 'culture-loisirs', 'jeux-de-societe-puzzles', 'jeux-de-societe-adultes'],
            ['puzzle', 'culture-loisirs', 'jeux-de-societe-puzzles', 'puzzles-adultes'],
            ['carte pokemon|carte magic|carte a collectionner', 'culture-loisirs', 'jeux-de-societe-puzzles', 'cartes-collection'],

            // --- Beauté / santé
            ['parfum homme|eau de toilette homme', 'beaute-sante', 'parfums', 'parfums-homme'],
            ['parfum|eau de parfum|eau de toilette', 'beaute-sante', 'parfums', 'parfums-femme'],
            ['fond de teint|correcteur|poudre teint', 'beaute-sante', 'maquillage', 'teint'],
            ['mascara|fard a paupiere|palette yeux|eyeliner', 'beaute-sante', 'maquillage', 'yeux'],
            ['rouge a levre|gloss', 'beaute-sante', 'maquillage', 'levres'],
            ['vernis a ongle|faux ongle|manucure', 'beaute-sante', 'maquillage', 'ongles'],
            ['creme solaire|protection solaire|apres soleil', 'beaute-sante', 'soins', 'solaires'],
            ['shampoing|apres shampoing|masque cheveux|huile de coco cheveux', 'beaute-sante', 'soins', 'cheveux'],
            ['creme visage|serum visage|nettoyant visage', 'beaute-sante', 'soins', 'visage'],
            ['seche cheveux|seche-cheveux|lisseur|boucleur|fer a lisser', 'beaute-sante', 'appareils-beaute', 'seche-cheveux-lisseurs'],
            ['tondeuse cheveux|rasoir electrique|epilateur', 'beaute-sante', 'appareils-beaute', 'tondeuses-rasoirs'],

            // --- Animaux
            ['niche chien|panier chien|couchage chat|arbre a chat', 'animaux', 'chiens-chats', 'niches-couchages'],
            ['laisse|collier chien|harnais chien', 'animaux', 'chiens-chats', 'laisses-colliers'],
            ['gamelle|distributeur croquette', 'animaux', 'chiens-chats', 'gamelles'],
            ['caisse de transport|sac de transport animal', 'animaux', 'chiens-chats', 'transport-animaux'],
            ['aquarium|pompe aquarium', 'animaux', 'autres-animaux', 'aquariophilie'],
            ['cage oiseau|voliere|cage rongeur', 'animaux', 'autres-animaux', 'cages-volieres'],
            ['poulailler|mangeoire poule', 'animaux', 'autres-animaux', 'basse-cour'],
        ];
    }

    /**
     * Catégorie proposée pour une annonce, ou null si aucune règle ne
     * reconnaît son texte.
     *
     * @return array{0: string, 1: string, 2: string}|null [niveau1, niveau2, niveau3]
     */
    public static function classer(Listing $listing): ?array
    {
        $texte = self::normaliser($listing->title . ' ' . $listing->description);

        if ($texte === '') {
            return null;
        }

        foreach (self::regles() as [$motif, $n1, $n2, $n3]) {
            if (preg_match('/' . $motif . '/u', $texte)) {
                return [$n1, $n2, $n3];
            }
        }

        return null;
    }

    /**
     * L'annonce porte-t-elle déjà une place valide dans l'arbre ?
     *
     * Une annonce correctement rangée n'est jamais déplacée, même si une règle
     * pourrait lui donner une autre place : le choix du vendeur fait foi.
     */
    public static function dejaRangee(Listing $listing): bool
    {
        $n1 = mb_strtolower((string) $listing->category_level1);
        $n2 = mb_strtolower((string) $listing->category_level2);

        if (! isset(Categories::ARBRE[$n1])) {
            return false;
        }

        return isset(Categories::ARBRE[$n1]['enfants'][$n2]);
    }

    /**
     * Minuscules, sans accents, ponctuation ramenée à des espaces : « Sèche-
     * cheveux » et « seche cheveux » doivent déclencher la même règle.
     */
    private static function normaliser(?string $texte): string
    {
        $texte = mb_strtolower(trim((string) $texte));
        $texte = strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
            '’' => ' ', "'" => ' ',
        ]);

        $texte = preg_replace('/[^a-z0-9]+/u', ' ', $texte);

        return trim(preg_replace('/\s+/', ' ', $texte));
    }
}
