{{-- Badge « Répond vite » : délai de réponse habituel du vendeur (recalculé chaque nuit). --}}
@php $reponse = \App\Support\TempsReponse::libelle($vendeur->reponse_minutes); @endphp
@if($reponse)
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold {{ $reponse['rapide'] ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}"
          title="Délai de réponse habituel aux messages ces 60 derniers jours">{{ $reponse['texte'] }}</span>
@endif
