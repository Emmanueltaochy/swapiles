<x-filament-panels::page>
    @php
        $oui = fn ($b) => $b ? '✅' : '❌';
        $domaines = ['produit' => '🧩 Produit', 'marketing' => '📣 Marketing', 'prospection' => '🤝 Prospection', 'technique' => '🛠️ Technique', 'communaute' => '💬 Communauté'];
        $types = config('agent.rse_types');
    @endphp

@php
        $aLire = $rapport ? trim($rapport->texte . ' ' . (count($rapport->actions ?? []) ? 'Mes trois actions pour aujourd’hui. ' . collect($rapport->actions)->map(fn ($a) => ['Un', 'Deux', 'Trois'][($a['id'] ?? 1) - 1] . ' : ' . ($a['titre'] ?? '') . '.')->implode(' ') : '')) : 'Pas encore de résumé aujourd’hui. Le premier arrive à sept heures.';
    @endphp
    {{-- À rappeler : les prospects qui ont répondu « intéressé » ou posé une question. --}}
    @if($aRappeler->count())
        <x-filament::section>
            <x-slot name="heading">🔥 À rappeler ({{ $aRappeler->count() }})</x-slot>
            <x-slot name="description">Ils ont répondu à nos mails. Leur message, comment les joindre et une réponse prête à envoyer.</x-slot>
            @foreach($aRappeler as $r)
                @php
                    $pr = $r->prospect();
                    $typeP = $r->prospect_type === 'rse' ? ($types[$pr?->type] ?? 'Organisme public') : 'Commerce · point relais';
                    $sujetMail = preg_match('/^\s*re\s*:/i', (string) $r->sujet) ? $r->sujet : 'Re: ' . ($r->sujet ?: 'Swap’Îles');
                    $mailto = 'mailto:' . $r->expediteur . '?subject=' . rawurlencode($sujetMail) . ($r->brouillon ? '&body=' . rawurlencode($r->brouillon) : '');
                @endphp
                <div style="padding:1rem;border:1px solid rgba(15,118,110,.35);border-radius:1rem;margin-bottom:.8rem;background:rgba(15,118,110,.04);">
                    <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
                        <div>
                            <div style="font-size:.75rem;font-weight:700;color:#0f766e;">{{ \App\Models\AgentReponse::CATEGORIES[$r->categorie] ?? $r->categorie }} · {{ $typeP }}</div>
                            <div style="font-size:1.05rem;font-weight:800;margin-top:.1rem;">{{ $pr?->nom ?? 'Prospect supprimé' }}</div>
                            <div style="font-size:.82rem;opacity:.75;">{{ collect([$pr?->ville, $pr?->territoire])->filter()->implode(' · ') }}</div>
                        </div>
                        <div style="font-size:.8rem;opacity:.7;">Reçu le {{ $r->recu_at?->format('d/m à H:i') }}</div>
                    </div>

                    <div style="margin-top:.6rem;font-size:.92rem;"><strong>En bref :</strong> {{ $r->resume }}</div>

                    <div style="margin-top:.6rem;font-size:.8rem;font-weight:700;opacity:.7;">Son message :</div>
                    <blockquote style="margin:.3rem 0 0;padding:.6rem .8rem;border-left:3px solid #0f766e;background:rgba(148,163,184,.12);border-radius:.4rem;white-space:pre-wrap;font-size:.9rem;">{{ $r->extrait ?: '(message vide : ouvre-le dans la boîte contact@)' }}</blockquote>

                    <div style="margin-top:.7rem;display:flex;gap:.5rem;flex-wrap:wrap;">
                        @if($pr?->telephone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $pr->telephone) }}" style="display:inline-block;border-radius:.7rem;padding:.5rem .8rem;background:#0f766e;color:#fff;font-weight:700;font-size:.85rem;text-decoration:none;">📞 Appeler {{ $pr->telephone }}</a>
                        @endif
                        @if($r->brouillon && ! $r->envoye_at && $p['envoi_contact'])
                            <x-filament::button size="sm" color="success" wire:click="envoyerReponse({{ $r->id }})"
                                wire:confirm="Envoyer la réponse proposée à {{ $r->expediteur }} depuis contact@swapiles.com, avec ta signature ?">📨 Envoyer la réponse depuis contact@</x-filament::button>
                        @endif
                        <a href="{{ $mailto }}" style="display:inline-block;border-radius:.7rem;padding:.5rem .8rem;border:1px solid #0f766e;color:#0f766e;font-weight:700;font-size:.85rem;text-decoration:none;">✉️ Modifier et répondre depuis ma messagerie</a>
                    </div>

                    @if($r->envoye_at)
                        <div style="margin-top:.6rem;font-size:.85rem;font-weight:700;color:#059669;">✅ Réponse envoyée le {{ $r->envoye_at->format('d/m à H:i') }} depuis contact@</div>
                    @elseif($r->brouillon)
                        <div style="margin-top:.6rem;font-size:.8rem;font-weight:700;opacity:.7;">✍️ Réponse proposée (ta signature Swap’Îles est ajoutée à l’envoi) :</div>
                        <pre style="white-space:pre-wrap;font-family:inherit;font-size:.88rem;background:rgba(148,163,184,.12);padding:.7rem;border-radius:.6rem;margin-top:.3rem;">{{ \App\Support\Agent\EnvoiReponse::texte($r) }}</pre>
                        <div style="font-size:.78rem;opacity:.65;margin-top:.2rem;">Tu peux aussi la faire modifier à la voix : « Réponds à {{ $pr?->nom }} et ajoute que je passe jeudi. »</div>
                    @endif
                    @if($pr?->notes)
                        <div style="margin-top:.5rem;font-size:.8rem;opacity:.75;white-space:pre-line;">📝 {{ $pr->notes }}</div>
                    @endif

                    <div style="margin-top:.7rem;display:flex;gap:.4rem;flex-wrap:wrap;">
                        <x-filament::button size="xs" color="success" wire:click="suivre({{ $r->id }}, 'rdv')">📅 RDV pris</x-filament::button>
                        @if($r->prospect_type === 'relais')
                            <x-filament::button size="xs" color="success" wire:click="suivre({{ $r->id }}, 'relais')">🏪 Devenu point relais</x-filament::button>
                        @endif
                        <x-filament::button size="xs" color="gray" wire:click="suivre({{ $r->id }}, 'traite')">✅ Traité</x-filament::button>
                        <x-filament::button size="xs" color="gray" wire:click="suivre({{ $r->id }}, 'refus')">❌ Finalement pas intéressé</x-filament::button>
                    </div>
                </div>
            @endforeach
        </x-filament::section>
    @endif

    {{-- Commerçants qui veulent devenir point relais (formulaire du site). --}}
    @if($candidatures->count())
        <x-filament::section>
            <x-slot name="heading">🏪 Commerces qui veulent devenir point relais ({{ $candidatures->count() }})</x-slot>
            <x-slot name="description">Ils ont rempli le formulaire « Devenir point relais ». Appelle-les, puis crée leur point relais dans Points relais › Commerces candidats.</x-slot>
            @foreach($candidatures as $c)
                @php
                    $corps = "Bonjour" . ($c->contact ? ' ' . $c->contact : '') . ",\n\nMerci beaucoup pour votre demande : nous sommes ravis que " . $c->commerce . " rejoigne Swap’Îles comme point relais" . ($c->ville ? ' à ' . $c->ville : '') . " !\n\n"
                        . "En quelques mots : un membre dépose un colis chez vous, vous vérifiez son code dans l’application, l’acheteur le retire avec son propre code (10 jours au plus). Vous recevez 1 € par colis, versé chaque mois avec un relevé automatique. Rien à installer, pas d’argent à manipuler, sans engagement.\n\n"
                        . "Pour activer votre point relais, il me manque :\n1. l’adresse exacte du commerce ;\n2. vos jours et horaires d’ouverture ;\n3. l’adresse e-mail de votre compte Swap’Îles.\n\nSi vous préférez, je vous appelle 5 minutes : dites-moi le meilleur moment.\n\nBien cordialement,\nEmmanuel Taochy\nFondateur de Swap’Îles · swapiles.com";
                    $lien = 'mailto:' . $c->email . '?subject=' . rawurlencode('Re: Point relais partenaire — ' . $c->commerce . ($c->ville ? ' (' . $c->ville . ')' : '')) . '&body=' . rawurlencode($corps);
                @endphp
                <div style="padding:.9rem 1rem;border:1px solid rgba(217,119,6,.35);border-radius:1rem;margin-bottom:.7rem;background:rgba(217,119,6,.04);">
                    <div style="display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
                        <div>
                            <div style="font-size:1.02rem;font-weight:800;">{{ $c->commerce }}</div>
                            <div style="font-size:.82rem;opacity:.75;">{{ collect([$c->ville, $c->territoire, $c->contact])->filter()->implode(' · ') }}{{ $c->user_id ? ' · a un compte Swap’Îles' : '' }}</div>
                        </div>
                        <div style="font-size:.8rem;opacity:.7;">Reçue le {{ $c->created_at?->format('d/m à H:i') }}</div>
                    </div>
                    <div style="margin-top:.4rem;font-size:.88rem;">Horaires : {{ $c->horaires ?: 'à demander' }}@if($c->message) · « {{ $c->message }} »@endif</div>
                    <div style="margin-top:.6rem;display:flex;gap:.5rem;flex-wrap:wrap;">
                        @if($c->telephone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $c->telephone) }}" style="display:inline-block;border-radius:.7rem;padding:.45rem .8rem;background:#0f766e;color:#fff;font-weight:700;font-size:.85rem;text-decoration:none;">📞 Appeler {{ $c->telephone }}</a>
                        @endif
                        @if($c->email)
                            <a href="{{ $lien }}" style="display:inline-block;border-radius:.7rem;padding:.45rem .8rem;border:1px solid #0f766e;color:#0f766e;font-weight:700;font-size:.85rem;text-decoration:none;">✉️ Envoyer le mail de bienvenue (déjà rédigé)</a>
                        @endif
                        <a href="{{ url('/admin/candidatures-relais') }}" style="display:inline-block;border-radius:.7rem;padding:.45rem .8rem;border:1px solid rgba(148,163,184,.5);font-weight:700;font-size:.85rem;text-decoration:none;color:inherit;">🏪 Créer le point relais</a>
                    </div>
                </div>
            @endforeach
        </x-filament::section>
    @endif

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
                    <button type="button" data-vitesse style="border:1px solid rgba(148,163,184,.4);border-radius:.7rem;padding:.5rem .8rem;font-weight:700;font-size:.85rem;cursor:pointer;background:transparent;">⏩ Vitesse</button>
                    <button type="button" data-stop style="border:1px solid rgba(148,163,184,.4);border-radius:.7rem;padding:.5rem .8rem;font-weight:700;font-size:.85rem;cursor:pointer;background:transparent;">⏹️ Stop</button>
                </div>
            </div>

            <div data-suggestions style="display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.9rem;">
                @foreach(['Comment ça va aujourd’hui ?', 'Qui a répondu à nos mails ?', 'Réponds aux prospects intéressés', 'Qu’est-ce que je fais en priorité ?'] as $q)
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
        <x-slot name="heading">📬 Autres réponses</x-slot>
        <x-slot name="description">Lues sur contact@ (seulement les réponses des prospects contactés) : absences, refus, accusés de réception, et les prospects déjà traités.</x-slot>
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

    // Voix française du téléphone (gratuite) : la plus naturelle disponible
    // (voix « améliorées », « premium », « naturelles » ou Google), sinon la première en français.
    function choisirVoix() {
        if (!synthese) return;
        const fr = synthese.getVoices().filter(v => /^fr/i.test(v.lang));
        const score = (v) => (/natural|neural|premium|enhanced|amélioré|online/i.test(v.name) ? 4 : 0)
            + (/google/i.test(v.name) ? 3 : 0)
            + (/Thomas|Am[ée]lie|Audrey|Denise|Henri|Vivienne|Remy|Rémy/i.test(v.name) ? 2 : 0)
            + (/^fr[-_]FR/i.test(v.lang) ? 1 : 0);
        voix = fr.sort((x, y) => score(y) - score(x))[0] || null;
    }
    // Vitesse de parole : réglable (mémorisée sur ce téléphone).
    let vitesse = 1.2;
    try { vitesse = parseFloat(localStorage.getItem('ceo-vitesse')) || 1.2; } catch (e) {}

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
            // Phrases regroupées par ~220 caractères : assez court pour les téléphones
            // qui coupent les longs textes, assez long pour éviter les pauses robotiques.
            const phrases = (t.match(/[^.!?…]+[.!?…]*/g) || [t]).map(m => m.trim()).filter(Boolean);
            const morceaux = [];
            phrases.forEach((ph) => {
                const dernier = morceaux.length - 1;
                if (dernier >= 0 && (morceaux[dernier] + ' ' + ph).length <= 220) morceaux[dernier] += ' ' + ph;
                else morceaux.push(ph);
            });
            if (!morceaux.length) { fini(); return; }
            let termine = false;
            const finir = () => { if (!termine) { termine = true; clearTimeout(garde); fini(); } };
            // Filet de sécurité : certains téléphones n'annoncent pas la fin de la lecture.
            const garde = setTimeout(finir, 4000 + t.length * 80 / vitesse);
            morceaux.forEach((m, i) => {
                const u = new SpeechSynthesisUtterance(m);
                u.lang = 'fr-FR';
                if (voix) u.voice = voix;
                u.rate = vitesse;
                u.pitch = 1;
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

    // E-mail prêt à partir : on montre exactement ce qui sera envoyé, avec deux boutons.
    function apercuEnvoi(a) {
        const carte = document.createElement('div');
        carte.style.cssText = 'align-self:stretch;border:1px solid #0f766e;border-radius:1rem;padding:.75rem .9rem;font-size:.88rem;background:rgba(15,118,110,.05);';
        const titre = document.createElement('div');
        titre.style.cssText = 'font-weight:800;margin-bottom:.35rem;';
        titre.textContent = '📨 Prêt à partir vers ' + a.nom + ' (' + a.a + ') depuis contact@ :';
        const corps = document.createElement('div');
        corps.style.cssText = 'white-space:pre-wrap;line-height:1.5;';
        corps.textContent = a.texte + '\n\n— ta signature Swap’Îles';
        const boutons = document.createElement('div');
        boutons.style.cssText = 'display:flex;gap:.5rem;margin-top:.6rem;flex-wrap:wrap;';
        [['✅ Oui, envoie', 'oui, envoie', '#0f766e', '#fff'], ['Annuler', 'annule, n’envoie pas', 'transparent', 'inherit']].forEach(([t, phrase, fond, couleur]) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = t;
            b.style.cssText = 'border:1px solid #0f766e;border-radius:.6rem;padding:.4rem .8rem;font-weight:700;cursor:pointer;background:' + fond + ';color:' + couleur + ';';
            b.addEventListener('click', () => { boutons.remove(); poser(phrase); });
            boutons.appendChild(b);
        });
        carte.append(titre, corps, boutons);
        fil.appendChild(carte);
        carte.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
            const res = await $wire.demander(q);
            r = (res && res.texte) || '';
            bulle('ceo', r);
            if (res && res.apercu) apercuEnvoi(res.apercu);
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
    const libellesVitesse = { 1: 'normale', 1.2: 'rapide', 1.4: 'très rapide' };
    const boutonVitesse = $('[data-vitesse]');
    const afficherVitesse = () => { boutonVitesse.textContent = '⏩ Vitesse : ' + (libellesVitesse[vitesse] || 'rapide'); };
    afficherVitesse();
    boutonVitesse.addEventListener('click', () => {
        vitesse = vitesse >= 1.4 ? 1 : (vitesse >= 1.2 ? 1.4 : 1.2);
        try { localStorage.setItem('ceo-vitesse', String(vitesse)); } catch (e) {}
        afficherVitesse();
        debloquer();
        parler('Voilà, je parle comme ça maintenant.');
    });
    $('[data-stop]').addEventListener('click', () => { conversation = false; synthese?.cancel(); ecoute?.abort(); arreterEcoute(); dire('Touche le micro et parle.'); });
})();
</script>
@endscript
</x-filament-panels::page>
