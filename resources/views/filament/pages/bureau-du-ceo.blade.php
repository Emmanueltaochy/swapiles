<x-filament-panels::page>
    @php
        $oui = fn ($b) => $b ? '✅' : '❌';
        $domaines = ['produit' => '🧩 Produit', 'marketing' => '📣 Marketing', 'prospection' => '🤝 Prospection', 'technique' => '🛠️ Technique', 'communaute' => '💬 Communauté'];
        $types = config('agent.rse_types');
    @endphp

@php
        $aLire = $rapport ? trim($rapport->texte . ' ' . (count($rapport->actions ?? []) ? 'Mes trois actions pour aujourd’hui. ' . collect($rapport->actions)->map(fn ($a) => ['Un', 'Deux', 'Trois'][($a['id'] ?? 1) - 1] . ' : ' . ($a['titre'] ?? '') . '.')->implode(' ') : '')) : 'Pas encore de résumé aujourd’hui. Le premier arrive à sept heures.';
    @endphp
    {{-- Parler au CEO : question à la voix (ou au clavier), réponse lue à voix haute. --}}
    <x-filament::section>
        <x-slot name="heading">🎙️ Parler au CEO</x-slot>
        <x-slot name="description">Pose ta question à voix haute : il répond avec les vrais chiffres de Swap’Îles et te lit sa réponse.</x-slot>
        <div id="ceo-voix" wire:ignore data-resume="{{ $aLire }}">
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <button type="button" data-micro aria-label="Parler au CEO"
                        style="width:76px;height:76px;border-radius:9999px;border:0;background:#0f766e;color:#fff;font-size:2rem;box-shadow:0 8px 24px rgba(15,118,110,.35);cursor:pointer;transition:transform .15s;">🎙️</button>
                <div style="flex:1;min-width:180px;">
                    <div data-etat style="font-weight:700;">Touche le micro et parle : on discute ensuite sans retoucher.</div>
                    <div data-aide style="font-size:.8rem;opacity:.7;margin-top:.15rem;"></div>
                </div>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                    <button type="button" data-resume-lire style="border:1px solid rgba(148,163,184,.4);border-radius:.7rem;padding:.5rem .8rem;font-weight:700;font-size:.85rem;cursor:pointer;background:transparent;">🔊 Lis-moi le résumé</button>
                    <button type="button" data-stop style="border:1px solid rgba(148,163,184,.4);border-radius:.7rem;padding:.5rem .8rem;font-weight:700;font-size:.85rem;cursor:pointer;background:transparent;">⏹️ Stop</button>
                </div>
            </div>

            <div data-suggestions style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.9rem;">
                @foreach(['Comment ça va aujourd’hui ?', 'Qui a répondu à nos mails ?', 'Qu’est-ce que je fais en priorité ?', 'Combien de ventes cette semaine ?'] as $q)
                    <button type="button" data-question="{{ $q }}" style="border-radius:9999px;padding:.35rem .75rem;font-size:.8rem;background:rgba(15,118,110,.1);color:#0f766e;border:0;cursor:pointer;font-weight:600;">{{ $q }}</button>
                @endforeach
            </div>

            <div data-fil style="margin-top:.9rem;display:flex;flex-direction:column;gap:.5rem;"></div>

            <form data-clavier style="margin-top:.8rem;display:flex;gap:.5rem;">
                <input type="text" data-texte maxlength="500" placeholder="…ou écris ta question (le micro du clavier marche aussi)" autocomplete="off"
                       style="flex:1;min-width:0;border:1px solid rgba(148,163,184,.4);border-radius:.7rem;padding:.6rem .8rem;background:transparent;">
                <button type="submit" style="border:0;border-radius:.7rem;padding:.6rem .9rem;background:#0f766e;color:#fff;font-weight:700;cursor:pointer;">Envoyer</button>
            </form>
        </div>
    </x-filament::section>

    {{-- Résumé du jour --}}
    <x-filament::section>
        <x-slot name="heading">☀️ Résumé du jour {{ $rapport ? '(' . $rapport->jour->format('d/m/Y') . ')' : '' }}</x-slot>
        <x-slot name="description">Préparé chaque matin à 7 h par l’agent, à partir des chiffres de Swap’Îles (totaux, aucune donnée personnelle).</x-slot>
        @if($rapport)
            <p style="white-space:pre-line;font-size:.95rem;line-height:1.6;">{{ $rapport->texte }}</p>
        @else
            <p style="font-size:.9rem;opacity:.75;">Pas encore de résumé : le premier arrive demain à 7 h. Tu peux aussi le préparer maintenant.</p>
        @endif
        <div style="margin-top:.8rem;">
            <x-filament::button size="sm" color="gray" wire:click="genererMaintenant" wire:loading.attr="disabled">🔄 Préparer le résumé maintenant</x-filament::button>
        </div>
    </x-filament::section>

    {{-- Les 3 actions --}}
    @if($rapport && count($rapport->actions ?? []))
        <x-filament::section>
            <x-slot name="heading">🎯 Les 3 actions proposées</x-slot>
            <x-slot name="description">Dis Go ou Non : l’agent en tient compte les jours suivants.</x-slot>
            @foreach($rapport->actions as $a)
                <div style="padding:.9rem 0;border-bottom:1px solid rgba(148,163,184,.2);">
                    <div style="font-size:.75rem;font-weight:700;opacity:.65;">{{ $domaines[$a['domaine'] ?? ''] ?? '' }}</div>
                    <div style="font-weight:800;font-size:1rem;margin-top:.15rem;">{{ $a['id'] }}. {{ $a['titre'] ?? '' }}</div>
                    <div style="font-size:.9rem;margin-top:.35rem;"><strong>Pourquoi :</strong> {{ $a['pourquoi'] ?? '' }}</div>
                    <div style="font-size:.9rem;margin-top:.25rem;white-space:pre-line;"><strong>Comment :</strong> {{ $a['comment'] ?? '' }}</div>
                    <div style="margin-top:.6rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                        @if(($a['decision'] ?? null) === 'go')
                            <span style="font-weight:700;color:#059669;">✅ Go</span>
                        @elseif(($a['decision'] ?? null) === 'non')
                            <span style="font-weight:700;opacity:.6;">❌ Non</span>
                        @endif
                        <x-filament::button size="sm" color="success" wire:click="decider({{ $rapport->id }}, {{ (int) $a['id'] }}, 'go')">✅ Go</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="decider({{ $rapport->id }}, {{ (int) $a['id'] }}, 'non')">❌ Non</x-filament::button>
                    </div>
                </div>
            @endforeach
        </x-filament::section>
    @endif

    {{-- Réponses des prospects --}}
    <x-filament::section>
        <x-slot name="heading">📬 Réponses des commerces et collectivités</x-slot>
        <x-slot name="description">Lues sur contact@ (seulement les réponses des prospects contactés). Les « Intéressé » et « Question » t’arrivent aussi par notification.</x-slot>
        @forelse($reponses as $r)
            @php $pr = $r->prospect(); @endphp
            <div style="padding:.8rem 0;border-bottom:1px solid rgba(148,163,184,.2);{{ $r->vu_at ? 'opacity:.6;' : '' }}">
                <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
                    <strong>{{ \App\Models\AgentReponse::CATEGORIES[$r->categorie] ?? $r->categorie }} · {{ $pr?->nom ?? 'Prospect supprimé' }}</strong>
                    <span style="font-size:.8rem;opacity:.7;">{{ $r->recu_at?->format('d/m H:i') }} · {{ $r->prospect_type === 'rse' ? 'Collectivité' : 'Commerce' }}{{ $pr?->territoire ? ' · ' . $pr->territoire : '' }}</span>
                </div>
                <div style="font-size:.9rem;margin-top:.3rem;">{{ $r->resume }}</div>
                <div style="font-size:.8rem;opacity:.75;margin-top:.2rem;">De : {{ $r->expediteur }}{{ $pr?->telephone ? ' · Tél. ' . $pr->telephone : '' }}</div>
                @if($r->brouillon)
                    <details style="margin-top:.4rem;">
                        <summary style="cursor:pointer;font-size:.85rem;font-weight:700;">✍️ Brouillon de réponse (à copier dans ta messagerie)</summary>
                        <pre style="white-space:pre-wrap;font-family:inherit;font-size:.88rem;background:rgba(148,163,184,.12);padding:.7rem;border-radius:.6rem;margin-top:.4rem;">{{ $r->brouillon }}</pre>
                    </details>
                @endif
                @if($r->extrait)
                    <details style="margin-top:.3rem;">
                        <summary style="cursor:pointer;font-size:.8rem;opacity:.8;">Voir leur message</summary>
                        <pre style="white-space:pre-wrap;font-family:inherit;font-size:.85rem;opacity:.85;margin-top:.3rem;">{{ $r->extrait }}</pre>
                    </details>
                @endif
                @unless($r->vu_at)
                    <div style="margin-top:.4rem;"><x-filament::button size="xs" color="gray" wire:click="marquerVu({{ $r->id }})">Marquer comme traité</x-filament::button></div>
                @endunless
            </div>
        @empty
            <p style="font-size:.9rem;opacity:.75;">Aucune réponse pour l’instant.</p>
        @endforelse
    </x-filament::section>

    {{-- Prospection --}}
    <x-filament::section>
        <x-slot name="heading">🤝 Prospection</x-slot>
        <div style="display:grid;gap:.4rem;font-size:.9rem;">
            <div>{{ $p['pause'] ? '⏸️ En pause' : '▶️ En marche' }} · <strong>{{ $p['envoyes'] }}/{{ $p['plafond'] }}</strong> mails aujourd’hui (montée en douceur : 20 par jour la 1ʳᵉ semaine, 40 la 2ᵉ, puis {{ config('agent.plafond_max') }})</div>
            <div>{{ $oui($p['boite']) }} Boîte d’envoi no-reply@ · {{ $oui($p['lecture']) }} Lecture des réponses sur contact@ · {{ $oui($p['ia']) }} IA ({{ number_format($p['cout'], 2, ',', '') }} $ / {{ number_format($p['budget'], 0) }} $ ce mois)</div>
            <div>Organismes publics : <strong>{{ $p['organismes'] }}</strong> repérés, {{ $p['organismes_email'] }} avec e-mail, {{ $p['organismes_contactes'] }} contactés
                @if($p['par_type']) <span style="opacity:.7;">({{ collect($p['par_type'])->map(fn ($n, $t) => ($types[$t] ?? $t) . ' ' . $n)->implode(', ') }})</span>@endif
            </div>
        </div>
        <div style="margin-top:.8rem;display:flex;gap:.5rem;flex-wrap:wrap;">
            <x-filament::button size="sm" :color="$p['pause'] ? 'success' : 'warning'" wire:click="basculerPause">{{ $p['pause'] ? '▶️ Relancer la prospection' : '⏸️ Mettre en pause' }}</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="basculerApercu">{{ $apercu ? 'Masquer' : '👀 Voir' }} le mail aux collectivités</x-filament::button>
            <x-filament::button size="sm" :color="$p['rse_valide'] ? 'gray' : 'success'" wire:click="validerRse"
                wire:confirm="{{ $p['rse_valide'] ? 'Arrêter les envois aux collectivités ?' : 'Valider ce modèle ? L’agent commencera à écrire aux collectivités (dans la limite du jour).' }}">
                {{ $p['rse_valide'] ? '⏹️ Arrêter les envois aux collectivités' : '✅ Valider le mail et écrire aux collectivités' }}
            </x-filament::button>
        </div>
        @if(! $p['rse_valide'])
            <p style="font-size:.85rem;margin-top:.6rem;opacity:.8;">Les collectivités ne sont pas contactées tant que tu n’as pas validé le modèle. En attendant, l’agent écrit seulement aux commerces (points relais).</p>
        @endif
        @if($apercu)
            <p style="font-size:.8rem;opacity:.7;margin-top:.8rem;">Exemple pour : {{ $exemple?->nom }} (le texte s’adapte : CCAS, intercommunalité, mairie…).</p>
            <iframe srcdoc="{{ $apercu }}" style="width:100%;height:900px;border:1px solid rgba(148,163,184,.3);border-radius:.8rem;margin-top:.4rem;background:#fff;"></iframe>
        @endif
    </x-filament::section>
@script
<script>
(() => {
    const zone = document.getElementById('ceo-voix');
    if (!zone) return;
    const $ = (sel) => zone.querySelector(sel);
    const micro = $('[data-micro]'), etat = $('[data-etat]'), aide = $('[data-aide]'), fil = $('[data-fil]'), texte = $('[data-texte]');
    const Reco = window.SpeechRecognition || window.webkitSpeechRecognition;
    const synthese = 'speechSynthesis' in window ? window.speechSynthesis : null;
    let voix = null, ecoute = null, enEcoute = false, occupe = false;

    function dire(t) { etat.textContent = t; }
    if (!Reco) aide.textContent = 'Ici le micro direct n’est pas disponible : touche la zone de texte puis le micro de ton clavier pour dicter.';
    else if (!synthese) aide.textContent = 'Ce navigateur ne sait pas lire à voix haute : les réponses s’affichent à l’écran.';

    // Voix française du téléphone (gratuite).
    function choisirVoix() {
        if (!synthese) return;
        const toutes = synthese.getVoices();
        voix = toutes.find(v => /^fr[-_]FR/i.test(v.lang) && /Thomas|Am[ée]lie|Audrey|Google|Daniel/i.test(v.name))
            || toutes.find(v => /^fr/i.test(v.lang)) || null;
    }
    if (synthese) { choisirVoix(); synthese.addEventListener?.('voiceschanged', choisirVoix); }

    // iPhone : la voix doit être « débloquée » par un geste de l'utilisateur.
    let debloquee = false;
    function debloquer() {
        if (!synthese || debloquee) return;
        debloquee = true;
        try { const u = new SpeechSynthesisUtterance(' '); u.volume = 0; synthese.speak(u); } catch (e) {}
    }

    // Lit le texte ; la promesse se termine quand la voix a fini (pour réécouter ensuite).
    function parler(t) {
        return new Promise((fini) => {
            if (!synthese || !t) { fini(); return; }
            synthese.cancel();
            const morceaux = (t.match(/[^.!?…]+[.!?…]*/g) || [t]).map(m => m.trim()).filter(Boolean);
            if (!morceaux.length) { fini(); return; }
            let termine = false;
            const finir = () => { if (!termine) { termine = true; clearTimeout(garde); fini(); } };
            // Filet de sécurité : certains téléphones n'annoncent pas la fin de la lecture.
            const garde = setTimeout(finir, 4000 + t.length * 90);
            morceaux.forEach((m, i) => {
                const u = new SpeechSynthesisUtterance(m);
                u.lang = 'fr-FR';
                if (voix) u.voice = voix;
                u.rate = 1.03;
                if (i === morceaux.length - 1) { u.onend = finir; u.onerror = finir; }
                synthese.speak(u);
            });
        });
    }

    function bulle(qui, t) {
        const b = document.createElement('div');
        b.textContent = t;
        b.style.cssText = 'max-width:88%;padding:.6rem .85rem;border-radius:1rem;font-size:.92rem;line-height:1.5;white-space:pre-line;'
            + (qui === 'moi'
                ? 'align-self:flex-end;background:#0f766e;color:#fff;border-bottom-right-radius:.3rem;'
                : 'align-self:flex-start;background:rgba(148,163,184,.15);border-bottom-left-radius:.3rem;');
        fil.appendChild(b);
        b.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return b;
    }

    // Mode conversation : après sa réponse, le micro se rouvre tout seul.
    let conversation = false, silences = 0;

    async function poser(q) {
        q = (q || '').trim();
        if (!q || occupe) return;
        debloquer();
        occupe = true;
        bulle('moi', q);
        texte.value = '';
        dire('Je réfléchis…');
        micro.style.opacity = '.5';
        let r = null;
        try {
            r = await $wire.demander(q);
            bulle('ceo', r);
        } catch (e) {
            bulle('ceo', 'La connexion a coupé. Réessaie dans un instant.');
        } finally {
            occupe = false;
            micro.style.opacity = '1';
        }
        if (r) {
            dire(conversation ? 'Je te réponds…' : 'Touche le micro pour une autre question.');
            await parler(r);
        }
        if (conversation && Reco) ecouter(); else dire('Touche le micro pour une autre question.');
    }

    function arreterEcoute() {
        enEcoute = false;
        micro.style.transform = 'scale(1)';
        micro.style.background = '#0f766e';
    }

    function ecouter() {
        if (!Reco || enEcoute) return;
        ecoute = new Reco();
        ecoute.lang = 'fr-FR';
        ecoute.interimResults = true;
        ecoute.maxAlternatives = 1;
        let final = '';
        ecoute.onresult = (ev) => {
            let provisoire = '';
            for (let i = ev.resultIndex; i < ev.results.length; i++) {
                if (ev.results[i].isFinal) final += ev.results[i][0].transcript; else provisoire += ev.results[i][0].transcript;
            }
            texte.value = (final + ' ' + provisoire).trim();
        };
        let erreur = null;
        ecoute.onerror = (ev) => { erreur = ev.error; };
        ecoute.onend = () => {
            const q = (final || texte.value).trim();
            arreterEcoute();
            if (erreur === 'not-allowed' || erreur === 'service-not-allowed') {
                conversation = false;
                dire('Le micro est bloqué : autorise-le dans les réglages du navigateur, ou écris ta question.');
                return;
            }
            if (q) { silences = 0; poser(q); return; }
            // Rien entendu : on réessaie une fois, puis on se met en pause.
            if (conversation && ++silences < 2) { ecouter(); return; }
            conversation = false;
            silences = 0;
            dire('Je me mets en pause. Touche le micro pour reprendre.');
        };
        try {
            ecoute.start();
            enEcoute = true;
            micro.style.transform = 'scale(1.08)';
            micro.style.background = '#dc2626';
            dire(conversation ? 'Je t’écoute… (parle, ou touche le micro pour arrêter)' : 'Je t’écoute…');
        } catch (e) {
            arreterEcoute();
            conversation = false;
            dire('Touche le micro pour reprendre.');
        }
    }

    micro.addEventListener('click', () => {
        debloquer();
        if (!Reco) { synthese?.cancel(); texte.focus(); dire('Touche le micro de ton clavier et dicte ta question.'); return; }
        // Pendant l'écoute : toucher le micro arrête la conversation.
        if (enEcoute) { conversation = false; ecoute?.stop(); return; }
        synthese?.cancel();
        conversation = true;
        silences = 0;
        ecouter();
    });

    $('[data-clavier]').addEventListener('submit', (e) => { e.preventDefault(); poser(texte.value); });
    zone.querySelectorAll('[data-question]').forEach((b) => b.addEventListener('click', () => poser(b.dataset.question)));
    $('[data-resume-lire]').addEventListener('click', () => {
        debloquer();
        if (!synthese) { dire('Ce navigateur ne sait pas lire à voix haute.'); return; }
        parler(zone.dataset.resume);
        dire('Je te lis le résumé du jour…');
    });
    $('[data-stop]').addEventListener('click', () => { conversation = false; synthese?.cancel(); ecoute?.abort(); arreterEcoute(); dire('Touche le micro et parle.'); });
})();
</script>
@endscript
</x-filament-panels::page>
