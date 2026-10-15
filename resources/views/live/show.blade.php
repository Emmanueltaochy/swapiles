@extends('layouts.app')

@section('title', $live->titre . ' — Live Swap\'Îles')
@section('meta_description', ($live->enDirect() ? '🔴 En direct : ' : '') . ($live->vendeur->name ?? 'Un vendeur') . ' présente ses articles en vidéo sur Swap\'Îles.')
@section('og_image', $live->articles->first()?->images->sortBy('order')->first()?->url ?? asset('images/logo.png'))

@push('head')
    {{-- Page chargée en entier (pas par Turbo) : le lecteur vidéo démarre
         toujours, et la caméra/le micro sont autorisés pour cette page. --}}
    <meta name="turbo-visit-control" content="reload">
@endpush

@section('content')
@php
    $etatInitial = \App\Support\LiveService::etat($live, auth()->user());
    $vendeurNom = $live->vendeur->name ?? 'Vendeur';
    $termine = $live->statut === 'termine';
    // Replay : visible par le vendeur, et par tous une fois publié.
    $replay = $termine && $live->replayUrl() && ($estVendeur || $live->replayPublie()) ? $live->replayUrl() : null;
    // Lien partagé : « ?de= » permet de saluer l'arrivée de l'invité dans le chat.
    $lienPartage = route('live.show', $live) . (auth()->check() && $live->enDirect() ? '?de=' . auth()->id() : '');
    $textePartage = match (true) {
        $live->enDirect() => '🔴 ' . $vendeurNom . ' est en live sur Swap\'Îles : « ' . $live->titre . ' ». Viens voir !',
        $termine => '🎬 Revois le live de ' . $vendeurNom . ' sur Swap\'Îles : « ' . $live->titre . ' »',
        default => '📅 Live de ' . $vendeurNom . ' sur Swap\'Îles' . ($live->debut_prevu_at ? ' ' . $live->debut_prevu_at->locale('fr')->translatedFormat('l j F à H\hi') : '') . ' : « ' . $live->titre . ' »',
    };
@endphp
<style>
    @keyframes swp-coeur {
        0%   { transform: translate(0, 0) scale(.6); opacity: 0; }
        12%  { opacity: 1; transform: translate(calc(var(--dx) * .2), -40px) scale(1.15); }
        100% { transform: translate(var(--dx), -320px) scale(.9) rotate(var(--rot)); opacity: 0; }
    }
    .swp-coeur { position: absolute; bottom: 0; right: 14px; font-size: 30px; pointer-events: none;
        animation: swp-coeur var(--duree, 2.2s) ease-out forwards; will-change: transform, opacity; }
    [data-chat] { -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 28%); mask-image: linear-gradient(to bottom, transparent 0, #000 28%); }
    [data-chat]::-webkit-scrollbar { display: none; }
</style>

<section class="fixed inset-0 z-[60] flex justify-center bg-black text-white lg:bg-gray-950 lg:py-4"
         style="height:100dvh"
         data-live="{{ $live->id }}"
         data-etat-url="{{ route('live.etat', $live, false) }}"
         data-jeton-url="{{ route('live.jeton', $live, false) }}"
         data-message-url="{{ auth()->check() ? route('live.message', $live, false) : '' }}"
         data-coeur-url="{{ route('live.coeur', $live, false) }}"
         data-statut="{{ $live->statut }}"
         data-partage-url="{{ $lienPartage }}"
         data-partage-texte="{{ $textePartage }}"
         data-enregistrer-url="{{ $estVendeur ? route('live.enregistrer', $live, false) : '' }}"
         data-acheter-base="{{ url('/live/' . $live->id . '/acheter') }}"
         data-coeurs-initial="{{ (int) $live->coeurs }}"
         data-vendeur="{{ $estVendeur ? '1' : '0' }}">

    <div class="relative h-full w-full max-w-[480px] overflow-hidden bg-gray-900 lg:rounded-3xl lg:shadow-2xl">

        {{-- Vidéo plein écran --}}
        <video data-video playsinline autoplay muted class="absolute inset-0 hidden h-full w-full object-cover"></video>
        @if($replay)
            <video data-replay src="{{ $replay }}" playsinline controls preload="metadata" class="absolute inset-x-0 bottom-0 top-24 z-[5] h-[calc(100%-6rem)] w-full bg-black object-contain"></video>
        @endif

        {{-- Écran d'attente (avant / pendant la connexion / après) --}}
        <div data-zone-video class="absolute inset-0 flex flex-col items-center justify-center px-8 pb-[34vh] pt-24 text-center">
            @if($etatInitial['epingle']['photo'] ?? null)
                <img src="{{ $etatInitial['epingle']['photo'] }}" alt="" class="absolute inset-0 h-full w-full scale-110 object-cover opacity-30 blur-2xl">
            @endif
            <div class="relative">
                @if($termine)
                    <p class="text-6xl">🌴</p>
                    <p class="mt-4 text-xl font-extrabold">Ce live est terminé</p>
                    <a href="{{ route('live.index') }}" class="mt-6 inline-block rounded-full bg-white px-6 py-3 text-sm font-extrabold text-gray-900">Voir les autres lives</a>
                @elseif(! $live->enDirect())
                    <p class="text-6xl">📅</p>
                    <p class="mt-4 text-xl font-extrabold">Le live n’a pas encore commencé</p>
                    @if($live->debut_prevu_at)<p class="mt-1 text-sm text-white/70">Prévu {{ $live->debut_prevu_at->locale('fr')->translatedFormat('l j F à H\hi') }}</p>@endif
                    @if($estVendeur)
                        <p class="mt-3 text-sm font-semibold text-white/85">🔔 {{ $nbInscrits }} personne{{ $nbInscrits > 1 ? 's' : '' }} {{ $nbInscrits > 1 ? 'seront prévenues' : 'sera prévenue' }} au démarrage</p>
                        <form method="POST" action="{{ route('live.demarrer', $live) }}" class="mt-6">@csrf
                            <button class="rounded-full bg-red-600 px-8 py-4 text-base font-extrabold text-white shadow-lg shadow-red-900/40 active:scale-95">🔴 Démarrer le live</button>
                        </form>
                    @else
                        @auth
                            <form method="POST" action="{{ route('live.rappel', $live) }}" class="mt-6">@csrf
                                @if($inscrit)
                                    <button class="rounded-full bg-white/15 px-7 py-3.5 text-base font-extrabold ring-1 ring-white/50">✅ Vous serez prévenu</button>
                                    <p class="mt-2 text-xs text-white/70">Appuyez à nouveau pour annuler</p>
                                @else
                                    <button class="rounded-full bg-white px-7 py-3.5 text-base font-extrabold text-gray-900 shadow-lg active:scale-95">🔔 Me prévenir</button>
                                    <p class="mt-2 text-xs text-white/70">Notification 15 min avant et au début du live</p>
                                @endif
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="mt-6 inline-block rounded-full bg-white px-7 py-3.5 text-base font-extrabold text-gray-900">🔔 Me prévenir</a>
                        @endauth
                    @endif
                @elseif($estVendeur)
                    <p class="text-6xl">📹</p>
                    <p class="mt-4 text-xl font-extrabold">Vos spectateurs vous attendent</p>
                    <button type="button" data-camera class="mt-6 rounded-full bg-red-600 px-8 py-4 text-base font-extrabold text-white shadow-lg shadow-red-900/40 active:scale-95">Activer ma caméra</button>
                @else
                    <span class="mx-auto block h-12 w-12 animate-spin rounded-full border-4 border-white/20 border-t-white" data-video-roue></span>
                    <p class="mt-4 text-base font-bold drop-shadow" data-video-message>Connexion au direct…</p>
                @endif
            </div>
        </div>

        <button type="button" data-son class="absolute inset-x-0 top-1/2 z-20 mx-auto hidden w-max -translate-y-1/2 rounded-full bg-white px-6 py-3 text-sm font-extrabold text-gray-900 shadow-xl">🔊 Activer le son</button>

        {{-- Haut : vendeur, titre, direct, spectateurs, fermer --}}
        <div class="absolute inset-x-0 top-0 z-10 bg-gradient-to-b from-black/70 via-black/30 to-transparent px-3 pb-10" style="padding-top:max(12px, env(safe-area-inset-top))">
            <div class="flex items-center gap-2.5">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-teal-600 text-base font-extrabold ring-2 ring-white/80">{{ mb_strtoupper(mb_substr($vendeurNom, 0, 1)) }}</span>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-sm font-extrabold drop-shadow">{{ $vendeurNom }}</p>
                    <p class="truncate text-xs text-white/85 drop-shadow">{{ $live->titre }}</p>
                </div>
                <span data-pastille-statut class="shrink-0 rounded-md px-2 py-1 text-[11px] font-extrabold tracking-wide {{ $live->enDirect() ? 'bg-red-600 text-white' : 'bg-white/90 text-gray-800' }}">
                    {{ $live->enDirect() ? 'LIVE' : \App\Models\LiveSession::STATUTS[$live->statut] }}
                </span>
                <span class="shrink-0 rounded-md bg-black/40 px-2 py-1 text-[11px] font-bold">👁 <span data-spectateurs>{{ $etatInitial['spectateurs'] }}</span></span>
                <a href="{{ route('live.index') }}" class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-black/40 text-lg font-bold" aria-label="Quitter le live">✕</a>
            </div>

            @unless(config('live.ouvert_a_tous'))
                <p class="mt-2 w-max rounded-full bg-amber-400/90 px-2.5 py-0.5 text-[11px] font-bold text-amber-950">🧪 Test : visible seulement par vous</p>
            @endunless
            @if(session('status'))
                <p class="mt-2 rounded-xl bg-white/95 px-3 py-2 text-sm font-semibold text-gray-900" role="status">{{ session('status') }}</p>
            @endif
            @if($estVendeur && $live->enDirect())
                <div class="mt-2 flex items-center gap-2">
                    <form method="POST" action="{{ route('live.terminer', $live) }}" onsubmit="return confirm('Terminer le live pour tout le monde ?');">@csrf
                        <button class="rounded-full bg-black/50 px-3 py-1.5 text-xs font-bold ring-1 ring-white/40">⏹ Terminer le live</button>
                    </form>
                    <span data-rec class="{{ $live->enregistrement_id ? '' : 'hidden' }} rounded-full bg-black/50 px-2.5 py-1 text-[11px] font-extrabold text-red-400">⏺ Enregistré</span>
                </div>
            @endif
            @if($estVendeur && $termine)
                <div class="mt-3 rounded-2xl bg-white p-3 text-gray-900 shadow-xl">
                    @if($live->replayUrl())
                        <p class="text-sm font-extrabold">🎬 Le replay de votre live</p>
                        <p class="mt-0.5 text-xs text-gray-600">{{ $live->replayPublie() ? 'Visible sur votre profil et dans la page des lives.' : 'Publiez-le sur votre profil : vos articles continuent d’être vus.' }} La vidéo peut mettre quelques minutes à être prête.</p>
                        <div class="mt-2.5 flex gap-2">
                            <form method="POST" action="{{ route('live.replay', $live) }}" class="flex-1">@csrf
                                <input type="hidden" name="publier" value="{{ $live->replayPublie() ? '0' : '1' }}">
                                <button class="w-full rounded-xl px-3 py-2.5 text-sm font-extrabold {{ $live->replayPublie() ? 'border border-gray-300 text-gray-700' : 'bg-teal-600 text-white' }}">{{ $live->replayPublie() ? 'Retirer du profil' : 'Publier sur mon profil' }}</button>
                            </form>
                            <button type="button" data-partager class="flex items-center gap-1.5 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-extrabold text-white"><svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M13 5.5V2l9 8-9 8v-3.6c-5.6 0-9.4 1.7-12 5.6 1-5.6 4.3-11.2 12-14.5Z"/></svg> Partager</button>
                        </div>
                    @else
                        <p class="text-sm font-extrabold">Live terminé 🌴</p>
                        <p class="mt-0.5 text-xs text-gray-600">Ce live n’a pas été enregistré, il n’y a donc pas de replay.</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Cœurs qui s'envolent --}}
        <div data-coeurs-zone class="pointer-events-none absolute bottom-48 right-1 z-10 h-80 w-24" aria-hidden="true"></div>

        {{-- Colonne de droite --}}
        <div class="absolute right-2 z-20 flex flex-col items-center gap-4" style="bottom:calc(150px + env(safe-area-inset-bottom))">
            @if($estVendeur && $live->enDirect())
                <div data-outils-camera class="hidden flex-col items-center gap-4">
                    <button type="button" data-retourner class="grid h-12 w-12 place-items-center rounded-full bg-black/45 text-xl active:scale-90" aria-label="Changer de caméra">🔄</button>
                    <button type="button" data-micro class="grid h-12 w-12 place-items-center rounded-full bg-black/45 text-xl active:scale-90" aria-label="Couper le micro">🎙️</button>
                </div>
            @endif
            <button type="button" data-partager class="grid h-12 w-12 place-items-center rounded-full bg-black/45 text-xl active:scale-90" aria-label="Partager le live"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="currentColor" aria-hidden="true"><path d="M13 5.5V2l9 8-9 8v-3.6c-5.6 0-9.4 1.7-12 5.6 1-5.6 4.3-11.2 12-14.5Z"/></svg></button>
            @php $nbArticles = $estVendeur ? $live->articles->count() : count($etatInitial['articles']); @endphp
            <button type="button" data-ouvrir-articles class="relative grid h-12 w-12 place-items-center rounded-full bg-black/45 text-xl transition active:scale-90" aria-label="Voir les articles du live">
                🛍️
                <span data-nb-articles class="{{ $nbArticles ? '' : 'hidden' }} absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-teal-500 px-1 text-[10px] font-extrabold">{{ $nbArticles }}</span>
            </button>
            @unless($termine)
                <button type="button" data-coeur class="grid h-14 w-14 place-items-center rounded-full bg-black/45 text-3xl active:scale-90" aria-label="Envoyer un cœur">❤️</button>
            @endunless
        </div>

        {{-- Bas : chat, article mis en avant, saisie --}}
        <div class="{{ $replay ? 'hidden' : '' }} absolute inset-x-0 bottom-0 z-10 bg-gradient-to-t from-black/80 via-black/40 to-transparent px-3 pt-16" style="padding-bottom:max(12px, env(safe-area-inset-bottom))">
            <div data-chat class="{{ $replay ? 'hidden' : '' }} mb-2 max-h-[30vh] w-[78%] space-y-1.5 overflow-y-auto overscroll-contain pt-10 text-sm" aria-live="polite"></div>

            <div data-epingle class="{{ $etatInitial['epingle'] ? '' : 'hidden' }} mb-2 mr-16 rounded-2xl bg-white p-2 text-gray-900 shadow-xl">
                <div class="flex items-center gap-2.5">
                    <img data-epingle-photo src="{{ $etatInitial['epingle']['photo'] ?? '' }}" alt="" class="h-14 w-14 shrink-0 rounded-xl bg-gray-100 object-cover">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-extrabold text-red-600">📌 En ce moment</p>
                        <p data-epingle-titre class="truncate text-sm font-bold">{{ $etatInitial['epingle']['titre'] ?? '' }}</p>
                        <p data-epingle-prix class="text-base font-extrabold">{{ $etatInitial['epingle']['prix'] ?? '' }}</p>
                    </div>
                    @unless($estVendeur)
                        <form method="POST" data-acheter-form action="{{ $etatInitial['epingle'] ? route('live.acheter', [$live, $etatInitial['epingle']['id']]) : '#' }}">
                            @csrf
                            <button data-acheter class="rounded-xl bg-teal-600 px-3.5 py-3 text-sm font-extrabold text-white active:scale-95 disabled:bg-gray-300 disabled:text-gray-600">Acheter</button>
                        </form>
                    @endunless
                </div>
                <p data-epingle-info class="mt-1.5 hidden px-1 text-xs font-semibold text-amber-700"></p>
            </div>

            @auth
                @if($live->statut !== 'termine')
                    <form data-chat-form class="mr-16 flex gap-2">
                        <label for="live-message" class="sr-only">Votre message</label>
                        <input id="live-message" maxlength="300" autocomplete="off" enterkeyhint="send" placeholder="{{ $estVendeur ? 'Écrire à vos spectateurs…' : 'Posez une question…' }}"
                               class="min-w-0 flex-1 rounded-full border-0 bg-white/20 px-4 py-2.5 text-base text-white placeholder-white/70 backdrop-blur focus:bg-white/30 focus:ring-2 focus:ring-white/60">
                        <button class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-teal-600 text-lg font-bold" aria-label="Envoyer">➤</button>
                    </form>
                @endif
            @elseif(! $termine)
                <a href="{{ route('login') }}" class="mr-16 block rounded-full bg-white/20 px-4 py-2.5 text-center text-sm font-semibold backdrop-blur">Connectez-vous pour discuter et acheter</a>
            @endauth
        </div>
    </div>

    {{-- Articles du live (panneau du bas) --}}
    <div data-feuille class="fixed inset-0 z-[70] hidden" role="dialog" aria-modal="true" aria-label="Articles du live">
        <button type="button" data-fermer-articles class="absolute inset-0 bg-black/50" aria-label="Fermer"></button>
        <div class="absolute inset-x-0 bottom-0 mx-auto max-h-[70vh] max-w-[480px] overflow-y-auto rounded-t-3xl bg-white p-4 text-gray-900" style="padding-bottom:max(16px, env(safe-area-inset-bottom))">
            <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-gray-300"></div>
            <div class="mb-3 flex items-center justify-between">
                <p class="text-base font-extrabold">🛍️ Articles du live</p>
                <button type="button" data-fermer-articles class="grid h-9 w-9 place-items-center rounded-full bg-gray-100 font-bold" aria-label="Fermer">✕</button>
            </div>
            @if($estVendeur)
                <p class="mb-2 rounded-xl bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-900">Vos articles sont <b>masqués</b> au départ. Dévoilez-les au fil du live 👁, ou mettez-en un <b>en avant</b> 📌 pour l’afficher en grand à l’écran.</p>
                <ul class="divide-y divide-gray-100">
                    @foreach($live->articles as $a)
                        @php $vendu = $a->status === 'sold'; $visible = (bool) $a->pivot->visible; @endphp
                        <li class="flex items-center gap-3 py-2.5">
                            <img src="{{ $a->images->sortBy('order')->first()?->url }}" alt="{{ $a->title }}" class="h-14 w-14 shrink-0 rounded-xl bg-gray-100 object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold">{{ $vendu ? '✅ ' : '' }}{{ $a->title }}</p>
                                <p class="text-sm font-extrabold">{{ \App\Support\TableauDeBord::euros((float) $a->price) }}</p>
                            </div>
                            <button type="button" data-visibilite="{{ route('live.visibilite', [$live, $a], false) }}" data-visible="{{ $visible ? '1' : '0' }}" data-listing="{{ $a->id }}"
                                    class="shrink-0 rounded-xl border px-2.5 py-2 text-xs font-extrabold data-[visible=0]:border-gray-300 data-[visible=0]:text-gray-500 data-[visible=1]:border-teal-600 data-[visible=1]:bg-teal-50 data-[visible=1]:text-teal-800">
                                <span class="in-data-[visible=1]:hidden">🙈 Masqué</span><span class="hidden in-data-[visible=1]:inline">👁 Visible</span>
                            </button>
                            @unless($vendu || $termine)
                                <button type="button" data-epingler="{{ route('live.epingler', $live, false) }}" data-listing="{{ $a->id }}"
                                        @if($live->article_epingle_id === $a->id) data-actif="1" @endif
                                        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border-2 border-teal-600 text-base data-[actif=1]:bg-teal-600" aria-label="Mettre en avant à l’écran">📌</button>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @else
                <ul data-liste-articles class="divide-y divide-gray-100"></ul>
                <p data-articles-vide class="hidden py-8 text-center text-sm font-semibold text-gray-500">{{ $termine ? 'Aucun article présenté.' : 'Le vendeur va bientôt dévoiler ses articles 👀' }}</p>
                <script type="application/json" data-articles-initiaux>@json($etatInitial['articles'])</script>
            @endif
        </div>
    </div>
</section>

<script>
(function () {
    var racine = document.querySelector('[data-live]');
    if (!racine) return;
    var jetonCsrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var vendeur = racine.dataset.vendeur === '1';
    var chat = racine.querySelector('[data-chat]');
    var zoneCoeurs = racine.querySelector('[data-coeurs-zone]');
    var dernier = 0;
    var coeursConnus = parseInt(racine.dataset.coeursInitial || '0', 10);
    var actif = true;

    // Page de live : rien ne défile derrière.
    document.documentElement.style.overflow = 'hidden';
    function quitter() {
        actif = false;
        document.documentElement.style.overflow = '';
        if (window.swpLiveSalle) { try { window.swpLiveSalle.disconnect(); } catch (e) {} window.swpLiveSalle = null; }
    }
    document.addEventListener('turbo:before-render', quitter, { once: true });
    window.addEventListener('pagehide', quitter, { once: true });

    function poster(url, donnees) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': jetonCsrf },
            body: JSON.stringify(donnees || {}),
        });
    }

    // --- Cœurs ---------------------------------------------------------------
    var formes = ['❤️', '❤️', '❤️', '💖', '💕', '🧡', '💛'];
    function envolerCoeur(delai) {
        setTimeout(function () {
            if (!actif) return;
            var c = document.createElement('span');
            c.className = 'swp-coeur';
            c.textContent = formes[Math.floor(Math.random() * formes.length)];
            c.style.setProperty('--dx', (Math.random() * 70 - 50).toFixed(0) + 'px');
            c.style.setProperty('--rot', (Math.random() * 40 - 20).toFixed(0) + 'deg');
            c.style.setProperty('--duree', (1.8 + Math.random() * 1.2).toFixed(2) + 's');
            c.style.fontSize = (24 + Math.random() * 14).toFixed(0) + 'px';
            c.addEventListener('animationend', function () { c.remove(); });
            zoneCoeurs.appendChild(c);
        }, delai || 0);
    }

    var boutonCoeur = racine.querySelector('[data-coeur]');
    if (boutonCoeur) boutonCoeur.addEventListener('click', function () {
        coeursConnus++;
        envolerCoeur(0);
        if (navigator.vibrate) navigator.vibrate(10);
        poster(racine.dataset.coeurUrl);
    });

    // --- Partager ------------------------------------------------------------
    // Le menu de partage du téléphone s'ouvre PAR-DESSUS le live : on choisit
    // WhatsApp, Messenger… on envoie, et on revient direct au live, qui n'a
    // pas été interrompu. Sans menu natif, on copie le lien.
    function bulle(texte) {
        var b = document.createElement('div');
        b.className = 'fixed left-1/2 top-1/3 z-[80] -translate-x-1/2 rounded-full bg-white px-5 py-3 text-sm font-extrabold text-gray-900 shadow-2xl';
        b.textContent = texte;
        document.body.appendChild(b);
        setTimeout(function () { b.remove(); }, 2200);
    }
    function partager() {
        var url = racine.dataset.partageUrl, texte = racine.dataset.partageTexte;
        var cap = window.Capacitor;
        var natif = cap && cap.isNativePlatform && cap.isNativePlatform() && cap.Plugins && cap.Plugins.Share;
        if (natif) { cap.Plugins.Share.share({ title: "Live Swap'Îles", text: texte, url: url }).catch(function () {}); return; }
        if (navigator.share) { navigator.share({ title: "Live Swap'Îles", text: texte, url: url }).catch(function () {}); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () { bulle('✅ Lien copié, collez-le à vos amis'); }, function () { window.prompt('Copiez le lien :', url); });
        } else { window.prompt('Copiez le lien :', url); }
    }
    racine.querySelectorAll('[data-partager]').forEach(function (b) { b.addEventListener('click', partager); });

    // --- Chat ----------------------------------------------------------------
    function ajouterMessage(m) {
        var ligne = document.createElement('div');
        ligne.dataset.message = m.id;
        if (m.type !== 'message') {
            ligne.className = 'w-max max-w-full rounded-xl bg-black/35 px-2.5 py-1 text-xs font-bold text-amber-200';
            ligne.textContent = m.texte;
        } else {
            ligne.className = 'leading-snug drop-shadow-[0_1px_2px_rgba(0,0,0,.8)]';
            var nom = document.createElement('span');
            nom.className = 'mr-1 font-extrabold ' + (m.vendeur ? 'text-teal-300' : 'text-white/75');
            nom.textContent = (m.auteur || 'Membre') + (m.vendeur ? ' ⭐' : '');
            var texte = document.createElement('span');
            texte.className = 'text-white';
            texte.textContent = m.texte;
            ligne.appendChild(nom); ligne.appendChild(texte);
            if (vendeur && !m.vendeur) {
                var outils = document.createElement('span');
                outils.className = 'ml-2 whitespace-nowrap text-[11px]';
                outils.innerHTML = '<button type="button" data-masquer class="text-white/60 underline">masquer</button> · <button type="button" data-exclure class="text-red-300 underline">exclure</button>';
                outils.querySelector('[data-masquer]').onclick = function () { poster(location.pathname + '/messages/' + m.id + '/masquer').then(function () { ligne.remove(); }); };
                outils.querySelector('[data-exclure]').onclick = function () {
                    if (confirm('Exclure ce membre du live ?')) poster(location.pathname + '/messages/' + m.id + '/exclure').then(function () { ligne.remove(); });
                };
                ligne.appendChild(outils);
            }
        }
        var enBas = chat.scrollTop + chat.clientHeight >= chat.scrollHeight - 40;
        chat.appendChild(ligne);
        while (chat.children.length > 80) chat.firstChild.remove();
        if (enBas) chat.scrollTop = chat.scrollHeight;
    }

    var form = racine.querySelector('[data-chat-form]');
    if (form) form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var champ = form.querySelector('input');
        var texte = champ.value.trim();
        if (!texte) return;
        champ.value = '';
        poster(racine.dataset.messageUrl, { texte: texte }).then(function () { chat.scrollTop = chat.scrollHeight; });
    });

    // --- Article mis en avant ------------------------------------------------
    function majEpingle(e) {
        var bloc = racine.querySelector('[data-epingle]');
        if (!e) { bloc.classList.add('hidden'); return; }
        bloc.classList.remove('hidden');
        var photo = racine.querySelector('[data-epingle-photo]');
        if (photo.getAttribute('src') !== (e.photo || '')) photo.src = e.photo || '';
        racine.querySelector('[data-epingle-titre]').textContent = e.titre;
        racine.querySelector('[data-epingle-prix]').textContent = e.prix;
        var info = racine.querySelector('[data-epingle-info]');
        var bouton = racine.querySelector('[data-acheter]');
        var formAchat = racine.querySelector('[data-acheter-form]');
        var texte = e.vendu ? '✅ Vendu !' : (e.reserve ? '⏳ Quelqu’un est en train de l’acheter…' : (!e.achetable ? 'Paiement en ligne non disponible pour cet article.' : ''));
        info.textContent = texte;
        info.classList.toggle('hidden', !texte);
        if (bouton && formAchat) {
            formAchat.action = location.pathname + '/acheter/' + e.id;
            bouton.disabled = e.vendu || e.reserve || !e.achetable;
            bouton.textContent = e.vendu ? 'Vendu' : 'Acheter';
        }
        racine.querySelectorAll('[data-epingler]').forEach(function (b) { b.dataset.actif = String(b.dataset.listing) === String(e.id) ? '1' : '0'; });
    }

    // --- Articles (panneau) --------------------------------------------------
    var feuille = racine.querySelector('[data-feuille]');
    racine.querySelector('[data-ouvrir-articles]').addEventListener('click', function () { feuille.classList.remove('hidden'); });
    racine.querySelectorAll('[data-fermer-articles]').forEach(function (b) { b.addEventListener('click', function () { feuille.classList.add('hidden'); }); });
    racine.querySelectorAll('[data-epingler]').forEach(function (b) {
        b.addEventListener('click', function () {
            racine.querySelectorAll('[data-epingler]').forEach(function (x) { x.dataset.actif = x === b ? '1' : '0'; });
            // Mis en avant = forcément visible.
            var v = racine.querySelector('[data-visibilite][data-listing="' + b.dataset.listing + '"]');
            if (v) v.dataset.visible = '1';
            poster(b.dataset.epingler, { listing_id: b.dataset.listing });
            setTimeout(function () { feuille.classList.add('hidden'); }, 250);
        });
    });
    // Vendeur : montrer / cacher un article dans la liste des spectateurs.
    racine.querySelectorAll('[data-visibilite]').forEach(function (b) {
        b.addEventListener('click', function () {
            var visible = b.dataset.visible !== '1';
            b.dataset.visible = visible ? '1' : '0';
            if (!visible) {
                var e = racine.querySelector('[data-epingler][data-listing="' + b.dataset.listing + '"]');
                if (e) e.dataset.actif = '0';
            }
            poster(b.dataset.visibilite, { visible: visible });
        });
    });

    // Spectateur : la liste se remplit au fur et à mesure que le vendeur dévoile.
    var liste = racine.querySelector('[data-liste-articles]');
    var nbArticles = racine.querySelector('[data-nb-articles]');
    var boutonArticles = racine.querySelector('[data-ouvrir-articles]');
    var signatureArticles = null, nbConnus = null;
    function echapper(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
    function majArticles(articles) {
        if (!liste || !articles) return;
        var signature = JSON.stringify(articles);
        if (signature === signatureArticles) return;
        signatureArticles = signature;
        var enDirect = racine.dataset.statut === 'en_direct';
        liste.innerHTML = articles.map(function (a) {
            var action;
            if (a.vendu) action = '<span class="shrink-0 rounded-lg bg-gray-100 px-3 py-2 text-xs font-bold text-gray-500">✅ Vendu</span>';
            else if (enDirect && a.achetable) action = '<form method="POST" action="' + racine.dataset.acheterBase + '/' + a.id + '" class="shrink-0"><input type="hidden" name="_token" value="' + jetonCsrf + '"><button class="rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-extrabold text-white active:scale-95">Acheter</button></form>';
            else action = '<a href="' + echapper(a.lien) + '" class="shrink-0 rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700">Voir</a>';
            return '<li class="flex items-center gap-3 py-2.5"><img src="' + echapper(a.photo || '') + '" alt="" class="h-16 w-16 shrink-0 rounded-xl bg-gray-100 object-cover">'
                + '<div class="min-w-0 flex-1"><p class="truncate text-sm font-bold">' + echapper(a.titre) + '</p><p class="text-sm font-extrabold">' + echapper(a.prix) + '</p></div>' + action + '</li>';
        }).join('');
        racine.querySelector('[data-articles-vide]').classList.toggle('hidden', articles.length > 0);
        nbArticles.textContent = articles.length;
        nbArticles.classList.toggle('hidden', articles.length === 0);
        // Nouvel article dévoilé : le bouton 🛍️ s'anime.
        if (nbConnus !== null && articles.length > nbConnus) {
            boutonArticles.classList.add('ring-4', 'ring-teal-400', 'scale-110');
            setTimeout(function () { boutonArticles.classList.remove('ring-4', 'ring-teal-400', 'scale-110'); }, 2500);
        }
        nbConnus = articles.length;
    }
    var initiaux = racine.querySelector('[data-articles-initiaux]');
    if (initiaux) { try { majArticles(JSON.parse(initiaux.textContent)); } catch (e) {} }

    // --- État du live (toutes les 2,5 s) -------------------------------------
    function rafraichir() {
        if (!actif || !document.body.contains(racine)) return;
        fetch(racine.dataset.etatUrl + '?apres=' + dernier, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (etat) {
                if (etat.exclu) { chat.innerHTML = '<p class="text-sm text-white/80">Vous ne pouvez plus participer à ce live.</p>'; return; }
                if (etat.statut !== racine.dataset.statut) { location.reload(); return; }
                racine.querySelector('[data-spectateurs]').textContent = etat.spectateurs;
                // Cœurs des autres spectateurs : on les fait s'envoler aussi.
                var nouveaux = etat.coeurs - coeursConnus;
                for (var i = 0; i < Math.min(nouveaux, 8); i++) envolerCoeur(i * 220);
                coeursConnus = Math.max(coeursConnus, etat.coeurs);
                etat.messages.forEach(function (m) { ajouterMessage(m); dernier = Math.max(dernier, m.id); });
                majEpingle(etat.epingle);
                majArticles(etat.articles);
            })
            .catch(function () {})
            .finally(function () { if (actif) setTimeout(rafraichir, 2500); });
    }
    rafraichir();
})();
</script>

@if($live->enDirect())
<script>
(function () {
    var racine = document.querySelector('[data-live]');
    if (!racine) return;
    var video = racine.querySelector('[data-video]');
    var zone = racine.querySelector('[data-zone-video]');
    var boutonSon = racine.querySelector('[data-son]');
    var vendeur = racine.dataset.vendeur === '1';
    var salle = null;

    // Bibliothèque vidéo chargée par ce script lui-même : elle est prête
    // avant qu'on s'en serve, quelle que soit la façon d'ouvrir la page.
    function chargerLiveKit() {
        if (window.LivekitClient) return Promise.resolve(window.LivekitClient);
        if (!window.swpChargementLiveKit) {
            window.swpChargementLiveKit = new Promise(function (ok, ko) {
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/livekit-client@2.5.7/dist/livekit-client.umd.min.js';
                s.onload = function () { window.LivekitClient ? ok(window.LivekitClient) : ko(new Error('chargement')); };
                s.onerror = function () { window.swpChargementLiveKit = null; ko(new Error('chargement')); };
                document.head.appendChild(s);
            });
        }
        return window.swpChargementLiveKit;
    }

    function message(texte, attente) {
        var m = zone.querySelector('[data-video-message]');
        var roue = zone.querySelector('[data-video-roue]');
        if (m) m.textContent = texte;
        if (roue) roue.classList.toggle('hidden', !attente);
        video.classList.add('hidden');
        zone.classList.remove('hidden');
    }

    function afficherVideo(piste, miroir) {
        piste.attach(video);
        video.classList.remove('hidden');
        video.style.transform = miroir ? 'scaleX(-1)' : '';
        zone.classList.add('hidden');
    }

    function jeton() {
        return fetch(racine.dataset.jetonUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j.statut = r.status; return j; }); });
    }

    function connecter(LK, acces) {
        salle = new LK.Room({ adaptiveStream: true, dynacast: true });
        window.swpLiveSalle = salle;
        salle.on(LK.RoomEvent.TrackSubscribed, function (piste) {
            if (piste.kind === 'video') afficherVideo(piste, false);
            if (piste.kind === 'audio') {
                var el = piste.attach(); el.style.display = 'none'; document.body.appendChild(el);
            }
        });
        salle.on(LK.RoomEvent.TrackUnsubscribed, function (piste) {
            piste.detach();
            if (piste.kind === 'video' && !vendeur) message('Le vendeur a coupé sa caméra… elle revient sûrement 🙂', true);
        });
        salle.on(LK.RoomEvent.AudioPlaybackStatusChanged, function () {
            boutonSon.classList.toggle('hidden', salle.canPlaybackAudio);
        });
        salle.on(LK.RoomEvent.Reconnecting, function () { if (!vendeur) message('Connexion instable, on se reconnecte…', true); });
        salle.on(LK.RoomEvent.Disconnected, function () {
            if (window.swpLiveSalle === salle && !vendeur) message('Vidéo interrompue. Rechargez la page.', false);
        });
        return salle.connect(acces.url, acces.jeton).then(function () {
            boutonSon.classList.toggle('hidden', salle.canPlaybackAudio);
            if (!vendeur && !video.srcObject) message('Le vendeur prépare sa caméra…', true);
        });
    }

    boutonSon.addEventListener('click', function () {
        if (salle) salle.startAudio().then(function () { boutonSon.classList.add('hidden'); });
    });

    if (!vendeur) {
        message('Connexion au direct…', true);
        Promise.all([chargerLiveKit(), jeton()]).then(function (r) {
            var LK = r[0], acces = r[1];
            if (acces.statut === 503) { message('La vidéo arrive très bientôt 🎥', false); return; }
            if (acces.statut === 403) { message('Vous ne pouvez plus regarder ce live.', false); return; }
            if (acces.statut !== 200) { message('Vidéo indisponible pour le moment.', false); return; }
            return connecter(LK, acces);
        }).catch(function () { message('Impossible de lancer la vidéo. Vérifiez votre connexion puis rechargez la page.', false); });
        return;
    }

    // Vendeur : la caméra démarre sur un geste (exigé par l'iPhone).
    var bouton = racine.querySelector('[data-camera]');
    if (!bouton) return;
    chargerLiveKit().catch(function () {});
    var avant = false;
    bouton.addEventListener('click', function () {
        bouton.disabled = true;
        bouton.textContent = 'Démarrage…';
        var LK;
        Promise.all([chargerLiveKit(), jeton()]).then(function (r) {
            LK = r[0];
            var acces = r[1];
            if (acces.statut === 503) throw new Error('La vidéo n’est pas encore configurée sur le serveur.');
            if (acces.statut !== 200) throw new Error('Accès vidéo refusé.');
            return connecter(LK, acces);
        }).then(function () {
            return LK.createLocalTracks({ audio: true, video: { facingMode: 'environment', resolution: LK.VideoPresets.h720.resolution } });
        }).then(function (pistes) {
            pistes.forEach(function (p) {
                salle.localParticipant.publishTrack(p);
                if (p.kind === 'video') afficherVideo(p, false);
            });
            var outils = racine.querySelector('[data-outils-camera]');
            outils.classList.remove('hidden'); outils.classList.add('flex');
            // La caméra tourne : on lance l'enregistrement du replay (si activé).
            if (racine.dataset.enregistrerUrl) {
                fetch(racine.dataset.enregistrerUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
                    .then(function (r) { return r.json(); })
                    .then(function (j) { if (j.enregistre) { var rec = racine.querySelector('[data-rec]'); if (rec) rec.classList.remove('hidden'); } })
                    .catch(function () {});
            }
            outils.querySelector('[data-retourner]').onclick = function () {
                avant = !avant;
                var pv = pistes.find(function (p) { return p.kind === 'video'; });
                pv.restartTrack({ facingMode: avant ? 'user' : 'environment' }).then(function () { video.style.transform = avant ? 'scaleX(-1)' : ''; });
            };
            var micro = outils.querySelector('[data-micro]');
            micro.onclick = function () {
                var allume = salle.localParticipant.isMicrophoneEnabled;
                salle.localParticipant.setMicrophoneEnabled(!allume).then(function () {
                    micro.textContent = allume ? '🔇' : '🎙️';
                    micro.setAttribute('aria-label', allume ? 'Rallumer le micro' : 'Couper le micro');
                });
            };
        }).catch(function (e) {
            bouton.disabled = false;
            bouton.textContent = 'Réessayer';
            alert((e && e.message && e.message !== 'chargement') ? e.message : 'Impossible d’activer la caméra. Autorisez la caméra et le micro pour ce site, puis réessayez.');
        });
    });
})();
</script>
@endif
@endsection
