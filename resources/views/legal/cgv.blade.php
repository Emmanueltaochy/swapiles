@extends('layouts.app')

@section('title', 'Conditions générales de vente — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-10 shadow-sm text-gray-700 leading-relaxed space-y-4">
            <h1 class="text-3xl font-extrabold text-gray-900">Conditions générales de vente (CGV)</h1>
            <p class="text-sm text-gray-400">Dernière mise à jour : [À COMPLÉTER : date]</p>

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
            <p>
                Swap'Îles applique des frais de service (commission et/ou frais de protection acheteur) affichés
                clairement avant la validation du paiement. [À COMPLÉTER : préciser le montant / pourcentage exact des frais.]
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

            <h2 class="text-xl font-bold text-gray-900 pt-4">6. Remboursement</h2>
            <p>
                Un remboursement peut être effectué tant que les fonds n'ont pas été versés au vendeur, notamment en cas
                d'article non expédié ou non conforme. [À COMPLÉTER : préciser votre politique de remboursement et délais.]
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">7. Droit de rétractation</h2>
            <p>
                S'agissant de ventes entre particuliers, le droit de rétractation légal applicable aux professionnels ne
                s'applique pas de plein droit. Les litiges sont traités au cas par cas via le service Swap'Îles.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">8. Contact</h2>
            <p><a href="mailto:contact@swapiles.com" class="font-semibold text-teal-700">contact@swapiles.com</a></p>

            <p class="text-xs text-gray-400 pt-6 border-t border-gray-100">
                Ce document est un modèle à adapter. Nous vous recommandons de le faire vérifier par un professionnel du droit.
            </p>
        </div>
    </div>
</section>
@endsection
