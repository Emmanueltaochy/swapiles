@php
    $carte = 'border-radius:.9rem;padding:.85rem 1rem;background:rgba(148,163,184,.10);';
    $titre = 'font-size:.72rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;opacity:.6;margin-bottom:.4rem;';
    $lien = 'color:rgb(13,148,136);font-weight:700;text-decoration:underline;';
@endphp

<div style="display:flex;flex-direction:column;gap:.9rem;">

    {{-- Le signalement --}}
    <div style="{{ $carte }}">
        <div style="{{ $titre }}">Le signalement</div>
        <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.5rem;">
            <span style="border-radius:999px;padding:.15rem .6rem;font-size:.78rem;font-weight:700;background:rgba(245,158,11,.18);">{{ $motif }}</span>
            <span style="border-radius:999px;padding:.15rem .6rem;font-size:.78rem;font-weight:700;background:{{ $report->status === 'open' ? 'rgba(239,68,68,.18)' : 'rgba(148,163,184,.2)' }};">{{ $etat }}</span>
        </div>
        <div style="font-weight:700;margin-bottom:.35rem;">{{ $cibleLibelle }}</div>
        @if (filled($report->details))
            <div style="white-space:pre-wrap;word-break:break-word;font-size:.95rem;line-height:1.45;">{{ $report->details }}</div>
        @else
            <div style="opacity:.6;font-size:.9rem;">Aucun détail écrit par le membre.</div>
        @endif
        @if ($report->handled_at)
            <div style="font-size:.75rem;opacity:.6;margin-top:.4rem;">Traité le {{ $report->handled_at->format('d/m/Y H:i') }}</div>
        @endif
    </div>

    {{-- Annonce visée --}}
    @if ($annonce)
        <div style="{{ $carte }}">
            <div style="{{ $titre }}">Annonce signalée</div>
            <div style="font-weight:700;">{{ $annonce['titre'] }} — {{ number_format((float) $annonce['prix'], 2, ',', ' ') }} €</div>
            <div style="font-size:.8rem;opacity:.6;margin:.15rem 0 .4rem;">Statut : {{ $annonce['statut'] }}</div>
            @if (filled($annonce['description']))
                <div style="white-space:pre-wrap;word-break:break-word;font-size:.88rem;opacity:.85;">{{ $annonce['description'] }}</div>
            @endif
            <a href="{{ $annonce['url'] }}" target="_blank" style="{{ $lien }}display:inline-block;margin-top:.5rem;">Voir l’annonce sur le site</a>
        </div>
    @endif

    {{-- Message visé --}}
    @if ($messageVise)
        <div style="{{ $carte }}">
            <div style="{{ $titre }}">Message signalé</div>
            <div style="font-size:.78rem;opacity:.7;margin-bottom:.25rem;">
                De {{ $messageVise->sender?->name ?? 'Membre' }} · {{ optional($messageVise->created_at)->format('d/m/Y H:i') }}
            </div>
            <div style="white-space:pre-wrap;word-break:break-word;">{{ $messageVise->body }}</div>
        </div>
    @endif

    {{-- Les deux personnes --}}
    @foreach ([['Membre signalé', $membre, true], ['Signalé par', $auteur, false]] as [$libelle, $p, $estVise])
        <div style="{{ $carte }}{{ $estVise && $p && ($p['signalements_recus'] > 1 || $p['banni']) ? 'border:1px solid rgba(239,68,68,.45);' : '' }}">
            <div style="{{ $titre }}">{{ $libelle }}</div>
            @if ($p)
                <div style="font-weight:800;font-size:1rem;">
                    {{ $p['nom'] }}
                    <span style="font-weight:600;opacity:.6;font-size:.82rem;">· membre n°{{ $p['id'] }}</span>
                    @if ($p['banni']) <span style="color:rgb(239,68,68);font-size:.82rem;">· BANNI</span> @endif
                    @if ($p['pro']) <span style="opacity:.7;font-size:.82rem;">· Pro</span> @endif
                </div>
                <div style="font-size:.85rem;opacity:.8;margin-top:.2rem;word-break:break-all;">
                    {{ $p['email'] }}@if ($p['telephone']) · {{ $p['telephone'] }}@endif
                </div>
                <div style="font-size:.85rem;opacity:.8;">
                    Inscrit le {{ $p['inscrit'] }} ({{ $p['anciennete'] }})@if ($p['ville']) · {{ $p['ville'] }}@endif
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(6.5rem,1fr));gap:.4rem;margin-top:.6rem;">
                    @foreach ([
                        ['Annonces', $p['annonces']],
                        ['Ventes', $p['ventes']],
                        ['Achats', $p['achats']],
                        ['Signalé', $p['signalements_recus'] . ' fois'],
                        ['Msg suspects', $p['messages_signales']],
                    ] as [$k, $v])
                        <div style="border-radius:.6rem;padding:.4rem .5rem;background:rgba(148,163,184,.12);text-align:center;">
                            <div style="font-weight:800;">{{ $v }}</div>
                            <div style="font-size:.7rem;opacity:.65;">{{ $k }}</div>
                        </div>
                    @endforeach
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:.9rem;margin-top:.6rem;font-size:.88rem;">
                    <a href="{{ $p['url_admin'] }}" style="{{ $lien }}">Fiche admin</a>
                    @if ($p['url_profil'])
                        <a href="{{ $p['url_profil'] }}" target="_blank" style="{{ $lien }}">Profil public</a>
                    @endif
                </div>
            @else
                <div style="opacity:.6;">Introuvable (compte supprimé ?).</div>
            @endif
        </div>
    @endforeach

    {{-- Autres signalements --}}
    @if ($autres->isNotEmpty())
        <div style="{{ $carte }}">
            <div style="{{ $titre }}">Autres signalements contre ce membre ({{ $autres->count() }})</div>
            <div style="display:flex;flex-direction:column;gap:.5rem;">
                @foreach ($autres as $a)
                    <div style="font-size:.88rem;border-left:3px solid rgba(239,68,68,.5);padding-left:.6rem;">
                        <div style="font-weight:700;">{{ $a->reasonLabel() }} <span style="font-weight:500;opacity:.6;">· {{ optional($a->created_at)->format('d/m/Y') }} · par {{ $a->reporter?->name ?? '—' }}</span></div>
                        @if (filled($a->details))
                            <div style="white-space:pre-wrap;word-break:break-word;opacity:.85;">{{ $a->details }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Conversation entre les deux --}}
    <div style="{{ $carte }}">
        <div style="{{ $titre }}">Messages entre eux ({{ $messages->count() }})</div>
        @if ($messages->isEmpty())
            <div style="opacity:.6;font-size:.9rem;">Aucun message échangé sur Swap'Îles entre ces deux membres.</div>
        @else
            <div style="display:flex;flex-direction:column;gap:.5rem;max-height:50vh;overflow-y:auto;">
                @foreach ($messages as $msg)
                    @php $deLuiMeme = $msg->sender_id === $membreId; @endphp
                    <div style="display:flex;justify-content:{{ $deLuiMeme ? 'flex-start' : 'flex-end' }};">
                        <div style="max-width:85%;border-radius:.9rem;padding:.5rem .75rem;background:{{ $deLuiMeme ? 'rgba(239,68,68,.12)' : 'rgba(13,148,136,.15)' }};{{ $msg->flagged_at ? 'border:1px solid rgba(239,68,68,.6);' : '' }}">
                            <div style="font-size:.7rem;font-weight:800;opacity:.7;">
                                {{ $msg->sender?->name ?? 'Membre' }}
                                @if ($msg->listing) <span style="font-weight:600;opacity:.7;">· {{ \Illuminate\Support\Str::limit($msg->listing->title, 30) }}</span> @endif
                            </div>
                            <div style="white-space:pre-wrap;word-break:break-word;font-size:.9rem;">{{ $msg->body }}</div>
                            <div style="font-size:.66rem;opacity:.55;text-align:right;">
                                {{ optional($msg->created_at)->format('d/m/Y H:i') }}@if ($msg->flagged_at) · ⚠️ {{ ['payment_forced' => 'paiement hors site', 'acompte' => 'demande d’acompte', 'phone' => 'numéro'][$msg->flag_kind] ?? 'signalé' }}@endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
