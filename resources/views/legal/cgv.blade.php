@extends('layouts.app')

@section('title', 'Conditions générales de vente — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-10 shadow-sm text-gray-700 leading-relaxed space-y-4">
            <h1 class="text-3xl font-extrabold text-gray-900">Conditions générales de vente (CGV)</h1>
            <p class="text-sm text-gray-400">Dernière mise à jour : 22 juillet 2026</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">1. Ventes entre particuliers</h2>
            <p>
                Les ventes réalisées sur Swap'Îles interviennent entre membres particuliers. Swap'Îles agit comme
                intermédiaire technique et fournit un service de paiement sécurisé, mais n'est pas le vendeur des articles.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">2. Paiement sécurisé</h2>
            <p>
                Pour les achats par carte bancaire, le paiement est encaissé de manière sécurisée via Stripe et
                <strong>conservé jusqu'à la confirmation de réception</strong> par l'acheteur. Le montant est ensuite
                reversé au vendeur, déduction faite des frais de service.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">3. Frais de service</h2>
            <p>Swap'Îles prélève, sur chaque vente réglée en ligne, les frais de service suivants :</p>
            <ul class="list-disc pl-6 space-y-1">
                <li><strong>Commission : 10 %</strong> du prix de vente, avec un <strong>minimum de 1 €</strong> pour les articles vendus à moins de 10 €.</li>
                <li><strong>Frais de protection acheteur : 1 €</strong> par transaction.</li>
            </ul>
            <p>
                Ces frais sont toujours affichés clairement <strong>avant la validation du paiement</strong>. Swap'Îles se
                réserve le droit de faire évoluer ces frais (à la hausse comme à la baisse) à tout moment ; les frais
                applicables sont ceux affichés au moment de la transaction.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">4. Livraison</h2>
            <p>
                La livraison est assurée via Colissimo (La Poste) ou par remise en main propre, selon l'option choisie.
                Le vendeur s'engage à expédier dans les meilleurs délais après le paiement. Les frais de livraison sont
                indiqués avant l'achat.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">5. Protection acheteur & réception</h2>
            <p>
                L'acheteur dispose d'un délai pour confirmer la bonne réception de l'article. En l'absence de
                confirmation, un mécanisme automatique peut clôturer la transaction. En cas de problème (article non
                conforme, non reçu), l'acheteur doit contacter Swap'Îles avant la libération des fonds.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">6. Litiges, retours et remboursements</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li>Grâce au paiement sécurisé, les fonds sont conservés jusqu'à ce que l'acheteur confirme la bonne réception de l'article.</li>
                <li>Si l'article n'est <strong>pas reçu</strong>, ou s'il est <strong>significativement non conforme</strong> à l'annonce (défaut majeur non signalé, contrefaçon, article différent), l'acheteur doit le signaler à Swap'Îles dans un délai de <strong>2 jours</strong> suivant la réception (ou la date de livraison estimée en cas de non-réception), <strong>avant de confirmer la réception</strong>.</li>
                <li>Après examen du litige, Swap'Îles peut procéder au <strong>remboursement de l'acheteur tant que les fonds n'ont pas été versés au vendeur</strong>. Le retour de l'article au vendeur peut être demandé, aux frais de la partie responsable.</li>
                <li>Une fois la réception confirmée par l'acheteur (ou après expiration du délai automatique), la transaction est finalisée et les fonds sont versés au vendeur.</li>
                <li>Les transactions réalisées <strong>hors paiement en ligne</strong> (espèces, échange, remise en main propre) ne bénéficient pas de la protection paiement Swap'Îles.</li>
            </ul>

            <h2 class="text-xl font-bold text-gray-900 pt-4">7. Droit de rétractation</h2>
            <p>
                Les ventes entre particuliers ne relèvent pas du droit de rétractation de 14 jours prévu par le Code de la
                consommation, réservé aux achats conclus auprès de vendeurs <strong>professionnels</strong>. La protection
                acheteur Swap'Îles décrite ci-dessus s'y substitue. Un vendeur agissant à titre professionnel demeure tenu
                de respecter l'ensemble de ses obligations légales, dont le droit de rétractation.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">8. Contact</h2>
            <p><a href="mailto:contact@swapiles.com" class="font-semibold text-teal-700">contact@swapiles.com</a></p>

        </div>
    </div>
</section>
@endsection
