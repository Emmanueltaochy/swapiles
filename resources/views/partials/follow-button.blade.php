{{--
    Bouton « Suivre » un dressing — le même partout (profil, page d'annonce).

    Suivre un membre, c'est être prévenu dès qu'il publie un article : comme
    sur un réseau social. Le clic ne recharge pas la page (public/js/follow.js),
    et le nombre d'abonnés affiché ailleurs dans la page se met à jour.

    Paramètres :
      $seller  : le membre à suivre
      $classes : classes de taille (facultatif)
--}}
@php
    $moi = auth()->user();
    $classesBouton = 'inline-flex items-center justify-center gap-1.5 rounded-xl font-semibold transition '
        . 'bg-teal-600 text-white hover:bg-teal-700 '
        . 'data-[suivi=1]:bg-white data-[suivi=1]:text-gray-700 data-[suivi=1]:ring-1 data-[suivi=1]:ring-gray-200 data-[suivi=1]:hover:bg-gray-50 '
        . ($classes ?? 'px-4 py-2 text-sm');
@endphp

@if(! $moi)
    {{-- Visiteur : un BOUTON (il peut se trouver dans un lien), qui mène à la connexion. --}}
    <button type="button" data-suivre-connexion="{{ route('login') }}" data-suivi="0" class="{{ $classesBouton }}">
        <span aria-hidden="true">➕</span> Suivre
    </button>
@elseif($moi->id !== $seller->id && ! $moi->hasBlocked($seller))
    @php $suivi = $moi->suit($seller); @endphp
    <button type="button"
            data-suivre-url="{{ route('account.seller-follow.toggle', $seller) }}"
            data-suivre-vendeur="{{ $seller->id }}"
            data-suivre-nom="{{ $seller->name }}"
            data-suivi="{{ $suivi ? '1' : '0' }}"
            aria-pressed="{{ $suivi ? 'true' : 'false' }}"
            class="{{ $classesBouton }}">
        <span data-suivre-libelle>{{ $suivi ? '✓ Abonné' : '➕ Suivre' }}</span>
    </button>
@endif
