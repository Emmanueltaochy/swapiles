{{--
    Boutons de partage réutilisables (fiche annonce + fenêtre « annonce publiée »).

    Paramètres :
      - $shareBase : URL canonique à partager (ex : https://swapiles.com/annonce/2435)
      - $shareText : texte d'accroche
      - $campaign  : suffixe de campagne UTM (ex : 'annonce_2435')

    Deux contextes, deux comportements — c'est ce qui manquait :
      • Dans l'APPLICATION (coque Capacitor), une page ne peut pas ouvrir
        WhatsApp/Facebook dans un nouvel onglet : les liens ne faisaient rien.
        On affiche donc le bouton de partage du téléphone, qui propose
        justement WhatsApp, Instagram, Messages, Facebook… nativement.
      • Sur le WEB, on garde les boutons par réseau, qui fonctionnent.

    Le tri est fait en JavaScript (public/js/share.js) : tout est rendu côté
    serveur, rien ne disparaît si le JavaScript est indisponible.
--}}
@php
    $utm = fn (string $source) => $shareBase
        . (str_contains($shareBase, '?') ? '&' : '?')
        . 'utm_source=' . $source . '&utm_medium=share&utm_campaign=' . $campaign;

    $waHref = 'https://wa.me/?text=' . rawurlencode($shareText . ' ' . $utm('whatsapp'));
    $fbHref = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($utm('facebook'));
    $xHref = 'https://twitter.com/intent/tweet?text=' . rawurlencode($shareText) . '&url=' . rawurlencode($utm('twitter'));
    $smsHref = 'sms:?&body=' . rawurlencode($shareText . ' ' . $utm('sms'));
@endphp

<div data-share-root>
    {{-- Partage natif : menu du téléphone (WhatsApp, Instagram, Messages…) --}}
    <button type="button"
            data-share-native
            data-share-url="{{ $utm('app') }}"
            data-share-text="{{ $shareText }}"
            hidden
            class="mb-3 flex w-full items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-teal-700">
        <span aria-hidden="true">📲</span> Partager
    </button>

    <div class="flex flex-wrap gap-2" data-share-web>
        <a href="{{ $waHref }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 rounded-xl bg-[#25D366] px-3.5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
            <span aria-hidden="true">🟢</span> WhatsApp
        </a>
        <a href="{{ $fbHref }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 rounded-xl bg-[#1877F2] px-3.5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
            <span aria-hidden="true">📘</span> Facebook
        </a>
        <a href="{{ $smsHref }}"
           class="inline-flex items-center gap-1.5 rounded-xl bg-[#34C759] px-3.5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
            <span aria-hidden="true">💬</span> Message
        </a>
        <a href="{{ $xHref }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 rounded-xl bg-gray-900 px-3.5 py-2.5 text-sm font-semibold text-white transition hover:bg-black">
            <span aria-hidden="true">✖️</span> X
        </a>
    </div>

    {{-- Copier le lien : marche partout, y compris quand rien d'autre ne marche --}}
    <button type="button"
            data-share-copy
            data-share-url="{{ $shareBase }}"
            class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-xl border-2 border-gray-200 px-3.5 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
        <span aria-hidden="true">🔗</span> Copier le lien
    </button>

    <p class="mt-2 text-center text-xs text-gray-400" data-share-hint hidden>
        Pour Instagram : copiez le lien et collez-le dans votre story ou votre bio 📸
    </p>
</div>
