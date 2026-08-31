<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300">
    <title>MediaDev Monitor — Estado en vivo</title>
    <style>
        :root {
            --bg: #0b1220; --card: #111a2c; --border: #1e2a44;
            --text: #e2e8f0; --muted: #7a8ba8; --accent: #f59e0b;
            --ok: #10b981; --warn: #f59e0b; --info: #3b82f6;
            --danger: #ef4444; --unknown: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg); color: var(--text);
            font-family: -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh; padding: 24px;
        }
        header { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        h1 { font-size: 1.4rem; }
        h1 span { color: var(--accent); }
        .live { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: .85rem; }
        .live .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ok); animation: pulse 2s infinite; }
        .live.stale .dot { background: var(--danger); animation: none; }
        @keyframes pulse { 50% { opacity: .3; } }
        .summary { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
        .summary .chip { background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 8px 14px; font-size: .85rem; color: var(--muted); }
        .summary .chip b { color: var(--text); font-size: 1.05rem; margin-left: 6px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 14px; }
        .card {
            background: var(--card); border: 1px solid var(--border);
            border-radius: 10px; padding: 16px; border-left: 4px solid var(--unknown);
            transition: border-color .3s;
        }
        .card[data-state="wp-full"]      { border-left-color: var(--ok); }
        .card[data-state="wp-degraded"]  { border-left-color: var(--warn); }
        .card[data-state="non-wp"]       { border-left-color: var(--info); }
        .card[data-state="down"]         { border-left-color: var(--danger); }
        .card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 8px; }
        .card-head a { color: var(--text); text-decoration: none; font-weight: 600; font-size: 1rem; }
        .card-head a:hover { color: var(--accent); }
        .card-head a small { display: block; color: var(--muted); font-weight: 400; font-size: .72rem; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
        .badge { padding: 3px 10px; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
        .badge.wp-full     { background: rgba(16,185,129,.15); color: var(--ok); }
        .badge.wp-degraded { background: rgba(245,158,11,.15); color: var(--warn); }
        .badge.non-wp      { background: rgba(59,130,246,.15); color: var(--info); }
        .badge.down        { background: rgba(239,68,68,.18); color: var(--danger); }
        .badge.unknown     { background: rgba(100,116,139,.15); color: var(--unknown); }
        .metrics { display: flex; gap: 18px; margin: 10px 0; font-size: .8rem; color: var(--muted); flex-wrap: wrap; }
        .metrics b { color: var(--text); display: block; font-size: .95rem; }
        .spark { display: flex; align-items: flex-end; gap: 2px; height: 36px; margin-top: 10px; }
        .spark .bar { flex: 1; min-width: 3px; border-radius: 2px 2px 0 0; background: var(--ok); opacity: .85; }
        .spark .bar.down { background: var(--danger); }
        .spark .bar.mixed { background: var(--warn); }
        .spark .bar.empty { background: var(--border); height: 4px !important; opacity: .4; }
        .spark-label { color: var(--muted); font-size: .68rem; margin-top: 6px; display: flex; justify-content: space-between; }
        footer { margin-top: 26px; text-align: center; color: var(--muted); font-size: .75rem; }
        footer a { color: var(--muted); }
    </style>
</head>
<body>
    <header>
        <h1>MediaDev <span>Monitor</span> — Estado en vivo</h1>
        <div class="live" id="live"><span class="dot"></span><span id="live-text">en vivo</span></div>
    </header>

    <div class="summary" id="summary">
        <span class="chip">Sitios <b id="sum-total">{{ count($sites) }}</b></span>
        <span class="chip">OK <b id="sum-ok">–</b></span>
        <span class="chip">Degradados <b id="sum-degraded">–</b></span>
        <span class="chip">Caídos <b id="sum-down">–</b></span>
    </div>

    <div class="grid" id="grid">
        @foreach ($sites as $site)
            <div class="card" data-state="{{ $site['state'] }}" data-site-id="{{ $site['id'] }}">
                <div class="card-head">
                    <a href="{{ $site['url'] }}" target="_blank" rel="noopener noreferrer">
                        {{ $site['name'] }}
                        <small>{{ parse_url($site['url'], PHP_URL_HOST) ?: $site['url'] }}</small>
                    </a>
                    <span class="badge {{ $site['state'] }}">{{ $site['state_label'] }}</span>
                </div>
                <div class="metrics">
                    <div>Respuesta<b>{{ $site['last_ms'] !== null ? $site['last_ms'].' ms' : '—' }}</b></div>
                    <div>Uptime 24h<b>{{ $site['uptime_24h'] !== null ? $site['uptime_24h'].'%' : '—' }}</b></div>
                    <div>Uptime 7d<b>{{ $site['uptime_7d'] !== null ? $site['uptime_7d'].'%' : '—' }}</b></div>
                    <div>Último check<b id="lc-{{ $site['id'] }}">{{ $site['last_check'] ? \Illuminate\Support\Carbon::parse($site['last_check'])->diffForHumans() : '—' }}</b></div>
                </div>
                <div class="spark" aria-label="Uptime últimas 24 horas">
                    @foreach ($site['sparkline'] as $bucket)
                        @php
                            $cls = $bucket['total'] === 0 ? 'empty' : ($bucket['up'] === $bucket['total'] ? ($bucket['up'] > 0 ? '' : 'empty') : ($bucket['up'] === 0 ? 'down' : 'mixed'));
                            $h = $bucket['total'] === 0 ? 4 : max(12, (int) round($bucket['up'] / max(1, $bucket['total']) * 36));
                        @endphp
                        <div class="bar {{ $cls }}" style="height: {{ $h }}px" title="{{ $bucket['up'] }}/{{ $bucket['total'] }} checks OK"></div>
                    @endforeach
                </div>
                <div class="spark-label"><span>-24h</span><span>ahora</span></div>
            </div>
        @endforeach
    </div>

    <footer>
        MediaDev Monitor · <a href="/admin">Panel admin</a> · actualización automática cada 5 s
    </footer>

    <script>
        (() => {
            const REFRESH_MS = 5000, STALE_MS = 15000;
            let lastUpdate = Date.now();

            function stateLabel(state) {
                return { 'wp-full': 'OK', 'wp-degraded': 'Degradado', 'non-wp': 'No-WP',
                         'down': 'Caído', unknown: 'Sin datos' }[state] ?? state;
            }

            function updateCard(site) {
                const card = document.querySelector(`[data-site-id="${site.id}"]`);
                if (!card) return location.reload(); // site added/removed → full reload
                card.dataset.state = site.state;

                const badge = card.querySelector('.badge');
                badge.className = `badge ${site.state}`;
                badge.textContent = stateLabel(site.state);

                const [ms, u24, u7] = card.querySelectorAll('.metrics b');
                ms.textContent  = site.last_ms !== null ? `${site.last_ms} ms` : '—';
                u24.textContent = site.uptime_24h !== null ? `${site.uptime_24h}%` : '—';
                u7.textContent  = site.uptime_7d !== null ? `${site.uptime_7d}%` : '—';

                const bars = card.querySelectorAll('.spark .bar');
                site.sparkline.forEach((b, i) => {
                    if (!bars[i]) return;
                    const cls = b.total === 0 ? 'empty' : (b.up === b.total ? (b.up > 0 ? 'bar' : 'empty') : (b.up === 0 ? 'down' : 'mixed'));
                    bars[i].className = `bar ${cls === 'bar' ? '' : cls}`;
                    bars[i].style.height = (b.total === 0 ? 4 : Math.max(12, Math.round(b.up / Math.max(1, b.total) * 36))) + 'px';
                    bars[i].title = `${b.up}/${b.total} checks OK`;
                });
            }

            function updateSummary(sites) {
                const c = s => sites.filter(x => x.state === s).length;
                document.getElementById('sum-ok').textContent = c('wp-full');
                document.getElementById('sum-degraded').textContent = c('wp-degraded') + c('non-wp');
                document.getElementById('sum-down').textContent = c('down');
                document.getElementById('sum-total').textContent = sites.length;
            }

            async function poll() {
                try {
                    const res = await fetch('/status.json', { headers: { Accept: 'application/json' } });
                    if (!res.ok) throw new Error(res.status);
                    const data = await res.json();
                    data.sites.forEach(updateCard);
                    updateSummary(data.sites);
                    lastUpdate = Date.now();
                    document.getElementById('live').classList.remove('stale');
                } catch {
                    document.getElementById('live').classList.add('stale');
                    document.getElementById('live-text').textContent = 'sin conexión';
                }
            }

            setInterval(() => {
                if (Date.now() - lastUpdate > STALE_MS) {
                    document.getElementById('live').classList.add('stale');
                }
                poll();
            }, REFRESH_MS);
            poll();
        })();
    </script>
</body>
</html>