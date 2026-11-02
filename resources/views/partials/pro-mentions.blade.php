{{-- Informations obligatoires quand le vendeur est un professionnel. --}}
<div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs leading-relaxed text-indigo-950">
    <p class="font-bold">🏬 Vendu par un professionnel : {{ $pro->pro_raison_sociale }}</p>
    <p class="mt-0.5 text-indigo-900/80">SIRET {{ \App\Support\ComptePro::siret($pro->pro_siret) }}@if($pro->pro_adresse) · {{ $pro->pro_adresse }}@endif</p>
    <p class="mt-2">✅ <b>Droit de rétractation de 14 jours</b> après réception, sans avoir à vous justifier (hors exceptions légales : articles personnalisés, produits d’hygiène descellés…).</p>
    <p>✅ <b>Garantie légale de conformité</b> et garantie contre les vices cachés.</p>
    @if($pro->pro_retours)<p class="mt-1">📄 <b>Conditions du vendeur :</b> {{ $pro->pro_retours }}</p>@endif
    <p class="mt-1"><a href="{{ route('legal.vendeurs-pro') }}" class="font-semibold underline">Conditions de vente des professionnels sur Swap’Îles</a></p>
</div>
