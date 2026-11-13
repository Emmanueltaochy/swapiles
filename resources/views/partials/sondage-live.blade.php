{{--
    Sondage « Aimeriez-vous faire du live shopping ? » (accueil, Mon compte).
    $compact = true : sur Mon compte, une fois répondu, un simple lien pour
    demander à organiser des lives (ou l'état de la demande).
--}}
@php
    $membre = auth()->user();
    $compact = $compact ?? false;
    $aDemander = \App\Support\InteretLive::aDemander($membre);
    $peutDemander = \App\Support\InteretLive::peutDemander($membre);
    $ouvrirDemande = session('sondage_live_demande');
@endphp
@if($membre && ($aDemander || $ouvrirDemande || ($compact && ($peutDemander || $membre->live_demande_statut === 'en_attente'))))
    <div id="sondage-live" data-sondage-live class="scroll-mt-24 rounded-3xl border border-rose-100 bg-gradient-to-br from-rose-50 to-white p-5 shadow-sm">
        @if($compact && ! $aDemander && ! $ouvrirDemande)
            @if($membre->live_demande_statut === 'en_attente')
                <p class="text-sm font-semibold text-gray-800">🔴 Ta demande pour faire des lives est en cours d’examen. Tu seras prévenu(e) dès qu’elle est acceptée.</p>
            @else
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-gray-800">🔴 Envie de présenter tes articles en live ?</p>
                    <button type="button" data-ouvrir-demande class="rounded-2xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700">Demander à faire des lives</button>
                </div>
            @endif
        @else
            <div data-etape="question" @class(['hidden' => $ouvrirDemande])>
                <p class="text-xs font-bold uppercase tracking-wide text-rose-600">Nouveau sur Swap’Îles</p>
                <h2 class="mt-1 text-lg font-extrabold text-gray-900">🔴 Aimeriez-vous faire du live shopping ?</h2>
                <p class="mt-1 text-sm text-gray-600">Des ventes en direct, comme sur les réseaux : on voit l’article, on pose ses questions, on achète en un clic.</p>
                <form method="POST" action="{{ route('live.interet') }}" class="mt-4 grid gap-2 sm:grid-cols-3">
                    @csrf
                    <button name="choix" value="regarder" class="rounded-2xl border border-rose-200 bg-white px-4 py-3 text-left text-sm font-bold text-gray-900 hover:border-rose-400">👀 Oui, regarder et acheter</button>
                    <button name="choix" value="organiser" class="rounded-2xl border border-rose-200 bg-white px-4 py-3 text-left text-sm font-bold text-gray-900 hover:border-rose-400">🎥 Oui, organiser mes lives</button>
                    <button name="choix" value="les_deux" class="rounded-2xl border border-rose-200 bg-white px-4 py-3 text-left text-sm font-bold text-gray-900 hover:border-rose-400">✨ Les deux</button>
                    <button name="choix" value="non" class="text-left text-xs font-semibold text-gray-500 underline underline-offset-2 sm:col-span-3">Pas pour l’instant</button>
                </form>
            </div>
        @endif

        <div data-etape="demande" @class(['hidden' => ! $ouvrirDemande])>
            <h2 class="text-lg font-extrabold text-gray-900">🎥 Super ! Qu’aimerais-tu vendre en live ?</h2>
            <p class="mt-1 text-sm text-gray-600">Les lives ouvrent petit à petit. Dis-nous en deux mots ce que tu présenterais : Emmanuel valide les demandes une par une.</p>
            <form method="POST" action="{{ route('live.demande') }}" class="mt-3 space-y-3">
                @csrf
                <label for="sondage-live-message" class="sr-only">Ce que tu aimerais vendre en live</label>
                <textarea id="sondage-live-message" name="message" rows="2" maxlength="500" placeholder="Ex. : vide-dressing enfant, sneakers, déco créole…"
                          class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm focus:border-rose-400 focus:ring-rose-200"></textarea>
                <button class="w-full rounded-2xl bg-rose-600 px-4 py-3 text-sm font-bold text-white hover:bg-rose-700 sm:w-auto">Envoyer ma demande</button>
            </form>
        </div>

        <p data-etape="merci" class="hidden text-sm font-semibold text-gray-800"></p>
    </div>

    @once
        <script>
        document.addEventListener('click', function (e) {
            const carte = e.target.closest('[data-sondage-live]');
            if (!carte) return;
            const montrer = (etape, texte) => {
                carte.querySelectorAll('[data-etape]').forEach(el => el.classList.toggle('hidden', el.dataset.etape !== etape));
                if (texte) carte.querySelector('[data-etape="merci"]').textContent = texte;
            };
            if (e.target.closest('[data-ouvrir-demande]')) {
                e.target.closest('[data-ouvrir-demande]').closest('div').classList.add('hidden');
                montrer('demande');
            }
        });
        document.addEventListener('submit', async function (e) {
            const carte = e.target.closest('[data-sondage-live]');
            if (!carte) return;
            e.preventDefault();
            const donnees = new FormData(e.target);
            if (e.submitter && e.submitter.name) donnees.set(e.submitter.name, e.submitter.value);
            try {
                const r = await fetch(e.target.action, { method: 'POST', body: donnees, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const res = await r.json();
                carte.querySelectorAll('[data-etape]').forEach(el => el.classList.toggle('hidden', el.dataset.etape !== res.etape));
                if (res.message) carte.querySelector('[data-etape="merci"]').textContent = '✅ ' + res.message;
                if (res.etape === 'merci') setTimeout(() => carte.remove(), 6000);
            } catch (err) {
                // Erreur : envoi classique (avec le bouton choisi).
                if (e.submitter && e.submitter.name) {
                    const cache = document.createElement('input');
                    cache.type = 'hidden'; cache.name = e.submitter.name; cache.value = e.submitter.value;
                    e.target.appendChild(cache);
                }
                e.target.submit();
            }
        });
        </script>
    @endonce
@endif
