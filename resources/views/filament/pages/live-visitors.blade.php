<x-filament-panels::page>
    <style>
        .live-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:24px}
        .live-card{background:#fff;border:1px solid #e5e7eb;border-radius:28px;padding:24px;box-shadow:0 10px 30px rgba(15,23,42,.06)}
        .live-top{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
        .stat{background:#0f172a;color:white;border-radius:24px;padding:18px;border:1px solid #1e293b}
        .stat span{font-size:12px;color:#94a3b8;font-weight:900}
        .stat strong{display:block;font-size:34px;margin-top:8px}
        .globe-wrap{position:relative;width:min(620px,100%);aspect-ratio:1;margin:auto;perspective:1200px}
        .globe{
            position:absolute;inset:0;border-radius:50%;
            background:
                radial-gradient(circle at 32% 26%, rgba(255,255,255,.75), transparent 6%),
                radial-gradient(circle at 35% 30%, #38bdf8 0, transparent 10%),
                radial-gradient(circle at 35% 35%, #14b8a6 0, #0f766e 28%, #0f172a 70%);
            box-shadow:inset -50px -35px 80px rgba(0,0,0,.55), inset 20px 20px 40px rgba(255,255,255,.12), 0 35px 90px rgba(15,23,42,.35);
            overflow:hidden;
            transform-style:preserve-3d;
            animation:globeFloat 7s ease-in-out infinite;
        }
        .globe:before{
            content:"";position:absolute;inset:7%;border-radius:50%;
            background:
                repeating-linear-gradient(90deg,rgba(255,255,255,.10) 0 1px,transparent 1px 38px),
                repeating-linear-gradient(0deg,rgba(255,255,255,.08) 0 1px,transparent 1px 38px);
            opacity:.45;
            animation:gridSpin 18s linear infinite;
        }
        .globe:after{
            content:"";position:absolute;inset:-8%;border-radius:50%;
            background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.16) 50%,transparent 100%);
            transform:rotate(25deg);
            animation:shine 5s ease-in-out infinite;
        }
        .orbit{position:absolute;inset:-3%;border-radius:50%;border:1px solid rgba(20,184,166,.25);animation:orbit 12s linear infinite}
        .dot{
            position:absolute;width:16px;height:16px;border-radius:50%;background:#ef4444;border:3px solid #fff;
            box-shadow:0 0 0 0 rgba(239,68,68,.7),0 0 20px rgba(239,68,68,.8);
            animation:pulse 1.6s infinite;transform:translate(-50%,-50%);z-index:5;
        }
        .dot small{
            position:absolute;top:18px;left:50%;transform:translateX(-50%);
            background:#111827;color:#fff;padding:5px 9px;border-radius:999px;font-size:11px;white-space:nowrap;font-weight:800;
        }
        .visitor{border:1px solid #e5e7eb;border-radius:20px;padding:14px}
        @keyframes pulse{70%{box-shadow:0 0 0 20px rgba(239,68,68,0),0 0 20px rgba(239,68,68,.8)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0),0 0 20px rgba(239,68,68,.8)}}
        @keyframes globeFloat{0%,100%{transform:rotateX(8deg) rotateY(-12deg) translateY(0)}50%{transform:rotateX(4deg) rotateY(12deg) translateY(-8px)}}
        @keyframes gridSpin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
        @keyframes orbit{from{transform:rotateZ(0)}to{transform:rotateZ(360deg)}}
        @keyframes shine{0%,100%{opacity:.15;transform:translateX(-30%) rotate(25deg)}50%{opacity:.32;transform:translateX(30%) rotate(25deg)}}
        @media(max-width:1100px){.live-grid,.live-top{grid-template-columns:1fr 1fr}}
        @media(max-width:700px){.live-grid,.live-top{grid-template-columns:1fr}}
    </style>

    <div wire:poll.10s style="display:flex;flex-direction:column;gap:24px;">
        @php
            $visits = $this->getLiveVisits();
            $activeCount = $visits->count();
            $connectedCount = $visits->whereNotNull('user_id')->count();
            $mobileCount = $visits->where('device', 'Mobile')->count();
            $desktopCount = $visits->where('device', 'Desktop')->count();
        @endphp

        <div class="live-top">
            <div class="stat"><span>🟢 Visiteurs en ligne</span><strong>{{ $activeCount }}</strong></div>
            <div class="stat"><span>👤 Connectés</span><strong>{{ $connectedCount }}</strong></div>
            <div class="stat"><span>📱 Mobile</span><strong>{{ $mobileCount }}</strong></div>
            <div class="stat"><span>🖥️ Desktop</span><strong>{{ $desktopCount }}</strong></div>
        </div>

        <div class="live-grid">
            <div class="live-card">
                <h2 style="font-size:24px;font-weight:950;margin-bottom:16px;">🌍 Globe en direct</h2>

                <div class="globe-wrap">
                    <div class="orbit"></div>
                    <div class="globe">
                        @foreach($visits as $visit)
                            @php
                                $x = ((float) $visit->lng + 180) / 360 * 100;
                                $y = (90 - (float) $visit->lat) / 180 * 100;
                                $x = max(15, min(85, $x));
                                $y = max(15, min(85, $y));
                            @endphp

                            <span class="dot" style="left:{{ $x }}%;top:{{ $y }}%;">
                                <small>{{ $visit->territoire ?: 'Visiteur' }}</small>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="live-card">
                <h3 style="font-size:22px;font-weight:950;margin-bottom:16px;">Visiteurs actifs ({{ $activeCount }})</h3>

                <div style="display:flex;flex-direction:column;gap:12px;max-height:560px;overflow:auto;padding-right:6px;">
                    @forelse($visits as $visit)
                        <div class="visitor">
                            <div style="display:flex;justify-content:space-between;gap:12px;">
                                <strong>🟢 {{ $visit->territoire ?: 'Non renseigné' }}</strong>
                                <span style="font-size:12px;color:#64748b;">{{ $visit->last_seen_at->diffForHumans() }}</span>
                            </div>
                            <div style="font-size:13px;color:#64748b;margin-top:6px;">
                                {{ $visit->device }} · {{ $visit->path }}
                            </div>
                        </div>
                    @empty
                        <div style="text-align:center;color:#64748b;padding:40px;">
                            Aucun visiteur actif pour le moment.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
