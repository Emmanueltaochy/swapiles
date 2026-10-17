<x-filament-widgets::widget>
    @php
        $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
        $pct = fn ($a, $b) => $b > 0 ? round($a / $b * 100) . ' %' : '—';
        $maxIle = max(1, ...(array_column($c['par_ile'], 'membres') ?: [1]));
        $maxConnu = max(1, ...(array_values($c['comment_connu']) ?: [1]));
        $drapeaux = ['La Réunion' => '🇷🇪', 'Martinique' => '🇲🇶', 'Guadeloupe' => '🇬🇵', 'Guyane' => '🇬🇫', 'La Guyane' => '🇬🇫', 'Mayotte' => '🇾🇹'];
    @endphp

    <style>
        .mi-grille{display:grid;gap:.75rem;grid-template-columns:repeat(4,minmax(0,1fr));}
        @media(max-width:1024px){.mi-grille{grid-template-columns:repeat(2,minmax(0,1fr));}}
        .mi-kpi{border-radius:.9rem;background:rgba(148,163,184,.10);padding:.8rem .95rem;min-width:0;}
        .mi-kpi .l{font-size:.74rem;font-weight:700;opacity:.65;}
        .mi-kpi .v{font-size:1.4rem;font-weight:900;margin-top:.15rem;font-variant-numeric:tabular-nums;}
        .mi-kpi .s{font-size:.72rem;font-weight:600;opacity:.6;margin-top:.1rem;}
        .mi-deux{display:grid;gap:1rem;grid-template-columns:repeat(2,minmax(0,1fr));}
        @media(max-width:768px){.mi-deux{grid-template-columns:1fr;}}
        .mi-ligne{display:flex;align-items:center;gap:.6rem;margin:.45rem 0;font-size:.85rem;}
        .mi-ligne .nm{width:40%;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .mi-piste{flex:1;height:.6rem;border-radius:1rem;background:rgba(148,163,184,.18);overflow:hidden;}
        .mi-piste div{height:100%;border-radius:1rem;}
        .mi-val{min-width:4.5rem;text-align:right;font-weight:800;font-variant-numeric:tabular-nums;}
        .mi-titre{font-weight:800;font-size:.95rem;margin-bottom:.3rem;}
        .mi-note{font-size:.72rem;opacity:.6;}
    </style>

    <div style="display:flex;flex-direction:column;gap:1rem;">
        <div class="mi-grille">
            <div class="mi-kpi" style="background:rgba(13,148,136,.14);">
                <div class="l">👥 Membres</div>
                <div class="v">{{ $n($c['total']) }}</div>
                <div class="s">+{{ $n($c['nouveaux_7j']) }} en 7 j · +{{ $n($c['nouveaux_30j']) }} en 30 j</div>
            </div>
            <div class="mi-kpi">
                <div class="l">🟢 Actifs ce mois-ci</div>
                <div class="v">{{ $n($c['actifs_30j']) }}</div>
                <div class="s">{{ $pct($c['actifs_30j'], $c['total']) }} des membres, connectés en 30 j</div>
            </div>
            <div class="mi-kpi">
                <div class="l">🛍️ Vendeurs</div>
                <div class="v">{{ $n($c['vendeurs']) }}</div>
                <div class="s">{{ $pct($c['vendeurs'], $c['total']) }} ont une annonce en ligne · {{ $n($c['ont_vendu']) }} ont déjà vendu</div>
            </div>
            <div class="mi-kpi">
                <div class="l">🛒 Acheteurs en ligne</div>
                <div class="v">{{ $n($c['ont_achete']) }}</div>
                <div class="s">{{ $pct($c['ont_achete'], $c['total']) }} ont déjà payé sur Swap’Îles</div>
            </div>
            <div class="mi-kpi">
                <div class="l">💳 Paiement en ligne activé</div>
                <div class="v">{{ $n($c['paiement_actif']) }}</div>
                <div class="s">{{ $pct($c['paiement_actif'], max(1, $c['vendeurs'])) }} des vendeurs</div>
            </div>
            <div class="mi-kpi">
                <div class="l">📱 Ont utilisé l’appli</div>
                <div class="v">{{ $n($c['appli']) }}</div>
                <div class="s">{{ $pct($c['appli'], $c['total']) }} des membres</div>
            </div>
            <div class="mi-kpi">
                <div class="l">🔔 Notifications activées</div>
                <div class="v">{{ $n($c['notifications']) }}</div>
                <div class="s">{{ $pct($c['notifications'], $c['total']) }} des membres joignables</div>
            </div>
            <div class="mi-kpi">
                <div class="l">⚠️ À surveiller</div>
                <div class="v" style="font-size:1rem;line-height:1.5;">
                    ✉️ {{ $n($c['email_non_verifie']) }} · 🌴 {{ $n($c['en_vacances']) }} · 🚫 {{ $n($c['bannis']) }}
                </div>
                <div class="s">e-mail non vérifié · en vacances · bannis · {{ $n($c['pros']) }} pro(s)</div>
            </div>
        </div>

        <div class="mi-deux">
            <x-filament::section compact>
                <div class="mi-titre">🏝️ Membres par île</div>
                @foreach($c['par_ile'] as $l)
                    <div class="mi-ligne">
                        <div class="nm">{{ $drapeaux[$l['ile']] ?? '📍' }} {{ $l['ile'] }}</div>
                        <div class="mi-piste"><div style="width:{{ max(2, round($l['membres'] / $maxIle * 100)) }}%;background:#0d9488;"></div></div>
                        <div class="mi-val">{{ $n($l['membres']) }}<span style="font-weight:600;opacity:.6;font-size:.72rem;"> · {{ $pct($l['membres'], $c['total']) }}</span></div>
                    </div>
                    @if($l['nouveaux'] > 0)
                        <div class="mi-note" style="margin:-.3rem 0 .3rem;padding-left:40%;">+{{ $n($l['nouveaux']) }} ce mois-ci</div>
                    @endif
                @endforeach
            </x-filament::section>

            <x-filament::section compact>
                <div class="mi-titre">📣 Comment ils nous ont connus</div>
                @forelse($c['comment_connu'] as $source => $nb)
                    <div class="mi-ligne">
                        <div class="nm">{{ $source }}</div>
                        <div class="mi-piste"><div style="width:{{ max(2, round($nb / $maxConnu * 100)) }}%;background:#6366f1;"></div></div>
                        <div class="mi-val">{{ $n($nb) }}<span style="font-weight:600;opacity:.6;font-size:.72rem;"> · {{ $pct($nb, $c['comment_connu_repondu']) }}</span></div>
                    </div>
                @empty
                    <p class="mi-note">Pas encore de réponse.</p>
                @endforelse
                <p class="mi-note" style="margin-top:.4rem;">Réponse facultative à l’inscription : {{ $n($c['comment_connu_repondu']) }} membre(s) ont répondu.</p>
            </x-filament::section>
        </div>
    </div>
</x-filament-widgets::widget>
