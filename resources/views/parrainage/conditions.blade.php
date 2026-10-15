@extends('layouts.app')

@section('title', 'Conditions du parrainage — Swap\'Îles')

@section('content')
@php
    $recompense = (float) config('referral.reward_amount', 0);
    $jours = (int) config('referral.credit_validity_days', 90);
    $plafond = (int) config('referral.max_rewards_per_month', 10);
@endphp
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-10 shadow-sm text-gray-700 leading-relaxed space-y-4">
            <h1 class="text-3xl font-extrabold text-gray-900">Conditions du parrainage</h1>
            <p class="text-sm text-gray-400">Ces conditions complètent les <a href="{{ route('legal.cgu') }}" class="font-semibold text-teal-700">conditions générales d’utilisation</a>.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">1. Principe</h2>
            <p>Chaque membre dispose d’un code et d’un lien de parrainage personnels. Une personne qui s’inscrit avec ce code devient son <strong>filleul</strong>. Un compte n’a qu’un seul parrain, choisi à l’inscription ou dans les {{ config('referral.delai_saisie_jours') }} jours qui suivent, avant tout achat. Il est interdit de se parrainer soi-même.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">2. Avantage du filleul</h2>
            <p>Les frais de protection acheteur sont <strong>offerts sur son premier achat payé dans l’application</strong>. Si cet achat est annulé ou remboursé, l’avantage reste valable pour l’achat suivant.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">3. Récompense du parrain</h2>
            <p>Le parrain reçoit un crédit{{ $recompense > 0 ? ' de ' . number_format($recompense, 2, ',', ' ') . ' €' : '' }} lorsque le <strong>premier achat payé dans l’application</strong> par son filleul est <strong>terminé</strong> (réception confirmée, retrait au point relais ou validation automatique). Aucune récompense n’est versée pour une simple inscription, pour une vente déclarée en main propre sans paiement dans l’application, ni pour une commande annulée ou remboursée.</p>
            <p>Les récompenses sont limitées à <strong>{{ $plafond }} par parrain et par mois civil</strong>.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">4. Le crédit</h2>
            <ul class="list-disc space-y-1 pl-6">
                <li>Il est <strong>valable {{ $jours }} jours</strong> à compter de son versement ; passé ce délai, il expire. Un rappel est envoyé 7 jours avant.</li>
                <li>Il se déduit d’un achat sur Swap’Îles (article, frais, livraison) ; un minimum de 0,50 € reste à payer par carte.</li>
                <li>Il <strong>n’est pas convertible en argent</strong>, ni remboursable, ni <strong>transférable</strong> à un autre compte.</li>
                <li>Si un achat payé en partie avec du crédit est annulé ou remboursé, le crédit utilisé est rendu au portefeuille.</li>
            </ul>

            <h2 class="text-xl font-bold text-gray-900 pt-4">5. Fraude et abus</h2>
            <p>Avant chaque récompense, Swap’Îles vérifie automatiquement que le parrain et le filleul sont deux personnes distinctes (appareil, moyen de paiement, adresse e-mail, téléphone). Swap’Îles peut <strong>refuser ou annuler une récompense</strong>, retirer le crédit correspondant et suspendre la participation d’un membre en cas de fraude, de faux comptes, d’achats fictifs ou de tout usage abusif du programme.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">6. Évolution du programme</h2>
            <p>Swap’Îles peut <strong>modifier ou arrêter le programme</strong> à tout moment. Les crédits déjà versés restent utilisables jusqu’à leur date d’expiration.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">7. Données personnelles</h2>
            <p>Le traitement des données liées au parrainage est décrit dans la <a href="{{ route('legal.privacy') }}" class="font-semibold text-teal-700">politique de confidentialité</a>.</p>

            <p class="pt-4"><a href="mailto:contact@swapiles.com" class="font-semibold text-teal-700">contact@swapiles.com</a></p>
        </div>
    </div>
</section>
@endsection
