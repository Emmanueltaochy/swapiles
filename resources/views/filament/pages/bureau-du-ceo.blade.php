<x-filament-panels::page>
    @php
        $oui = fn ($b) => $b ? '✅' : '❌';
        $domaines = ['produit' => '🧩 Produit', 'marketing' => '📣 Marketing', 'prospection' => '🤝 Prospection', 'technique' => '🛠️ Technique', 'communaute' => '💬 Communauté'];
        $types = config('agent.rse_types');
    @endphp

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
</x-filament-panels::page>
