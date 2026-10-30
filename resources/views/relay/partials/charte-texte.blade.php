@php
    $fee = number_format((float) config('pricing.relay_merchant_fee', 1), 0, ',', ' ');
    $garde = (int) config('relais.delai_garde_jours', 10);
    $poids = rtrim(rtrim(number_format((float) config('relais.poids_max_kg', 15), 1, ',', ''), '0'), ',');
    $longueur = (int) config('relais.longueur_max_cm', 100);
    $minimum = number_format((float) config('relais.versement_minimum', 10), 0, ',', ' ');
    $preavis = (int) config('relais.preavis_jours', 15);
    $plafond = number_format((float) config('relais.plafond_responsabilite', 100), 0, ',', ' ');
    $justif = (int) config('relais.delai_justificatif_jours', 7);
    $editeur = config('partenaires.editeur');
@endphp
<div class="space-y-5 text-sm text-gray-700 leading-relaxed">
    <div>
        <h3 class="font-extrabold text-gray-900">1. Le rôle du point relais</h3>
        <p>Le commerçant reçoit les colis déposés par les vendeurs Swap’Îles et les remet aux acheteurs, pendant ses horaires d’ouverture. Il n’encaisse jamais d’argent : l’acheteur a déjà payé en ligne.</p>
    </div>
    <div>
        <h3 class="font-extrabold text-gray-900">2. C’est gratuit et sans engagement</h3>
        <p>Aucun matériel, aucun abonnement, aucune commission prélevée. Le commerçant peut arrêter à tout moment, sans frais, avec un préavis de {{ $preavis }} jours pour remettre les colis encore en stock. Swap’Îles peut aussi mettre fin au partenariat avec le même préavis, ou sans préavis en cas de manquement grave à la charte.</p>
    </div>
    <div>
        <h3 class="font-extrabold text-gray-900">3. Les colis acceptés</h3>
        <p>Colis fermés et emballés, de {{ $poids }} kg maximum et {{ $longueur }} cm de long maximum. Le commerçant peut refuser un colis abîmé, ouvert ou trop grand : il le signale dans son espace relais ou à contact@swapiles.com.</p>
    </div>
    <div>
        <h3 class="font-extrabold text-gray-900">4. La remise contre code</h3>
        <p>Un colis n’est remis qu’à la personne qui présente le <strong>code de retrait</strong> de la vente, et la remise est confirmée aussitôt dans l’espace relais. Après le délai de garde, le colis est rendu au vendeur, qui se présente avec son compte Swap’Îles.</p>
    </div>
    <div>
        <h3 class="font-extrabold text-gray-900">5. Le délai de garde : {{ $garde }} jours</h3>
        <p>L’acheteur a {{ $garde }} jours après le dépôt pour retirer son colis. Il reçoit des rappels. Passé ce délai, le vendeur est prévenu et vient récupérer son colis. Swap’Îles s’occupe de la suite de la vente avec l’acheteur.</p>
    </div>
    <div>
        <h3 class="font-extrabold text-gray-900">6. La rémunération : {{ $fee }} € par colis</h3>
        <p>{{ $fee }} € TTC par colis remis (à l’acheteur, ou au vendeur après le délai de garde). Le solde est visible à tout moment dans l’espace relais. Un relevé est établi chaque mois, et le solde est versé par virement au début du mois suivant, dès {{ $minimum }} € de solde (sinon il est reporté au mois suivant).</p>
    </div>
    <div>
        <h3 class="font-extrabold text-gray-900">7. Le mandat d’autofacturation</h3>
        <p>En acceptant la charte, le commerçant donne mandat à {{ $editeur['nom'] }} pour établir en son nom et pour son compte les factures de ses commissions, chaque mois. Il les retrouve dans son espace relais et peut les contester sous 30 jours à contact@swapiles.com. Il reste responsable de ses déclarations (chiffre d’affaires, TVA s’il y est assujetti). Sans SIRET renseigné, Swap’Îles établit un simple relevé.</p>
    </div>
    <div>
        <h3 class="font-extrabold text-gray-900">8. Les responsabilités</h3>
        <p>Le commerçant a une obligation de garde : un colis est sous sa responsabilité de son dépôt jusqu’à sa remise. Il le garde avec le même soin que les articles de ses clients, dans un endroit non accessible au public.</p>
        <p class="mt-2">En cas de problème, l’acheteur est <strong>toujours remboursé tout de suite par la protection acheteur Swap’Îles</strong>. Ensuite :</p>
        <ul class="mt-1 list-disc pl-5 space-y-1">
            <li><strong>Faute du commerçant</strong> (colis égaré dans le commerce, remis sans code, ou remis à une autre personne que l’acheteur ou le vendeur) : seul <strong>le prix réel de l’article</strong> peut lui être demandé, jamais plus (un article vendu 10 € = 10 € au plus), et dans tous les cas <strong>dans la limite de {{ $plafond }} € par colis</strong>. Ce montant est <strong>uniquement retenu sur ses commissions</strong> : le commerçant n’avance jamais d’argent de sa poche. Swap’Îles l’informe avant toute retenue, avec le détail.</li>
            <li><strong>Pas de faute</strong> : rien ne lui est demandé, <strong>sur justificatif envoyé à contact@swapiles.com sous {{ $justif }} jours</strong> :
                <ul class="mt-1 list-[circle] pl-5 space-y-1">
                    <li>vol avec effraction ou vol à main armée : copie du <strong>récépissé de dépôt de plainte</strong> (police ou gendarmerie) ;</li>
                    <li>incendie, dégât des eaux ou autre sinistre : copie de la <strong>déclaration de sinistre</strong> à son assurance ;</li>
                    <li>colis déjà abîmé ou ouvert au dépôt : <strong>signalé au moment du dépôt</strong>, avec une photo (ou refusé).</li>
                </ul>
            </li>
        </ul>
        <p class="mt-2">Sans justificatif dans ce délai, la perte est considérée comme une faute du commerçant.</p>
        <p class="mt-2">Le contenu et la conformité des articles relèvent du vendeur.</p>
    </div>
    <div>
        <h3 class="font-extrabold text-gray-900">9. Les données personnelles</h3>
        <p>Le commerçant voit le nom des vendeurs et des acheteurs uniquement pour remettre les colis. Il s’engage à ne pas les utiliser à d’autres fins ni à les communiquer. Les rapports d’impact qu’il reçoit ne contiennent que des totaux anonymes.</p>
    </div>
    <div>
        <h3 class="font-extrabold text-gray-900">10. Les changements de la charte</h3>
        <p>Si Swap’Îles change une règle, le commerçant est prévenu et accepte la nouvelle version dans son espace relais avant de continuer. Version en vigueur : {{ config('relais.charte_version') }}.</p>
    </div>
</div>
