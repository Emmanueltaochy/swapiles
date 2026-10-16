{{--
    Stories automatiques de l'accueil : un rond par dressing qui a publié ces
    7 derniers jours, illustré par son DERNIER ARTICLE (la photo de profil
    est en pastille). Vert = nouveautés pas encore vues, gris = déjà vues.
    On touche : les nouveaux articles défilent en plein écran (5 s chacun),
    puis le dressing suivant. Le vendeur n'a rien à faire : ses annonces
    sont sa story.
--}}
<section class="bg-white border-b border-gray-100" data-stories
         data-connecte="{{ auth()->check() ? '1' : '0' }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3 pb-2">
        <p class="text-xs font-bold uppercase tracking-wide text-gray-500">📸 Nouveautés des dressings</p>
        <div class="-mx-4 mt-2 flex gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach($stories as $story)
                @php $vendeur = $story['vendeur']; @endphp
                <button type="button" class="group w-[72px] shrink-0 text-center"
                        data-story-rond="{{ $vendeur->id }}"
                        data-story-url="{{ route('stories.show', $vendeur) }}"
                        data-story-vu-url="{{ route('stories.vu', $vendeur) }}"
                        data-story-dernier="{{ $story['dernier']->timestamp }}"
                        data-story-vu="{{ $story['vu'] ? '1' : '0' }}"
                        aria-label="Nouveautés de {{ $vendeur->name }} ({{ $story['nombre'] }} article{{ $story['nombre'] > 1 ? 's' : '' }})">
                    {{-- Grand rond : la photo du dernier article publié ; petite pastille : le vendeur. --}}
                    <span class="relative mx-auto block h-[68px] w-[68px]">
                        <span class="block h-full w-full rounded-full p-[3px] transition
                                     bg-gradient-to-tr from-emerald-400 via-teal-500 to-teal-700
                                     group-data-[story-vu=1]:bg-none group-data-[story-vu=1]:bg-gray-300 group-data-[story-vu=1]:p-[2px]">
                            <span class="block h-full w-full rounded-full bg-white p-[2px]">
                                <span class="grid h-full w-full place-items-center overflow-hidden rounded-full bg-gray-100 text-2xl">
                                    @if($story['image'])
                                        <img src="{{ $story['image'] }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                    @else
                                        📦
                                    @endif
                                </span>
                            </span>
                        </span>
                        <span class="absolute -bottom-0.5 -right-0.5 grid h-6 w-6 place-items-center overflow-hidden rounded-full bg-teal-600 text-[10px] font-extrabold text-white ring-2 ring-white">
                            @if($vendeur->avatar)
                                <img src="{{ $vendeur->avatar }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            @else
                                {{ mb_strtoupper(mb_substr($vendeur->name ?? '?', 0, 1)) }}
                            @endif
                        </span>
                    </span>
                    <span class="mt-1 block truncate text-xs font-semibold text-gray-800 group-data-[story-vu=1]:font-normal group-data-[story-vu=1]:text-gray-500">{{ $vendeur->name }}</span>
                </button>
            @endforeach
        </div>
    </div>
</section>

{{-- Visionneuse plein écran (remplie par le script). --}}
<div data-story-vue hidden role="dialog" aria-modal="true" aria-label="Nouveautés du dressing"
     class="fixed inset-0 z-[10000] select-none bg-black text-white">
    <div class="absolute inset-0 overflow-hidden">
        <img data-story-fond alt="" class="h-full w-full scale-110 object-cover opacity-40 blur-2xl">
    </div>
    <img data-story-image alt="" class="absolute inset-0 m-auto max-h-full max-w-full object-contain">

    {{-- Zones de toucher : gauche = précédent, droite = suivant. --}}
    <button type="button" data-story-precedent class="absolute inset-y-0 left-0 w-1/3" aria-label="Article précédent"></button>
    <button type="button" data-story-suivant class="absolute inset-y-0 right-0 w-2/3" aria-label="Article suivant"></button>

    <div class="pointer-events-none absolute inset-x-0 top-0 bg-gradient-to-b from-black/70 to-transparent px-3 pb-8 pt-[max(0.75rem,env(safe-area-inset-top))]">
        <div data-story-barres class="flex gap-1"></div>
        <div class="pointer-events-auto mt-3 flex items-center gap-2.5">
            <a data-story-profil class="flex min-w-0 flex-1 items-center gap-2.5">
                <span data-story-avatar class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-teal-600 text-sm font-extrabold"></span>
                <span class="min-w-0">
                    <span data-story-nom class="block truncate text-sm font-bold"></span>
                    <span data-story-quand class="block text-xs text-white/75"></span>
                </span>
            </a>
            <button type="button" data-story-fermer class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-3xl leading-none" aria-label="Fermer">×</button>
        </div>
    </div>

    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent px-4 pt-16 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
        <p data-story-titre class="line-clamp-2 text-lg font-bold"></p>
        <p data-story-prix class="mt-0.5 text-2xl font-extrabold"></p>
        <a data-story-lien class="mt-3 flex w-full items-center justify-center rounded-2xl bg-white px-4 py-3.5 text-base font-bold text-gray-900">Voir l’article</a>
    </div>
</div>

<script>
    (function () {
        // Une seule écoute pour tout le site (navigation Turbo sans rechargement).
        if (window.swpStories) return;
        window.swpStories = true;

        var DUREE = 5000;
        var etat = null; // { ronds, indexRond, data, index, debut, ecoule, pause, minuteur }

        function $(sel) { return document.querySelector(sel); }
        function vue() { return $('[data-story-vue]'); }

        function cleVu(id) { return 'swp-story-vu-' + id; }
        function lireVu(id) { try { return parseInt(localStorage.getItem(cleVu(id)) || '0', 10); } catch (e) { return 0; } }
        function ecrireVu(id, t) { try { localStorage.setItem(cleVu(id), String(t)); } catch (e) {} }

        // Visiteur non connecté : on se souvient des ronds vus sur ce téléphone.
        function peindreRonds() {
            document.querySelectorAll('[data-story-rond]').forEach(function (r) {
                if (lireVu(r.dataset.storyRond) >= parseInt(r.dataset.storyDernier, 10)) r.dataset.storyVu = '1';
            });
        }
        // Arrivée depuis la notification « Nouveautés de vos dressings » : on
        // ouvre directement la première story pas encore vue.
        function ouvrirDepuisNotification() {
            if (!/[?&]stories=1\b/.test(location.search)) return;
            var rond = document.querySelector('[data-story-rond][data-story-vu="0"]') || document.querySelector('[data-story-rond]');
            if (rond) ouvrir(rond);
            history.replaceState(null, '', location.pathname);
        }

        document.addEventListener('turbo:load', function () { peindreRonds(); ouvrirDepuisNotification(); });
        peindreRonds();
        setTimeout(ouvrirDepuisNotification, 0);

        function marquerVu(rond) {
            rond.dataset.storyVu = '1';
            ecrireVu(rond.dataset.storyRond, parseInt(rond.dataset.storyDernier, 10));
            var bloc = rond.closest('[data-stories]');
            if (bloc && bloc.dataset.connecte === '1') {
                var jeton = document.querySelector('meta[name="csrf-token"]');
                fetch(rond.dataset.storyVuUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': jeton ? jeton.content : '', 'Accept': 'application/json' },
                    credentials: 'same-origin'
                }).catch(function () {});
            }
        }

        function ouvrir(rond) {
            var ronds = Array.prototype.slice.call(document.querySelectorAll('[data-story-rond]'));
            etat = { ronds: ronds, indexRond: ronds.indexOf(rond), data: null, index: 0, ecoule: 0, pause: false, minuteur: null };
            vue().hidden = false;
            document.documentElement.style.overflow = 'hidden';
            chargerRond();
        }

        function fermer() {
            if (!etat) return;
            clearInterval(etat.minuteur);
            etat = null;
            var v = vue();
            if (v) v.hidden = true;
            document.documentElement.style.overflow = '';
        }

        function chargerRond() {
            var rond = etat.ronds[etat.indexRond];
            if (!rond) return fermer();
            var demande = etat;
            fetch(rond.dataset.storyUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (etat !== demande) return;
                    if (!data || !data.articles.length) return suivantRond();
                    etat.data = data;
                    etat.index = 0;
                    marquerVu(rond);
                    dessinerBarres();
                    afficher();
                })
                .catch(function () { if (etat === demande) suivantRond(); });
        }

        function dessinerBarres() {
            var barres = $('[data-story-barres]');
            barres.innerHTML = '';
            etat.data.articles.forEach(function () {
                var b = document.createElement('div');
                b.className = 'h-[3px] flex-1 overflow-hidden rounded-full bg-white/35';
                b.innerHTML = '<div class="h-full w-0 bg-white"></div>';
                barres.appendChild(b);
            });
            var v = etat.data.vendeur;
            var avatar = $('[data-story-avatar]');
            avatar.innerHTML = '';
            if (v.avatar) {
                var img = document.createElement('img');
                img.src = v.avatar; img.alt = ''; img.className = 'h-full w-full object-cover';
                avatar.appendChild(img);
            } else {
                avatar.textContent = (v.nom || '?').charAt(0).toUpperCase();
            }
            $('[data-story-nom]').textContent = v.nom;
            $('[data-story-profil]').href = v.profil;
        }

        function afficher() {
            var a = etat.data.articles[etat.index];
            $('[data-story-image]').src = a.image || '';
            $('[data-story-fond]').src = a.image || '';
            $('[data-story-titre]').textContent = a.titre;
            $('[data-story-prix]').textContent = a.prix;
            $('[data-story-quand]').textContent = 'Publié ' + a.quand;
            $('[data-story-lien]').href = a.url;
            Array.prototype.forEach.call($('[data-story-barres]').children, function (b, i) {
                b.firstChild.style.width = i < etat.index ? '100%' : '0%';
            });
            etat.ecoule = 0;
            clearInterval(etat.minuteur);
            var dernier = Date.now();
            etat.minuteur = setInterval(function () {
                var maintenant = Date.now();
                if (!etat.pause) etat.ecoule += maintenant - dernier;
                dernier = maintenant;
                var barre = $('[data-story-barres]').children[etat.index];
                if (barre) barre.firstChild.style.width = Math.min(100, etat.ecoule / DUREE * 100) + '%';
                if (etat.ecoule >= DUREE) suivant();
            }, 50);
        }

        function suivant() {
            if (!etat || !etat.data) return;
            if (etat.index < etat.data.articles.length - 1) { etat.index++; afficher(); }
            else suivantRond();
        }
        function precedent() {
            if (!etat || !etat.data) return;
            if (etat.index > 0) { etat.index--; afficher(); }
            else if (etat.indexRond > 0) { etat.indexRond--; clearInterval(etat.minuteur); chargerRond(); }
            else afficher();
        }
        function suivantRond() {
            if (!etat) return;
            clearInterval(etat.minuteur);
            etat.indexRond++;
            if (etat.indexRond >= etat.ronds.length) return fermer();
            chargerRond();
        }

        document.addEventListener('click', function (e) {
            var rond = e.target.closest('[data-story-rond]');
            if (rond) { ouvrir(rond); return; }
            if (!etat) return;
            if (etat.appuiLong) { etat.appuiLong = false; return; } // relâché après une pause : pas de saut
            if (e.target.closest('[data-story-fermer]')) fermer();
            else if (e.target.closest('[data-story-suivant]')) suivant();
            else if (e.target.closest('[data-story-precedent]')) precedent();
        });

        // Appui long = pause (comme Instagram) ; glisser vers le bas = fermer.
        var departY = null, minuteurAppui = null;
        document.addEventListener('pointerdown', function (e) {
            if (!etat || !e.target.closest('[data-story-vue]')) return;
            departY = e.clientY;
            minuteurAppui = setTimeout(function () { if (etat) { etat.pause = true; etat.appuiLong = true; } }, 250);
        });
        document.addEventListener('pointerup', function (e) {
            clearTimeout(minuteurAppui);
            if (!etat) return;
            etat.pause = false;
            if (departY !== null && e.clientY - departY > 90) { etat.appuiLong = true; fermer(); }
            departY = null;
        });
        document.addEventListener('keydown', function (e) {
            if (!etat) return;
            if (e.key === 'Escape') fermer();
            if (e.key === 'ArrowRight') suivant();
            if (e.key === 'ArrowLeft') precedent();
        });
        document.addEventListener('visibilitychange', function () { if (etat) etat.pause = document.hidden; });
        document.addEventListener('turbo:before-render', fermer);
    })();
</script>
