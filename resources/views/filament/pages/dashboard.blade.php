<x-filament-panels::page>
    <style>
        .swp-grid{display:grid;gap:1rem}
        .swp-4{grid-template-columns:repeat(4,minmax(0,1fr))}
        .swp-3{grid-template-columns:repeat(3,minmax(0,1fr))}
        .swp-2{grid-template-columns:repeat(2,minmax(0,1fr))}
        @media(max-width:1024px){.swp-4,.swp-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:640px){.swp-4,.swp-3,.swp-2{grid-template-columns:1fr}}
        .swp-klabel{font-size:.78rem;font-weight:700;opacity:.6}
        .swp-kvalue{font-size:2rem;font-weight:800;line-height:1.1;margin-top:.35rem}
        .swp-ksub{font-size:.75rem;opacity:.55;margin-top:.4rem}
        .swp-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.7rem 0;border-bottom:1px solid rgba(148,163,184,.2)}
        .swp-row:last-child{border-bottom:0}
        .swp-bar{height:.55rem;border-radius:999px;background:rgba(148,163,184,.22);overflow:hidden;margin-top:.4rem}
        .swp-bar>div{height:100%;background:#0d9488;border-radius:999px}
        .swp-table{width:100%;border-collapse:collapse;font-size:.875rem}
        .swp-table th{text-align:left;opacity:.55;font-size:.72rem;font-weight:700;padding:.6rem;border-bottom:1px solid rgba(148,163,184,.25)}
        .swp-table td{padding:.7rem .6rem;border-bottom:1px solid rgba(148,163,184,.15)}
        .swp-trunc{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .swp-section-title{font-size:1.05rem;font-weight:800;margin-bottom:.75rem;display:flex;flex-wrap:wrap;align-items:baseline;gap:.5rem}
        .swp-section-title small{font-size:.75rem;font-weight:600;opacity:.55}
        .swp-badge-live{font-size:.68rem;font-weight:700;padding:.1rem .5rem;border-radius:999px;background:rgba(16,185,129,.15);color:#0d9488}
    </style>

    <div style="display:flex;flex-direction:column;gap:1.5rem;">

        {{-- Filtre période --}}
        <x-filament::section>
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;">
                <div>
                    <div class="swp-klabel">Période affichée</div>
                    <div style="font-size:1.35rem;font-weight:800;margin-top:.15rem;">{{ $periodLabel }}</div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
                    @foreach($periods as $key => $label)
                        <x-filament::button
                            tag="a"
                            :href="request()->fullUrlWithQuery(['period' => $key])"
                            :color="$period === $key ? 'primary' : 'gray'"
                            size="sm">
                            {{ $label }}
                        </x-filament::button>
                    @endforeach
                </div>
            </div>
        </x-filament::section>

        {{-- État actuel : indépendant de la période --}}
        <div>
            <div class="swp-section-title">
                🟢 État actuel de la marketplace
                <span class="swp-badge-live">temps réel</span>
                <small>indépendant du filtre de période</small>
            </div>
            <div class="swp-grid swp-4">
                <x-filament::section>
                    <div class="swp-klabel">👥 Membres inscrits</div>
                    <div class="swp-kvalue">{{ number_format($membersTotalCount, 0, ',', ' ') }}</div>
                    <div class="swp-ksub">Total depuis le lancement</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="swp-klabel">📦 Annonces en ligne</div>
                    <div class="swp-kvalue">{{ number_format($publishedListingsCount, 0, ',', ' ') }}</div>
                    <div class="swp-ksub">Actuellement visibles</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="swp-klabel">🗂️ Total annonces</div>
                    <div class="swp-kvalue">{{ number_format($totalListingsCount, 0, ',', ' ') }}</div>
                    <div class="swp-ksub">En ligne, brouillons et vendues</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="swp-klabel">👀 Vues cumulées</div>
                    <div class="swp-kvalue">{{ number_format($viewsCount, 0, ',', ' ') }}</div>
                    <div class="swp-ksub">Sur l'ensemble des annonces</div>
                </x-filament::section>
            </div>
        </div>

        {{-- Sur la période sélectionnée --}}
        <div>
            <div class="swp-section-title">
                📊 Sur la période : {{ $periodLabel }}
                <small>ces chiffres suivent le filtre ci-dessus</small>
            </div>
            <div class="swp-grid swp-4">
                <x-filament::section>
                    <div class="swp-klabel">🆕 Nouveaux membres</div>
                    <div class="swp-kvalue">{{ number_format($usersCount, 0, ',', ' ') }}</div>
                    <div class="swp-ksub">+{{ $todayUsersCount }} aujourd’hui</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="swp-klabel">📝 Nouvelles annonces</div>
                    <div class="swp-kvalue">{{ number_format($totalListingsCount, 0, ',', ' ') }}</div>
                    <div class="swp-ksub">+{{ $todayListingsCount }} aujourd’hui</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="swp-klabel">💬 Messages</div>
                    <div class="swp-kvalue">{{ number_format($messagesCount, 0, ',', ' ') }}</div>
                    <div class="swp-ksub">+{{ $todayMessagesCount }} aujourd’hui</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="swp-klabel">💳 Transactions payées</div>
                    <div class="swp-kvalue">{{ number_format($paidTransactionsCount, 0, ',', ' ') }}</div>
                    <div class="swp-ksub">{{ number_format($transactionsCount, 0, ',', ' ') }} transactions</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="swp-klabel">💰 Volume validé</div>
                    <div class="swp-kvalue">{{ number_format($completedAmount, 0, ',', ' ') }} €</div>
                    <div class="swp-ksub">Ventes terminées</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="swp-klabel">🏝️ Revenu plateforme</div>
                    <div class="swp-kvalue" style="color:#0d9488;">{{ number_format($platformRevenueAmount ?? (($commissionAmount ?? 0) + ($buyerProtectionAmount ?? 0)), 0, ',', ' ') }} €</div>
                    <div class="swp-ksub">Commission + protection</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="swp-klabel">🛡️ Protection acheteur</div>
                    <div class="swp-kvalue">{{ number_format($buyerProtectionAmount ?? 0, 0, ',', ' ') }} €</div>
                    <div class="swp-ksub">Frais collectés</div>
                </x-filament::section>
            </div>
        </div>

        {{-- Répartition territoire + Top annonces --}}
        <div class="swp-grid swp-2">
            <x-filament::section>
                <x-slot name="heading">🌍 Répartition par île</x-slot>
                <x-slot name="description">Annonces publiées par territoire.</x-slot>

                @forelse($territories as $row)
                    @php $percent = $publishedListingsCount > 0 ? round(($row->total / $publishedListingsCount) * 100) : 0; @endphp
                    <div style="margin-bottom:.9rem;">
                        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:.875rem;">
                            <span>{{ $row->territoire ?: 'Non renseigné' }}</span>
                            <span>{{ $row->total }} · {{ $percent }}%</span>
                        </div>
                        <div class="swp-bar"><div style="width:{{ $percent }}%"></div></div>
                    </div>
                @empty
                    <p style="opacity:.6;">Aucune donnée.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">🔥 Annonces les plus vues</x-slot>

                @forelse($topListings as $listing)
                    <a href="{{ route('listings.show', $listing) }}" target="_blank" class="swp-row" style="text-decoration:none;color:inherit;">
                        <div class="swp-trunc">
                            <div style="font-weight:700;" class="swp-trunc">{{ $listing->title }}</div>
                            <div style="opacity:.55;font-size:.8rem;">{{ $listing->user->name ?? 'Utilisateur' }}</div>
                        </div>
                        <div style="font-weight:800;white-space:nowrap;">👀 {{ (int) $listing->views_count }}</div>
                    </a>
                @empty
                    <p style="opacity:.6;">Aucune annonce.</p>
                @endforelse
            </x-filament::section>
        </div>

        {{-- Analytics site --}}
        @php
            $analyticsTableExists = \Illuminate\Support\Facades\Schema::hasTable('analytics_events');

            $analyticsPeriod = request('analytics_period', '30d');
            $analyticsPeriodLabels = [
                'today' => 'Aujourd’hui',
                'week' => 'Cette semaine',
                '15d' => '15 derniers jours',
                '30d' => '30 derniers jours',
                '3m' => '3 mois',
                'all' => 'Depuis le début',
            ];
            $analyticsPeriodLabel = $analyticsPeriodLabels[$analyticsPeriod] ?? '30 derniers jours';

            $analyticsStart = match ($analyticsPeriod) {
                'today' => today(),
                'week' => now()->startOfWeek(),
                '15d' => now()->subDays(15),
                '3m' => now()->subMonths(3),
                'all' => null,
                default => now()->subDays(30),
            };

            $analyticsViewsCount = 0;
            $analyticsConnectedViewsCount = 0;
            $analyticsTopPages = collect();
            $analyticsRecentConnectedEvents = collect();

            if ($analyticsTableExists) {
                $analyticsBaseQuery = \App\Models\AnalyticsEvent::query()
                    ->when($analyticsStart, fn ($q) => $q->where('created_at', '>=', $analyticsStart));

                $analyticsViewsCount = (clone $analyticsBaseQuery)->count();

                $analyticsConnectedViewsCount = (clone $analyticsBaseQuery)
                    ->whereNotNull('user_id')
                    ->count();

                $analyticsTopPages = (clone $analyticsBaseQuery)
                    ->selectRaw('COALESCE(page_name, path) as page_label, path, COUNT(*) as total_views, MAX(created_at) as last_seen_at')
                    ->groupBy('page_label', 'path')
                    ->orderByDesc('total_views')
                    ->limit(8)
                    ->get();

                $analyticsRecentConnectedEvents = (clone $analyticsBaseQuery)
                    ->with('user')
                    ->whereNotNull('user_id')
                    ->latest('created_at')
                    ->paginate(10, ['*'], 'activite')
                    ->withQueryString();
            }
        @endphp

        <x-filament::section>
            <x-slot name="heading">📊 Analytics site</x-slot>
            <x-slot name="description">Vues de pages · {{ $analyticsPeriodLabel }}</x-slot>

            <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1.25rem;">
                @foreach($analyticsPeriodLabels as $key => $label)
                    <x-filament::button
                        tag="a"
                        :href="request()->fullUrlWithQuery(['analytics_period' => $key])"
                        :color="$analyticsPeriod === $key ? 'primary' : 'gray'"
                        size="sm">
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>

            <div class="swp-grid swp-3" style="margin-bottom:1.5rem;">
                <div style="border-radius:1rem;background:rgba(148,163,184,.1);padding:1rem;">
                    <div class="swp-klabel">👁️ Vues pages</div>
                    <div class="swp-kvalue">{{ number_format($analyticsViewsCount, 0, ',', ' ') }}</div>
                </div>
                <div style="border-radius:1rem;background:rgba(13,148,136,.12);padding:1rem;">
                    <div class="swp-klabel">👤 Vues connectées</div>
                    <div class="swp-kvalue">{{ number_format($analyticsConnectedViewsCount, 0, ',', ' ') }}</div>
                </div>
                <div style="border-radius:1rem;background:rgba(59,130,246,.12);padding:1rem;">
                    <div class="swp-klabel">📄 Pages suivies</div>
                    <div class="swp-kvalue">{{ number_format($analyticsTopPages->count(), 0, ',', ' ') }}</div>
                </div>
            </div>

            <div class="swp-grid swp-2">
                <div>
                    <h3 style="font-size:1rem;font-weight:800;margin-bottom:.5rem;">Pages les plus vues</h3>
                    @forelse($analyticsTopPages as $page)
                        <div class="swp-row">
                            <div class="swp-trunc">
                                <div style="font-weight:700;" class="swp-trunc">{{ $page->page_label ?: $page->path }}</div>
                                <div style="font-size:.8rem;opacity:.55;" class="swp-trunc">{{ $page->path }}</div>
                            </div>
                            <div style="font-weight:800;color:#0d9488;white-space:nowrap;">{{ $page->total_views }} vues</div>
                        </div>
                    @empty
                        <p style="opacity:.6;">Aucune vue enregistrée pour le moment.</p>
                    @endforelse
                </div>

                <div>
                    <h3 style="font-size:1rem;font-weight:800;margin-bottom:.5rem;">Activité des membres connectés</h3>
                    @forelse($analyticsRecentConnectedEvents as $event)
                        <div class="swp-row">
                            <div class="swp-trunc">
                                <div style="font-weight:700;" class="swp-trunc">{{ $event->user?->name ?? $event->user?->email ?? 'Membre connecté' }}</div>
                                <div style="font-size:.8rem;opacity:.55;" class="swp-trunc">{{ $event->page_name ?: $event->path }}</div>
                            </div>
                            <div style="font-size:.78rem;font-weight:700;opacity:.55;white-space:nowrap;">{{ optional($event->created_at)->diffForHumans() }}</div>
                        </div>
                    @empty
                        <p style="opacity:.6;">Aucune activité connectée pour le moment.</p>
                    @endforelse

                    @if($analyticsRecentConnectedEvents->hasPages())
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:1rem;flex-wrap:wrap;">
                            <span style="font-size:.78rem;opacity:.6;">
                                Page {{ $analyticsRecentConnectedEvents->currentPage() }} / {{ $analyticsRecentConnectedEvents->lastPage() }}
                                · {{ $analyticsRecentConnectedEvents->total() }} activités
                            </span>
                            <div style="display:flex;gap:.4rem;">
                                @if($analyticsRecentConnectedEvents->previousPageUrl())
                                    <x-filament::button tag="a" size="sm" color="gray" :href="$analyticsRecentConnectedEvents->previousPageUrl()">← Précédent</x-filament::button>
                                @endif
                                @if($analyticsRecentConnectedEvents->nextPageUrl())
                                    <x-filament::button tag="a" size="sm" color="gray" :href="$analyticsRecentConnectedEvents->nextPageUrl()">Suivant →</x-filament::button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>

        {{-- Dernières transactions --}}
        <x-filament::section>
            <x-slot name="heading">🧾 Dernières transactions</x-slot>

            <div style="overflow-x:auto;">
                <table class="swp-table">
                    <thead>
                        <tr>
                            <th>Annonce</th>
                            <th>Acheteur</th>
                            <th>Vendeur</th>
                            <th>Statut</th>
                            <th style="text-align:right;">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTransactions as $transaction)
                            @php
                                $statusLabels = ['pending'=>'En attente','paid'=>'Payée','completed'=>'Terminée','cancelled'=>'Annulée','refunded'=>'Remboursée'];
                                $statusColors = ['pending'=>'warning','paid'=>'info','completed'=>'success','cancelled'=>'gray','refunded'=>'danger'];
                            @endphp
                            <tr>
                                <td style="font-weight:700;">{{ $transaction->listing->title ?? 'Annonce supprimée' }}</td>
                                <td>{{ $transaction->buyer->name ?? '—' }}</td>
                                <td>{{ $transaction->seller->name ?? '—' }}</td>
                                <td>
                                    <x-filament::badge :color="$statusColors[$transaction->status] ?? 'gray'">
                                        {{ $statusLabels[$transaction->status] ?? $transaction->status }}
                                    </x-filament::badge>
                                </td>
                                <td style="text-align:right;font-weight:800;">{{ number_format($transaction->amount, 2, ',', ' ') }} €</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align:center;opacity:.6;padding:1.5rem;">Aucune transaction.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
