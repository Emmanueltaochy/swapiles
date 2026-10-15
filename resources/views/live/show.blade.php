@extends('layouts.app')

@section('title', $live->titre . ' — Live Swap\'Îles')

@section('content')
@php
    $etatInitial = \App\Support\LiveService::etat($live, auth()->user());
@endphp
<section class="max-w-5xl mx-auto px-0 sm:px-4 py-0 sm:py-6"
         data-live="{{ $live->id }}"
         data-etat-url="{{ route('live.etat', $live, false) }}"
         data-message-url="{{ auth()->check() ? route('live.message', $live, false) : '' }}"
         data-coeur-url="{{ route('live.coeur', $live, false) }}"
         data-statut="{{ $live->statut }}"
         data-vendeur="{{ $estVendeur ? '1' : '0' }}">

    @unless(config('live.ouvert_a_tous'))
        <p class="bg-amber-50 px-4 py-2 text-center text-xs font-semibold text-amber-800">🧪 Mode test : ce live n’est visible que par vous.</p>
    @endunless
    @if(session('status'))
        <p class="bg-teal-50 px-4 py-2 text-center text-sm font-semibold text-teal-800" role="status">{{ session('status') }}</p>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,420px)_1fr] gap-0 lg:gap-6">
        {{-- Vidéo --}}
        <div class="relative bg-gray-900 lg:rounded-3xl overflow-hidden" style="aspect-ratio:9/14;max-height:72vh;">
            <video data-video playsinline autoplay muted class="absolute inset-0 h-full w-full object-cover hidden"></video>
            <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-white" data-zone-video>
                @if($live->statut === 'termine')
                    <p class="text-5xl">🌴</p>
                    <p class="mt-3 font-bold">Ce live est terminé</p>
                @elseif(! $live->enDirect())
                    <p class="text-5xl">📅</p>
                    <p class="mt-3 font-bold">Le live n’a pas encore commencé</p>
                    @if($live->debut_prevu_at)<p class="mt-1 text-sm text-white/70">Prévu {{ $live->debut_prevu_at->locale('fr')->translatedFormat('l j F à H\hi') }}</p>@endif
                @elseif($estVendeur)
                    <p class="text-5xl">📹</p>
                    <p class="mt-3 font-bold">Vos spectateurs vous attendent</p>
                    <button type="button" data-camera class="mt-4 rounded-2xl bg-red-600 px-6 py-3 font-extrabold text-white">Activer ma caméra</button>
                @else
                    <p class="text-5xl">⏳</p>
                    <p class="mt-3 font-bold" data-video-message>Connexion à la vidéo…</p>
                @endif
            </div>
            <button type="button" data-son class="absolute inset-x-0 bottom-20 mx-auto hidden w-max rounded-full bg-white px-5 py-2.5 text-sm font-extrabold text-gray-900 shadow-lg">🔊 Activer le son</button>
            @if($estVendeur && $live->enDirect())
                <div data-outils-camera class="absolute bottom-3 left-3 hidden gap-2">
                    <button type="button" data-retourner class="rounded-full bg-black/50 px-3 py-2 text-xs font-bold text-white">🔄 Caméra</button>
                    <button type="button" data-micro class="rounded-full bg-black/50 px-3 py-2 text-xs font-bold text-white">🎙️ Micro activé</button>
                </div>
            @endif

            <div class="absolute left-3 top-3 flex items-center gap-2">
                <span data-pastille-statut class="rounded-full px-2.5 py-1 text-xs font-extrabold {{ $live->enDirect() ? 'bg-red-600 text-white' : 'bg-white/90 text-gray-800' }}">
                    {{ $live->enDirect() ? 'EN DIRECT' : \App\Models\LiveSession::STATUTS[$live->statut] }}
                </span>
                <span class="rounded-full bg-black/50 px-2.5 py-1 text-xs font-bold text-white">👁 <span data-spectateurs>{{ $etatInitial['spectateurs'] }}</span></span>
            </div>
            <button type="button" data-coeur class="absolute right-3 bottom-3 grid h-12 w-12 place-items-center rounded-full bg-black/40 text-2xl text-white active:scale-90" aria-label="Envoyer un cœur">❤️</button>
            <span class="absolute right-4 bottom-16 text-xs font-bold text-white drop-shadow" data-coeurs>{{ $live->coeurs }}</span>

            <div class="absolute inset-x-0 top-12 px-3">
                <p class="truncate text-sm font-bold text-white drop-shadow">{{ $live->titre }}</p>
                <p class="text-xs text-white/80 drop-shadow">{{ $live->vendeur->name ?? '' }}</p>
            </div>
        </div>

        <div class="flex flex-col gap-4 p-4 lg:p-0">
            {{-- Article mis en avant --}}
            <div data-epingle class="{{ $etatInitial['epingle'] ? '' : 'hidden' }} rounded-2xl border border-teal-100 bg-white p-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <img data-epingle-photo src="{{ $etatInitial['epingle']['photo'] ?? '' }}" alt="" class="h-16 w-16 shrink-0 rounded-xl object-cover bg-gray-100">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-teal-700">📌 En ce moment</p>
                        <p data-epingle-titre class="truncate font-bold text-gray-900">{{ $etatInitial['epingle']['titre'] ?? '' }}</p>
                        <p data-epingle-prix class="text-sm font-extrabold text-gray-900">{{ $etatInitial['epingle']['prix'] ?? '' }}</p>
                    </div>
                    @unless($estVendeur)
                        <form method="POST" data-acheter-form action="{{ $etatInitial['epingle'] ? route('live.acheter', [$live, $etatInitial['epingle']['id']]) : '#' }}">
                            @csrf
                            <button data-acheter class="rounded-xl bg-teal-700 px-4 py-3 text-sm font-extrabold text-white hover:bg-teal-800 disabled:bg-gray-300 disabled:text-gray-600">Acheter</button>
                        </form>
                    @endunless
                </div>
                <p data-epingle-info class="mt-2 hidden text-xs font-semibold text-amber-700"></p>
            </div>

            {{-- Commandes du vendeur --}}
            @if($estVendeur)
                <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                    <div class="flex flex-wrap gap-2">
                        @if($live->statut === 'programme')
                            <form method="POST" action="{{ route('live.demarrer', $live) }}">@csrf
                                <button class="rounded-xl bg-red-600 px-5 py-3 font-extrabold text-white">🔴 Démarrer le live</button>
                            </form>
                        @elseif($live->enDirect())
                            <form method="POST" action="{{ route('live.terminer', $live) }}" onsubmit="return confirm('Terminer le live ?');">@csrf
                                <button class="rounded-xl border-2 border-gray-300 bg-white px-5 py-3 font-bold text-gray-800">Terminer le live</button>
                            </form>
                        @endif
                    </div>
                    <p class="mt-4 mb-2 text-sm font-bold text-gray-800">Vos articles : appuyez pour le mettre en avant</p>
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        @foreach($live->articles as $a)
                            <button type="button" data-epingler="{{ route('live.epingler', $live, false) }}" data-listing="{{ $a->id }}"
                                    class="w-24 shrink-0 overflow-hidden rounded-xl border-2 border-transparent bg-white text-left data-[actif=1]:border-teal-600"
                                    @if($live->article_epingle_id === $a->id) data-actif="1" @endif>
                                <img src="{{ $a->images->sortBy('order')->first()?->url }}" alt="" class="aspect-square w-full object-cover bg-gray-100">
                                <span class="block truncate px-1.5 py-1 text-[11px] font-semibold text-gray-800">{{ $a->status === 'sold' ? '✅ ' : '' }}{{ $a->title }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Chat --}}
            <div class="flex min-h-[280px] flex-1 flex-col rounded-2xl border border-gray-100 bg-white">
                <div data-chat class="flex-1 space-y-2 overflow-y-auto p-3 text-sm" style="max-height:45vh;" aria-live="polite"></div>
                @auth
                    @if($live->statut !== 'termine')
                        <form data-chat-form class="flex gap-2 border-t border-gray-100 p-2">
                            <label for="live-message" class="sr-only">Votre message</label>
                            <input id="live-message" maxlength="300" autocomplete="off" placeholder="Posez une question…" class="min-w-0 flex-1 rounded-xl border border-gray-200 px-3 py-2.5 text-base">
                            <button class="rounded-xl bg-teal-700 px-4 font-bold text-white">Envoyer</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="border-t border-gray-100 p-3 text-center text-sm font-semibold text-teal-700">Connectez-vous pour participer au chat</a>
                @endauth
            </div>
        </div>
    </div>
</section>

@if($live->enDirect())
<script src="https://cdn.jsdelivr.net/npm/livekit-client@2.5.7/dist/livekit-client.umd.min.js"></script>
<script>
(function () {
    var racine = document.querySelector('[data-live]');
    if (!racine || typeof LivekitClient === 'undefined') return;
    var LK = LivekitClient;
    var video = racine.querySelector('[data-video]');
    var zone = racine.querySelector('[data-zone-video]');
    var boutonSon = racine.querySelector('[data-son]');
    var vendeur = racine.dataset.vendeur === '1';
    var salle = null;

    function message(texte) {
        var m = zone.querySelector('[data-video-message]');
        if (m) m.textContent = texte; else zone.innerHTML = '<p class="font-bold">' + texte + '</p>';
        zone.classList.remove('hidden');
    }

    function afficherVideo(piste, miroir) {
        piste.attach(video);
        video.classList.remove('hidden');
        video.style.transform = miroir ? 'scaleX(-1)' : '';
        zone.classList.add('hidden');
    }

    function jeton() {
        return fetch('{{ route('live.jeton', $live, false) }}', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { j.statut = r.status; return j; }); });
    }

    function connecter(acces) {
        salle = new LK.Room({ adaptiveStream: true, dynacast: true });
        salle.on(LK.RoomEvent.TrackSubscribed, function (piste) {
            if (piste.kind === 'video') afficherVideo(piste, false);
            if (piste.kind === 'audio') {
                var el = piste.attach(); el.style.display = 'none'; document.body.appendChild(el);
            }
        });
        salle.on(LK.RoomEvent.TrackUnsubscribed, function (piste) {
            piste.detach();
            if (piste.kind === 'video' && !vendeur) { video.classList.add('hidden'); message('Le vendeur a coupé sa caméra…'); }
        });
        salle.on(LK.RoomEvent.AudioPlaybackStatusChanged, function () {
            boutonSon.classList.toggle('hidden', salle.canPlaybackAudio);
        });
        salle.on(LK.RoomEvent.Disconnected, function () {
            if (!vendeur) message('Vidéo interrompue. Rechargez la page.');
        });
        return salle.connect(acces.url, acces.jeton).then(function () {
            boutonSon.classList.toggle('hidden', salle.canPlaybackAudio);
            if (!vendeur && !video.srcObject) message('Le vendeur se prépare…');
        });
    }

    boutonSon.addEventListener('click', function () {
        if (salle) salle.startAudio().then(function () { boutonSon.classList.add('hidden'); });
    });

    if (!vendeur) {
        jeton().then(function (acces) {
            if (acces.statut === 503) { message('La vidéo arrive très bientôt 🎥'); return; }
            if (acces.statut !== 200) { message('Vidéo indisponible.'); return; }
            return connecter(acces);
        }).catch(function () { message('Vidéo indisponible. Vérifiez votre connexion.'); });
        return;
    }

    // Vendeur : la caméra démarre sur un geste (exigé par iPhone).
    var bouton = racine.querySelector('[data-camera]');
    if (!bouton) return;
    var avant = false;
    bouton.addEventListener('click', function () {
        bouton.disabled = true;
        bouton.textContent = 'Démarrage…';
        jeton().then(function (acces) {
            if (acces.statut === 503) throw new Error('La vidéo n’est pas encore configurée sur le serveur.');
            if (acces.statut !== 200) throw new Error('Accès vidéo refusé.');
            return connecter(acces);
        }).then(function () {
            return LK.createLocalTracks({ audio: true, video: { facingMode: 'environment', resolution: LK.VideoPresets.h720.resolution } });
        }).then(function (pistes) {
            pistes.forEach(function (p) {
                salle.localParticipant.publishTrack(p);
                if (p.kind === 'video') afficherVideo(p, false);
            });
            var outils = racine.querySelector('[data-outils-camera]');
            outils.classList.remove('hidden'); outils.classList.add('flex');
            outils.querySelector('[data-retourner]').onclick = function () {
                avant = !avant;
                var pv = pistes.find(function (p) { return p.kind === 'video'; });
                pv.restartTrack({ facingMode: avant ? 'user' : 'environment' }).then(function () { video.style.transform = avant ? 'scaleX(-1)' : ''; });
            };
            var micro = outils.querySelector('[data-micro]');
            micro.onclick = function () {
                var actif = salle.localParticipant.isMicrophoneEnabled;
                salle.localParticipant.setMicrophoneEnabled(!actif).then(function () { micro.textContent = actif ? '🔇 Micro coupé' : '🎙️ Micro activé'; });
            };
        }).catch(function (e) {
            bouton.disabled = false;
            bouton.textContent = 'Réessayer';
            alert((e && e.message) ? e.message : 'Impossible d’activer la caméra. Autorisez la caméra et le micro pour ce site.');
        });
    });
})();
</script>
@endif

<script>
(function () {
    var racine = document.querySelector('[data-live]');
    if (!racine) return;
    var jeton = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var vendeur = racine.dataset.vendeur === '1';
    var dernier = 0;
    var chat = racine.querySelector('[data-chat]');

    function poster(url, donnees) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': jeton },
            body: JSON.stringify(donnees || {}),
        });
    }

    function ajouterMessage(m) {
        var ligne = document.createElement('div');
        ligne.dataset.message = m.id;
        if (m.type !== 'message') {
            ligne.className = 'rounded-lg bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-800';
            ligne.textContent = m.texte;
        } else {
            var nom = document.createElement('span');
            nom.className = 'font-bold ' + (m.vendeur ? 'text-teal-700' : 'text-gray-900');
            nom.textContent = (m.auteur || 'Membre') + (m.vendeur ? ' (vendeur)' : '') + ' ';
            var texte = document.createElement('span');
            texte.className = 'text-gray-700';
            texte.textContent = m.texte;
            ligne.appendChild(nom); ligne.appendChild(texte);
            if (vendeur && !m.vendeur) {
                var outils = document.createElement('span');
                outils.className = 'ml-2 text-[11px]';
                outils.innerHTML = '<button type="button" data-masquer class="text-gray-400 underline">masquer</button> · <button type="button" data-exclure class="text-red-500 underline">exclure</button>';
                outils.querySelector('[data-masquer]').onclick = function () { poster(location.pathname + '/messages/' + m.id + '/masquer').then(function () { ligne.remove(); }); };
                outils.querySelector('[data-exclure]').onclick = function () {
                    if (confirm('Exclure ce membre du live ?')) poster(location.pathname + '/messages/' + m.id + '/exclure').then(function () { ligne.remove(); });
                };
                ligne.appendChild(outils);
            }
        }
        var enBas = chat.scrollTop + chat.clientHeight >= chat.scrollHeight - 40;
        chat.appendChild(ligne);
        if (enBas) chat.scrollTop = chat.scrollHeight;
    }

    function majEpingle(e) {
        var bloc = racine.querySelector('[data-epingle]');
        if (!e) { bloc.classList.add('hidden'); return; }
        bloc.classList.remove('hidden');
        racine.querySelector('[data-epingle-photo]').src = e.photo || '';
        racine.querySelector('[data-epingle-titre]').textContent = e.titre;
        racine.querySelector('[data-epingle-prix]').textContent = e.prix;
        var info = racine.querySelector('[data-epingle-info]');
        var bouton = racine.querySelector('[data-acheter]');
        var form = racine.querySelector('[data-acheter-form]');
        var message = e.vendu ? '✅ Vendu !' : (e.reserve ? '⏳ Quelqu’un est en train de l’acheter…' : (!e.achetable ? 'Paiement en ligne non disponible pour cet article.' : ''));
        info.textContent = message;
        info.classList.toggle('hidden', !message);
        if (bouton && form) {
            form.action = location.pathname + '/acheter/' + e.id;
            bouton.disabled = e.vendu || e.reserve || !e.achetable;
            bouton.textContent = e.vendu ? 'Vendu' : 'Acheter';
        }
        racine.querySelectorAll('[data-epingler]').forEach(function (b) { b.dataset.actif = String(b.dataset.listing) === String(e.id) ? '1' : '0'; });
    }

    function rafraichir() {
        if (!document.body.contains(racine)) return; // page quittée
        fetch(racine.dataset.etatUrl + '?apres=' + dernier, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (etat) {
                if (etat.exclu) { chat.innerHTML = '<p class="text-sm text-gray-500">Vous ne pouvez plus participer à ce live.</p>'; return; }
                if (etat.statut !== racine.dataset.statut) { location.reload(); return; }
                racine.querySelector('[data-spectateurs]').textContent = etat.spectateurs;
                racine.querySelector('[data-coeurs]').textContent = etat.coeurs;
                etat.messages.forEach(function (m) { ajouterMessage(m); dernier = Math.max(dernier, m.id); });
                majEpingle(etat.epingle);
            })
            .catch(function () {})
            .finally(function () { setTimeout(rafraichir, 2500); });
    }
    rafraichir();

    var form = racine.querySelector('[data-chat-form]');
    if (form) form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var champ = form.querySelector('input');
        var texte = champ.value.trim();
        if (!texte) return;
        champ.value = '';
        poster(racine.dataset.messageUrl, { texte: texte });
    });

    racine.querySelector('[data-coeur]').addEventListener('click', function () {
        var n = racine.querySelector('[data-coeurs]');
        n.textContent = String(parseInt(n.textContent || '0', 10) + 1);
        poster(racine.dataset.coeurUrl);
    });

    racine.querySelectorAll('[data-epingler]').forEach(function (b) {
        b.addEventListener('click', function () { poster(b.dataset.epingler, { listing_id: b.dataset.listing }); });
    });
})();
</script>
@endsection
