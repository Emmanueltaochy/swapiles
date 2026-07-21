<x-filament-panels::page>
    <style>
        .swp-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
        .swp-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
        .swp-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
        .swp-card{background:#111827;border:1px solid #1f2937;border-radius:26px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,.18)}
        .swp-card-light{background:#fff;border:1px solid #e5e7eb;border-radius:26px;padding:22px;box-shadow:0 10px 30px rgba(15,23,42,.06);color:#111827}
        .swp-label{font-size:13px;font-weight:800;color:#94a3b8}
        .swp-value{font-size:34px;font-weight:950;line-height:1.1;margin-top:8px;color:#fff}
        .swp-card-light .swp-value{color:#111827}
        .swp-sub{font-size:12px;color:#94a3b8;margin-top:6px}
        .swp-title{font-size:22px;font-weight:950;margin-bottom:6px}
        .swp-muted{color:#64748b;font-size:14px}
        .swp-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #e5e7eb}
        .swp-row:last-child{border-bottom:0}
        .swp-bar{height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-top:8px}
        .swp-bar > div{height:100%;background:#0f766e;border-radius:999px}
        .swp-table{width:100%;border-collapse:collapse;font-size:14px}
        .swp-table th{text-align:left;color:#64748b;font-size:12px;padding:12px;border-bottom:1px solid #e5e7eb}
        .swp-table td{padding:12px;border-bottom:1px solid #f1f5f9}
        .swp-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;background:#fff;border:1px solid #e5e7eb;border-radius:24px;padding:14px 16px;box-shadow:0 10px 30px rgba(15,23,42,.06)}
        .swp-filter-links{display:flex;flex-wrap:wrap;gap:8px}
        .swp-filter{display:inline-flex;align-items:center;border-radius:999px;padding:9px 13px;font-size:13px;font-weight:900;text-decoration:none;border:1px solid #e5e7eb;color:#334155;background:#fff}
        .swp-filter.active{background:#0f766e;color:#fff;border-color:#0f766e}

        @media(max-width:1100px){.swp-grid-4,.swp-grid-3,.swp-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:700px){.swp-grid-4,.swp-grid-3,.swp-grid-2{grid-template-columns:1fr}}
    </style>

    <div style="display:flex;flex-direction:column;gap:24px;">


        <div class="swp-filters">
            <div>
                <div style="font-size:13px;font-weight:950;color:#64748b;">Période affichée</div>
                <div style="font-size:22px;font-weight:950;color:#111827;">{{ $periodLabel }}</div>
            </div>

            <div class="swp-filter-links">
                @foreach($periods as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['period' => $key]) }}"
                       class="swp-filter {{ $period === $key ? 'active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>


        <div class="swp-grid-4">
            <div class="swp-card">
                <div class="swp-label">👥 Membres</div>
                <div class="swp-value">{{ number_format($usersCount, 0, ',', ' ') }}</div>
                <div class="swp-sub">+{{ $todayUsersCount }} aujourd’hui · filtre : {{ $periodLabel }}</div>
            </div>

            <div class="swp-card">
                <div class="swp-label">📦 Annonces publiées</div>
                <div class="swp-value">{{ number_format($publishedListingsCount, 0, ',', ' ') }}</div>
                <div class="swp-sub">{{ number_format($totalListingsCount, 0, ',', ' ') }} annonces sur la période</div>
            </div>

            <div class="swp-card">
                <div class="swp-label">👀 Vues annonces</div>
                <div class="swp-value">{{ number_format($viewsCount, 0, ',', ' ') }}</div>
                <div class="swp-sub">{{ number_format($favoritesCount, 0, ',', ' ') }} favoris</div>
            </div>

            <div class="swp-card">
                <div class="swp-label">💬 Messages</div>
                <div class="swp-value">{{ number_format($messagesCount, 0, ',', ' ') }}</div>
                <div class="swp-sub">+{{ $todayMessagesCount }} aujourd’hui · filtre : {{ $periodLabel }}</div>
            </div>
        </div>

        <div class="swp-grid-3">
            <div class="swp-card-light">
                <div class="swp-label">💳 Transactions payées</div>
                <div class="swp-value">{{ number_format($paidTransactionsCount, 0, ',', ' ') }}</div>
            </div>

            <div class="swp-card-light">
                <div class="swp-label">💰 Volume validé</div>
                <div class="swp-value">{{ number_format($completedAmount, 0, ',', ' ') }} €</div>
            </div>

            <div class="swp-card-light">
                <div class="swp-label">🏝️ Revenu plateforme</div>
                <div class="swp-value">{{ number_format($platformRevenueAmount ?? (($commissionAmount ?? 0) + ($buyerProtectionAmount ?? 0)), 0, ',', ' ') }} €</div>
                    <div class="text-xs font-bold text-slate-400 mt-2">
                        Commission + protection acheteur
                    </div>
            </div>

            <div class="swp-card">
                <div class="swp-label">🛡️ Protection acheteur</div>
                <div class="swp-value">{{ number_format($buyerProtectionAmount ?? 0, 0, ',', ' ') }} €</div>
                <div class="text-xs font-bold text-slate-400 mt-2">
                    Frais de protection collectés
                </div>
            </div>

        </div>

        <div class="swp-grid-2">
            <div class="swp-card-light">
                <div class="swp-title">
<!-- SWAPILES_ADMIN_ANALYTICS_START -->
@php
    $analyticsTableExists = \Illuminate\Support\Facades\Schema::hasTable('analytics_events');

    $analyticsViewsCount = 0;
    $analyticsConnectedViewsCount = 0;
    $analyticsTopPages = collect();
    $analyticsRecentConnectedEvents = collect();

    if ($analyticsTableExists) {
        $analyticsBaseQuery = \App\Models\AnalyticsEvent::query()
            ->where('created_at', '>=', now()->subDays(30));

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

        $analyticsRecentConnectedEvents = \App\Models\AnalyticsEvent::query()
            ->with('user')
            ->whereNotNull('user_id')
            ->latest('created_at')
            ->limit(12)
            ->get();
    }
@endphp

<div class="swp-card" style="margin-top: 1.5rem;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.25rem;">
        <div>
            <h2 style="font-size:1.7rem; font-weight:900; color:#0f172a; margin:0;">
                📊 Analytics site
            </h2>
            <p style="margin-top:.35rem; color:#64748b; font-weight:700;">
                Suivi des vues pages sur les 30 derniers jours.
            </p>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:1.5rem;">
        <div style="border-radius:24px; background:#f8fafc; border:1px solid #e2e8f0; padding:1rem;">
            <div class="swp-label">👁️ Vues pages</div>
            <div class="swp-value">{{ number_format($analyticsViewsCount, 0, ',', ' ') }}</div>
        </div>

        <div style="border-radius:24px; background:#ecfdf5; border:1px solid #bbf7d0; padding:1rem;">
            <div class="swp-label">👤 Vues connectées</div>
            <div class="swp-value">{{ number_format($analyticsConnectedViewsCount, 0, ',', ' ') }}</div>
        </div>

        <div style="border-radius:24px; background:#eff6ff; border:1px solid #bfdbfe; padding:1rem;">
            <div class="swp-label">📄 Pages suivies</div>
            <div class="swp-value">{{ number_format($analyticsTopPages->count(), 0, ',', ' ') }}</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr; gap:1.5rem;">
        <div>
            <h3 style="font-size:1.15rem; font-weight:900; color:#0f172a; margin-bottom:.75rem;">
                Pages les plus vues
            </h3>

            @forelse($analyticsTopPages as $page)
                <div style="display:flex; justify-content:space-between; gap:1rem; padding:.8rem 0; border-bottom:1px solid #f1f5f9;">
                    <div style="min-width:0;">
                        <div style="font-weight:900; color:#0f172a;">
                            {{ $page->page_label ?: $page->path }}
                        </div>
                        <div style="font-size:.85rem; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            {{ $page->path }}
                        </div>
                    </div>

                    <div style="font-weight:900; color:#0f766e; white-space:nowrap;">
                        {{ $page->total_views }} vues
                    </div>
                </div>
            @empty
                <p style="color:#64748b; font-weight:700;">Aucune vue enregistrée pour le moment.</p>
            @endforelse
        </div>

        <div>
            <h3 style="font-size:1.15rem; font-weight:900; color:#0f172a; margin-bottom:.75rem;">
                Activité des utilisateurs connectés
            </h3>

            @forelse($analyticsRecentConnectedEvents as $event)
                <div style="display:flex; justify-content:space-between; gap:1rem; padding:.8rem 0; border-bottom:1px solid #f1f5f9;">
                    <div style="min-width:0;">
                        <div style="font-weight:900; color:#0f172a;">
                            {{ $event->user?->name ?? $event->user?->email ?? 'Utilisateur connecté' }}
                        </div>
                        <div style="font-size:.85rem; color:#64748b;">
                            {{ $event->page_name ?: $event->path }}
                        </div>
                        <div style="font-size:.78rem; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            {{ $event->path }}
                        </div>
                    </div>

                    <div style="font-size:.8rem; font-weight:800; color:#64748b; white-space:nowrap;">
                        {{ optional($event->created_at)->diffForHumans() }}
                    </div>
                </div>
            @empty
                <p style="color:#64748b; font-weight:700;">Aucune activité connectée pour le moment.</p>
            @endforelse
        </div>
    </div>
</div>
<!-- SWAPILES_ADMIN_ANALYTICS_END -->

🌍 Répartition par territoire</div>
                <div class="swp-muted">Annonces publiées par île.</div>

                <div style="margin-top:18px;">
                    @forelse($territories as $row)
                        @php
                            $percent = $publishedListingsCount > 0 ? round(($row->total / $publishedListingsCount) * 100) : 0;
                        @endphp

                        <div style="margin-bottom:14px;">
                            <div style="display:flex;justify-content:space-between;font-weight:800;font-size:14px;">
                                <span>{{ $row->territoire ?: 'Non renseigné' }}</span>
                                <span>{{ $row->total }} · {{ $percent }}%</span>
                            </div>
                            <div class="swp-bar"><div style="width:{{ $percent }}%"></div></div>
                        </div>
                    @empty
                        <p class="swp-muted">Aucune donnée.</p>
                    @endforelse
                </div>
            </div>

            <div class="swp-card-light">
                <div class="swp-title">🔥 Annonces les plus vues</div>

                <div style="margin-top:12px;">
                    @forelse($topListings as $listing)
                        <a href="{{ route('listings.show', $listing) }}" target="_blank" class="swp-row" style="text-decoration:none;color:inherit;">
                            <div style="min-width:0;">
                                <div style="font-weight:950;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $listing->title }}</div>
                                <div class="swp-muted">{{ $listing->user->name ?? 'Utilisateur' }}</div>
                            </div>
                            <div style="font-weight:950;white-space:nowrap;">👀 {{ (int) $listing->views_count }}</div>
                        </a>
                    @empty
                        <p class="swp-muted">Aucune annonce.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="swp-card-light">
            <div class="swp-title">🧾 Dernières transactions</div>

            <div style="overflow-x:auto;margin-top:12px;">
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
                            <tr>
                                <td style="font-weight:800;">{{ $transaction->listing->title ?? 'Annonce supprimée' }}</td>
                                <td>{{ $transaction->buyer->name ?? '-' }}</td>
                                <td>{{ $transaction->seller->name ?? '-' }}</td>
                                <td>{{ $transaction->status }}</td>
                                <td style="text-align:right;font-weight:950;">{{ number_format($transaction->amount, 0, ',', ' ') }} €</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align:center;color:#64748b;padding:24px;">Aucune transaction.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-filament-panels::page>
