@extends('layouts.app')

@section('title', 'Politique de confidentialité — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-10 shadow-sm text-gray-700 leading-relaxed space-y-4">
            <h1 class="text-3xl font-extrabold text-gray-900">Politique de confidentialité</h1>
            <p class="text-sm text-gray-400">Dernière mise à jour : 22 juillet 2026</p>

            <p>
                Cette politique explique quelles données personnelles Swap'Îles collecte, pourquoi, et vos droits
                conformément au Règlement Général sur la Protection des Données (RGPD).
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">1. Responsable du traitement</h2>
            <p>Emmanuel Taochy (EI) — 124 route des canots, 97427 Étang-Salé, La Réunion. Contact : contact@swapiles.com</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">2. Données collectées</h2>
            <ul class="list-disc pl-6 space-y-1">
                <li>Données d'inscription : nom, e-mail, mot de passe (chiffré), territoire.</li>
                <li>Données de profil et d'annonces : photos, descriptions, adresse d'expédition.</li>
                <li>Données de transaction : historique d'achats/ventes, coordonnées de livraison.</li>
                <li>Données de paiement : gérées directement par Stripe — Swap'Îles ne stocke jamais votre numéro de carte ni votre IBAN.</li>
                <li>Données techniques : adresse IP, appareil, pages consultées (mesure d'audience).</li>
            </ul>

            <h2 class="text-xl font-bold text-gray-900 pt-4">3. Finalités</h2>
            <p>
                Fournir le service (mise en relation, paiement, livraison), assurer la sécurité, améliorer la plateforme,
                et communiquer avec vous (e-mails de transaction, informations importantes).
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">4. Partage des données</h2>
            <p>
                Vos données ne sont partagées qu'avec les prestataires nécessaires au service : <strong>Stripe</strong>
                (paiements), <strong>Colissimo / La Poste</strong> (livraison), et l'hébergeur. Aucune revente de données à des tiers.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">5. Durée de conservation</h2>
            <p>Vos données sont conservées le temps nécessaire à la fourniture du service et aux obligations légales (comptables, fiscales).</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">6. Vos droits</h2>
            <p>
                Vous disposez d'un droit d'accès, de rectification, d'effacement, d'opposition et de portabilité de vos
                données. Pour l'exercer : <a href="mailto:contact@swapiles.com" class="font-semibold text-teal-700">contact@swapiles.com</a>.
                Vous pouvez aussi saisir la CNIL.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">7. Cookies & mesure d'audience</h2>
            <p>
                Le site utilise des cookies strictement <strong>nécessaires à son fonctionnement</strong> (session, sécurité)
                qui ne requièrent pas de consentement. Avec votre accord, il utilise également des outils de <strong>mesure
                d'audience et de publicité</strong> — <strong>Meta Pixel</strong> (Meta Platforms) et <strong>Google Tag /
                Analytics</strong> (Google) — afin de mesurer la performance du site et de nos campagnes.
            </p>
            <p>
                Ces outils ne sont <strong>activés qu'après votre consentement</strong> via le bandeau cookies. Vous pouvez
                refuser, ou modifier votre choix en supprimant les cookies du site dans votre navigateur.
            </p>

        </div>
    </div>
</section>
@endsection
