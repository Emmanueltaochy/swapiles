@extends('layouts.app')

@section('title', 'Conditions générales d\'utilisation — Swap\'Îles')

@section('content')
<section class="bg-gray-50 min-h-screen py-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="rounded-2xl border border-gray-100 bg-white p-6 sm:p-10 shadow-sm text-gray-700 leading-relaxed space-y-4">
            <h1 class="text-3xl font-extrabold text-gray-900">Conditions générales d'utilisation (CGU)</h1>
            <p class="text-sm text-gray-400">Dernière mise à jour : 22 juillet 2026</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">1. Objet</h2>
            <p>
                Swap'Îles est une place de marché en ligne qui met en relation des particuliers pour l'achat,
                la vente, l'échange et le don d'articles d'occasion, principalement dans les territoires ultramarins.
                Les présentes CGU encadrent l'utilisation du site.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">2. Inscription</h2>
            <p>
                L'inscription est gratuite et réservée aux personnes majeures (ou mineures avec l'accord de leur
                représentant légal). Vous vous engagez à fournir des informations exactes et à préserver la
                confidentialité de vos identifiants.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">3. Rôle de la plateforme</h2>
            <p>
                Swap'Îles est un <strong>intermédiaire technique</strong>. Les transactions sont conclues directement
                entre membres. Swap'Îles n'est pas partie au contrat de vente et n'est pas propriétaire des articles.
                Le paiement en ligne sécurisé est fourni via notre partenaire Stripe.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">4. Obligations des membres</h2>
            <p>Chaque membre s'engage à ne pas publier d'annonces :</p>
            <ul class="list-disc pl-6 space-y-1">
                <li>portant sur des produits illicites, contrefaits, dangereux ou interdits à la vente ;</li>
                <li>trompeuses, frauduleuses ou portant atteinte aux droits de tiers ;</li>
                <li>à caractère injurieux, discriminatoire ou contraire à l'ordre public.</li>
            </ul>
            <p>Les vendeurs s'engagent à décrire fidèlement leurs articles et à expédier dans les délais convenus.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">5. Contenus</h2>
            <p>
                Vous restez responsable des contenus (photos, descriptions) que vous publiez. Swap'Îles peut retirer
                tout contenu non conforme et suspendre un compte en cas de manquement.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">6. Responsabilité</h2>
            <p>
                Swap'Îles met tout en œuvre pour assurer le bon fonctionnement du service mais ne peut garantir une
                disponibilité permanente. La responsabilité de la qualité, de la conformité et de la livraison des
                articles incombe aux membres vendeurs.
            </p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">7. Résiliation</h2>
            <p>Vous pouvez fermer votre compte à tout moment. Swap'Îles peut suspendre un compte en cas de non-respect des CGU.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">8. Droit applicable</h2>
            <p>Les présentes CGU sont soumises au droit français. En cas de litige, une solution amiable sera recherchée en priorité.</p>

            <h2 class="text-xl font-bold text-gray-900 pt-4">9. Contact</h2>
            <p><a href="mailto:contact@swapiles.com" class="font-semibold text-teal-700">contact@swapiles.com</a></p>

            <p class="pt-4">
                Éditeur : Emmanuel Taochy (EI) — 124 route des canots, 97427 Étang-Salé, La Réunion — SIRET 884 270 604 00025.
            </p>
        </div>
    </div>
</section>
@endsection
