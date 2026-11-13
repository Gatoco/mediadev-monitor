@php
    $counts = ['wp-full' => 0, 'wp-degraded' => 0, 'non-wp' => 0, 'down' => 0];
    foreach ($sites as $s) {
        if (isset($counts[$s['state']])) {
            $counts[$s['state']]++;
        }
    }
    $total = count($sites);
    $worst = $counts['down'] > 0 ? 'down' : ($counts['wp-degraded'] > 0 ? 'wp-degraded' : 'wp-full');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SemaOPS · Mission Control</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #080a0f;
            --surface: #0f131a;
            --surface-2: #151a23;
            --line: rgba(226, 232, 240, .08);
            --line-strong: rgba(226, 232, 240, .16);
            --text: #e6eaf0;
            --text-2: #a3adbd;
            --muted: #6b7585;
            --ok: #2fb57c;
            --warn: #e0a23a;
            --nonwp: #7c8798;
            --down: #e5484d;
            --lamp-off: #1b212b;
            --ease: cubic-bezier(.2, .7, .2, 1);
            --sans: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            --mono: 'JetBrains Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            background: var(--bg); color: var(--text);
            font: 400 14px/1.45 var(--sans);
            font-variant-numeric: tabular-nums;
            -webkit-font-smoothing: antialiased;
        }
        .mono { font-family: var(--mono); }
        button { font: inherit; color: inherit; }
        :focus-visible { outline: 2px solid var(--text-2); outline-offset: 2px; }

        /* Shell fills the viewport so the kiosk (F11) never scrolls at >= 1280x800 */
        .shell { display: flex; flex-direction: column; gap: 16px; height: 100vh; min-height: 760px; padding: 0 24px 20px; }

        /* Top bar: quiet chrome */
        .topbar { display: flex; align-items: center; gap: 24px; height: 56px; border-bottom: 1px solid var(--line); flex: none; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand svg { width: 28px; height: 28px; }
        .brand-name { font-weight: 600; font-size: 16px; }
        .brand-sub { color: var(--muted); font-size: 13px; }
        .topbar-meta { margin-left: auto; display: flex; align-items: center; gap: 20px; color: var(--text-2); font-size: 13px; }
        .telemetry { display: flex; align-items: center; gap: 8px; }
        .telemetry .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ok); }
        .telemetry[data-mode="stale"] .dot { background: var(--warn); }
        .telemetry[data-mode="offline"] .dot { background: var(--down); }
        .telemetry[data-mode="sim"] .dot { background: var(--text); }
        .clock { color: var(--text); font-family: var(--mono); font-size: 13px; }

        /* Hero band: semaphore + message + quiet KPIs */
        .hero { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 32px; align-items: center; flex: none; }
        .signal { display: flex; align-items: center; gap: 20px; }
        .housing { width: 64px; padding: 8px; display: grid; gap: 8px; background: #11161e; border: 1px solid var(--line-strong); border-radius: 16px; box-shadow: inset 0 1px 0 rgba(255,255,255,.04), 0 8px 24px rgba(0,0,0,.45); }
        .lamp { width: 48px; height: 48px; border-radius: 50%; background: radial-gradient(circle at 35% 30%, #262d38, var(--lamp-off) 60%); box-shadow: inset 0 2px 6px rgba(0,0,0,.6); transition: background .4s var(--ease), box-shadow .4s var(--ease); }
        .housing[data-lit="down"] .lamp-r { background: radial-gradient(circle at 35% 30%, #ff9a9d, var(--down) 55%, #8f2226); box-shadow: 0 0 24px rgba(229,72,77,.45), inset 0 -3px 6px rgba(0,0,0,.35); }
        .housing[data-lit="wp-degraded"] .lamp-a { background: radial-gradient(circle at 35% 30%, #ffd896, var(--warn) 55%, #8a5d14); box-shadow: 0 0 24px rgba(224,162,58,.4), inset 0 -3px 6px rgba(0,0,0,.35); }
        .housing[data-lit="wp-full"] .lamp-g { background: radial-gradient(circle at 35% 30%, #9cf0c8, var(--ok) 55%, #146b45); box-shadow: 0 0 24px rgba(47,181,124,.4), inset 0 -3px 6px rgba(0,0,0,.35); }
        .legend { display: grid; gap: 8px; }
        .legend-row { display: grid; grid-template-columns: 10px 40px auto; align-items: baseline; gap: 10px; color: var(--text-2); font-size: 13px; }
        .legend-row .swatch { width: 10px; height: 10px; border-radius: 3px; align-self: center; }
        .legend-row b { font: 500 22px/1 var(--mono); color: var(--text); text-align: right; }
        .legend-row small { color: var(--muted); font-family: var(--mono); font-size: 11px; margin-left: 6px; }
        .headline h1 { font-size: 30px; font-weight: 500; line-height: 1.15; letter-spacing: -.01em; }
        .headline h1 span.soft { color: var(--muted); }
        .headline p { margin-top: 8px; color: var(--text-2); font-size: 15px; max-width: 56ch; }
        .kpis { display: grid; grid-template-columns: repeat(4, auto); border: 1px solid var(--line); border-radius: 12px; background: var(--surface); }
        .kpi { padding: 12px 20px; min-width: 132px; }
        .kpi + .kpi { border-left: 1px solid var(--line); }
        .kpi dt { color: var(--muted); font-size: 12px; }
        .kpi dd { font: 500 24px/1.2 var(--mono); margin-top: 4px; white-space: nowrap; }
        .kpi dd small { font-size: 13px; color: var(--text-2); margin-left: 2px; }
        .kpi .note { color: var(--muted); font-size: 11px; margin-top: 2px; white-space: nowrap; }

        /* Body: node matrix + interactive console */
        .body { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(0, 1fr) 420px; gap: 16px; }
        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; min-height: 0; }
        .panel-head { display: flex; align-items: baseline; gap: 12px; padding: 14px 16px 0; }
        .panel-head h2 { font-size: 14px; font-weight: 500; }
        .panel-head .meta { color: var(--muted); font-size: 12px; margin-left: auto; }

        .matrix-wrap { display: flex; flex-direction: column; }
        .matrix { flex: 1; min-height: 0; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); grid-auto-rows: minmax(104px, 1fr); gap: 8px; padding: 12px 16px 16px; }
        .node {
            display: flex; flex-direction: column; gap: 4px; min-width: 0;
            padding: 10px 12px; text-align: left; cursor: pointer;
            background: var(--surface-2); border: 1px solid var(--line); border-radius: 10px;
            transition: border-color .2s var(--ease), background-color .2s var(--ease);
        }
        .node:hover { border-color: var(--line-strong); background: #18202b; }
        .node[aria-pressed="true"] { border-color: var(--text-2); }
        .node[data-state="down"] { border-color: rgba(229,72,77,.55); background: #1a1216; }
        .node-top { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .led { flex: none; width: 8px; height: 8px; border-radius: 50%; background: var(--muted); color: var(--muted); }
        [data-state="wp-full"] > .led, [data-state="wp-full"] .node-top .led { background: var(--ok); color: var(--ok); }
        [data-state="wp-degraded"] > .led, [data-state="wp-degraded"] .node-top .led { background: var(--warn); color: var(--warn); }
        [data-state="non-wp"] > .led, [data-state="non-wp"] .node-top .led { background: var(--nonwp); color: var(--nonwp); }
        [data-state="down"] > .led, [data-state="down"] .node-top .led { background: var(--down); color: var(--down); }
        /* One short flash when a fresh check lands: motion that means "data arrived" */
        .node.tick .led { animation: tick .6s var(--ease); }
        @keyframes tick { from { box-shadow: 0 0 0 0 currentColor; } to { box-shadow: 0 0 0 5px transparent; } }
        .node-name { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .node-ms { font: 500 18px/1.1 var(--mono); }
        .node-ms small { font-size: 11px; color: var(--muted); margin-left: 2px; }
        .node[data-state="down"] .node-ms { color: var(--down); }
        .node-spark { width: 100%; flex: 1; min-height: 16px; max-height: 40px; }
        .node-spark polyline { fill: none; stroke: var(--text-2); stroke-width: 1.25; vector-effect: non-scaling-stroke; }
        .node[data-state="wp-full"] .node-spark polyline { stroke: var(--ok); }
        .node[data-state="wp-degraded"] .node-spark polyline { stroke: var(--warn); }
        .node[data-state="down"] .node-spark polyline { stroke: var(--down); }
        .node-foot { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 11px; }
        .node-foot .state { color: var(--text-2); white-space: nowrap; }
        .node-foot .up { margin-left: auto; font-family: var(--mono); }
        /* Signature: the 3-strike rule, one notch per consecutive failure */
        .strikes { display: inline-flex; gap: 2px; }
        .strikes i { width: 6px; height: 6px; border-radius: 1px; background: #232a35; }
        .strikes i.on { background: var(--down); }

        .side { display: flex; flex-direction: column; overflow: hidden; }
        .sim-note { padding: 4px 16px 0; color: var(--muted); font-size: 12px; }
        .target { display: flex; align-items: center; gap: 10px; margin: 12px 16px 0; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--surface-2); }
        .target-name { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .target-state { margin-left: auto; color: var(--text-2); font-size: 12px; white-space: nowrap; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px 16px 0; }
        .btn { height: 44px; border-radius: 10px; border: 1px solid var(--line-strong); background: transparent; cursor: pointer; font-weight: 500; transition: background-color .15s var(--ease), border-color .15s var(--ease), transform .1s var(--ease); }
        .btn:hover:not(:disabled) { border-color: var(--text-2); }
        .btn:active:not(:disabled) { transform: scale(.98); }
        .btn:disabled { opacity: .4; cursor: not-allowed; }
        .btn-primary { background: var(--text); color: #0b0e13; border-color: var(--text); }
        .btn-primary:hover:not(:disabled) { background: #fff; }
        .console {
            flex: 1; min-height: 120px; margin: 12px 16px 0; padding: 12px; overflow-y: auto;
            background: #07090d; border: 1px solid var(--line); border-radius: 10px;
            font: 400 12px/1.6 var(--mono); color: var(--text-2); white-space: pre-wrap; word-break: break-word;
        }
        .console div { min-height: 1.6em; }
        .c-cmd { color: var(--text); }
        .c-err { color: var(--down); }
        .c-warn { color: var(--warn); }
        .c-ok { color: var(--ok); }
        .c-dim { color: var(--muted); }
        .c-step { color: var(--text); }
        .events-title { margin: 12px 16px 0; color: var(--muted); font-size: 12px; }
        .events { list-style: none; margin: 8px 16px 16px; display: grid; align-content: start; gap: 4px; height: 124px; overflow: hidden; flex: none; }
        .events li { display: grid; grid-template-columns: 64px 6px minmax(0, 1fr); align-items: center; gap: 8px; font-size: 12px; color: var(--text-2); white-space: nowrap; }
        .events li time { font-family: var(--mono); color: var(--muted); }
        .events li .led { width: 6px; height: 6px; }
        .events li span:last-child { overflow: hidden; text-overflow: ellipsis; }
        .events .empty { color: var(--muted); }

        .footer { display: flex; justify-content: space-between; color: var(--muted); font-size: 12px; flex: none; }

        @media (max-width: 1500px) {
            .hero { gap: 24px; }
            .headline h1 { font-size: 24px; }
            .headline p { font-size: 14px; }
            .kpis { grid-template-columns: repeat(2, auto); }
            .kpi + .kpi { border-left: 0; }
            .kpi:nth-child(even) { border-left: 1px solid var(--line); }
            .kpi:nth-child(n+3) { border-top: 1px solid var(--line); }
            .kpi { padding: 8px 16px; }
            .kpi dd { font-size: 20px; }
            .lamp { width: 40px; height: 40px; }
            .housing { width: 56px; }
            .body { grid-template-columns: minmax(0, 1fr) 380px; }
            .matrix { grid-template-columns: repeat(6, minmax(0, 1fr)); grid-auto-rows: minmax(96px, 1fr); }
            .node { padding: 8px 10px; }
            .node-name { font-size: 12px; }
        }
        @media (max-width: 1279px) {
            .shell { height: auto; }
            .hero { grid-template-columns: 1fr; }
            .body { grid-template-columns: 1fr; }
            .matrix { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div class="brand">
            <svg viewBox="0 0 160 120" aria-hidden="true">
                <rect x="56" y="6" width="48" height="72" rx="14" fill="#334155"/>
                <circle cx="80" cy="22" r="9" fill="#e5484d"/>
                <circle cx="80" cy="42" r="9" fill="#e0a23a"/>
                <circle cx="80" cy="62" r="9" fill="#2fb57c"/>
                <polyline points="8,100 44,100 54,86 66,112 78,94 90,100 152,100" fill="none" stroke="#2fb57c" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="brand-name">SemaOPS</span>
            <span class="brand-sub">Mission Control</span>
        </div>
        <div class="topbar-meta">
            <span class="telemetry" id="telemetry" data-mode="live" role="status">
                <span class="dot" aria-hidden="true"></span><span id="telemetry-text">Telemetría real</span>
            </span>
            <time class="clock" id="clock">--:--:--</time>
        </div>
    </header>

    <section class="hero" aria-label="Resumen de la cartera">
        <div class="signal">
            <div class="housing" id="housing" data-lit="{{ $worst }}" role="img" aria-label="Semáforo global">
                <div class="lamp lamp-r"></div>
                <div class="lamp lamp-a"></div>
                <div class="lamp lamp-g"></div>
            </div>
            <div class="legend">
                <div class="legend-row"><span class="swatch" style="background:var(--ok)"></span><b id="c-wp-full">{{ $counts['wp-full'] }}</b><span>Operativos<small>wp-full</small></span></div>
                <div class="legend-row"><span class="swatch" style="background:var(--warn)"></span><b id="c-wp-degraded">{{ $counts['wp-degraded'] }}</b><span>Requieren atención<small>wp-degraded</small></span></div>
                <div class="legend-row"><span class="swatch" style="background:var(--nonwp)"></span><b id="c-non-wp">{{ $counts['non-wp'] }}</b><span>Sin WordPress<small>non-wp</small></span></div>
                <div class="legend-row"><span class="swatch" style="background:var(--down)"></span><b id="c-down">{{ $counts['down'] }}</b><span>Caídos<small>down</small></span></div>
            </div>
        </div>

        <div class="headline">
            <h1><span id="h-total">{{ $total }}</span> sitios WordPress vigilados <span class="soft">sin instalar plugins</span></h1>
            <p>Detecta la caída antes que tu cliente y explica cómo resolverla. Tu software, siempre en verde.</p>
        </div>

        <dl class="kpis">
            <div class="kpi"><dt>Uptime 24 h</dt><dd id="k-uptime">—</dd></div>
            <div class="kpi"><dt>Latencia media</dt><dd id="k-latency">—</dd></div>
            <div class="kpi"><dt>Plugins instalados</dt><dd>0</dd><div class="note">REST + Application Passwords</div></div>
            <div class="kpi"><dt>Ahorro estimado</dt><dd>25–35<small>%</small></dd><div class="note">jornada semanal · encuesta n=45</div></div>
        </dl>
    </section>

    <main class="body">
        <section class="panel matrix-wrap" aria-labelledby="matrix-title">
            <div class="panel-head">
                <h2 id="matrix-title">Matriz de salud</h2>
                <span class="meta mono">latencia 24 h · regla de 3 fallos</span>
            </div>
            <div class="matrix" id="matrix">
                @foreach ($sites as $site)
                    <button type="button" class="node" data-site-id="{{ $site['id'] }}" data-state="{{ $site['state'] }}" aria-pressed="false"
                            title="{{ parse_url($site['url'], PHP_URL_HOST) ?: $site['url'] }}">
                        <span class="node-top"><span class="led" aria-hidden="true"></span><span class="node-name">{{ $site['name'] }}</span></span>
                        <span class="node-ms">{{ $site['last_ms'] !== null ? $site['last_ms'] : '—' }}<small>{{ $site['last_ms'] !== null ? 'ms' : '' }}</small></span>
                        <svg class="node-spark" viewBox="0 0 96 24" preserveAspectRatio="none" aria-hidden="true"><polyline points=""/></svg>
                        <span class="node-foot">
                            <span class="state">{{ $site['state_label'] }}</span>
                            <span class="strikes" aria-label="{{ $site['failures'] }} fallos consecutivos">@for ($i = 1; $i <= 3; $i++)<i class="{{ $site['failures'] >= $i ? 'on' : '' }}"></i>@endfor</span>
                            <span class="up">{{ $site['uptime_24h'] !== null ? $site['uptime_24h'].'%' : '—' }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </section>

        <aside class="panel side" aria-labelledby="sim-title">
            <div class="panel-head">
                <h2 id="sim-title">Demo interactiva</h2>
                <span class="meta">toca un sitio de la matriz</span>
            </div>
            <p class="sim-note">Simulación local: no modifica datos reales ni envía alertas</p>
            <div class="target" id="target" data-state="unknown">
                <span class="led" aria-hidden="true"></span>
                <span class="target-name" id="target-name">—</span>
                <span class="target-state" id="target-state"></span>
            </div>
            <div class="actions">
                <button type="button" class="btn btn-primary" id="btn-fail">Simular caída</button>
                <button type="button" class="btn" id="btn-heal" disabled>Re-verificar y reparar</button>
            </div>
            <div class="console" id="console" aria-live="polite" aria-label="Consola de diagnóstico asistido"><div class="c-dim">Diagnóstico asistido · esperando un incidente</div></div>
            <p class="events-title">Eventos</p>
            <ol class="events" id="events" aria-live="polite"><li class="empty">Sin cambios de estado desde que abriste el panel</li></ol>
        </aside>
    </main>

    <footer class="footer">
        <span>SemaOPS · INACAP Sede Talca</span>
        <span class="mono" id="footer-meta">actualización cada 5 s</span>
    </footer>
</div>

<script>
(() => {
    const REFRESH_MS = 5000, STALE_CHECK_MS = 10 * 60 * 1000, SIM_IDLE_RESET_MS = 120000;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const LABELS = { 'wp-full': 'OK', 'wp-degraded': 'Degradado', 'non-wp': 'No-WP', down: 'Caído', unknown: 'Sin datos' };
    const IDLE_CONSOLE = '<div class="c-dim">Diagnóstico asistido · esperando un incidente</div>';
    const $ = (id) => document.getElementById(id);

    let sites = [];                 // last real payload from /status.json
    const sim = new Map();          // id -> overlay { state, failures, last_ms }; lives only in this browser
    let selectedId = null, busy = false, idleTimer = null;

    const host = (url) => { try { return new URL(url).host; } catch { return url; } };
    const view = (s) => sim.has(s.id) ? { ...s, ...sim.get(s.id) } : s;
    const hhmmss = (d = new Date()) => d.toLocaleTimeString('es-CL', { hour12: false });

    function sparkPoints(buckets) {
        const vals = buckets.map((b) => b.avg_ms ?? null);
        const known = vals.filter((v) => v !== null);
        if (known.length < 2) return '';
        const max = Math.max(...known), min = Math.min(...known), span = Math.max(1, max - min);
        const step = 96 / (vals.length - 1);
        return vals.map((v, i) => v === null ? null : `${(i * step).toFixed(1)},${(22 - ((v - min) / span) * 20).toFixed(1)}`)
            .filter(Boolean).join(' ');
    }

    function renderNode(s, flash) {
        const el = document.querySelector(`.node[data-site-id="${s.id}"]`);
        if (!el) return false;
        const v = view(s);
        el.dataset.state = v.state;
        const noMs = v.state === 'down' || v.last_ms === null;
        el.querySelector('.node-ms').firstChild.textContent = noMs ? '—' : v.last_ms;
        el.querySelector('.node-ms small').textContent = noMs ? '' : 'ms';
        el.querySelector('.node-foot .state').textContent = v.failures > 0 && v.state !== 'down' ? `Fallo ${v.failures}/3` : (LABELS[v.state] ?? v.state);
        el.querySelector('.node-foot .up').textContent = s.uptime_24h !== null ? `${s.uptime_24h}%` : '—';
        el.querySelectorAll('.strikes i').forEach((n, i) => n.classList.toggle('on', v.failures > i));
        el.querySelector('.strikes').setAttribute('aria-label', `${v.failures} fallos consecutivos`);
        el.querySelector('polyline').setAttribute('points', sparkPoints(s.sparkline));
        el.setAttribute('aria-label', `${s.name}: ${LABELS[v.state] ?? v.state}`);
        if (flash && !reduced) { el.classList.remove('tick'); void el.offsetWidth; el.classList.add('tick'); }
        return true;
    }

    function renderSummary() {
        const shown = sites.map(view);
        const c = (st) => shown.filter((x) => x.state === st).length;
        for (const st of ['wp-full', 'wp-degraded', 'non-wp', 'down']) $(`c-${st}`).textContent = c(st);
        $('h-total').textContent = shown.length;
        $('housing').dataset.lit = c('down') ? 'down' : c('wp-degraded') ? 'wp-degraded' : 'wp-full';

        const ups = sites.map((s) => s.uptime_24h).filter((u) => u !== null);
        $('k-uptime').innerHTML = ups.length ? `${(ups.reduce((a, b) => a + b, 0) / ups.length).toFixed(1)}<small>%</small>` : '—';
        const lat = shown.filter((s) => s.state !== 'down' && s.last_ms !== null).map((s) => s.last_ms);
        $('k-latency').innerHTML = lat.length ? `${Math.round(lat.reduce((a, b) => a + b, 0) / lat.length)}<small>ms</small>` : '—';
    }

    function renderTarget() {
        document.querySelectorAll('.node').forEach((n) => n.setAttribute('aria-pressed', String(Number(n.dataset.siteId) === selectedId)));
        const s = sites.find((x) => x.id === selectedId);
        if (!s) return;
        const v = view(s);
        $('target').dataset.state = v.state;
        $('target-name').textContent = s.name;
        $('target-state').textContent = `${host(s.url) === s.name ? '' : host(s.url) + ' · '}${v.failures > 0 && v.state !== 'down' ? `fallo ${v.failures}/3` : (LABELS[v.state] ?? v.state)}`;
        $('btn-fail').disabled = busy || v.state === 'down';
        $('btn-heal').disabled = busy || !sim.has(s.id);
    }

    function logEvent(state, text) {
        $('events').querySelector('.empty')?.remove();
        const li = document.createElement('li');
        li.dataset.state = state;
        li.innerHTML = '<time></time><span class="led"></span><span></span>';
        li.firstChild.textContent = hhmmss();
        li.lastChild.textContent = text;
        $('events').prepend(li);
        while ($('events').children.length > 6) $('events').lastChild.remove();
    }

    function setTelemetry() {
        const t = $('telemetry');
        if (sim.size) {
            t.dataset.mode = 'sim';
            $('telemetry-text').textContent = `Simulación en ${sim.size} sitio${sim.size > 1 ? 's' : ''} · resto telemetría real`;
            return;
        }
        const latest = Math.max(0, ...sites.map((s) => s.last_check ? Date.parse(s.last_check) : 0));
        if (!latest) { t.dataset.mode = 'stale'; $('telemetry-text').textContent = 'Telemetría real · sin checks registrados'; return; }
        const age = Date.now() - latest;
        const rel = age < 90e3 ? `hace ${Math.max(1, Math.round(age / 1000))} s` : age < 3600e3 ? `hace ${Math.round(age / 60e3)} min` : `hace ${Math.round(age / 3600e3)} h`;
        t.dataset.mode = age > STALE_CHECK_MS ? 'stale' : 'live';
        $('telemetry-text').textContent = `Telemetría real · último check ${rel}`;
    }

    function apply(data) {
        if (sites.length && data.sites.length !== sites.length) return location.reload();
        const prev = new Map(sites.map((s) => [s.id, s]));
        sites = data.sites;
        for (const s of sites) {
            const before = prev.get(s.id);
            if (!renderNode(s, before && before.last_check !== s.last_check)) return location.reload();
            if (before && before.state !== s.state && !sim.has(s.id)) logEvent(s.state, `${s.name}: ${LABELS[before.state]} → ${LABELS[s.state]}`);
        }
        if (selectedId === null) selectedId = (sites.find((s) => s.state === 'wp-full') ?? sites[0])?.id ?? null;
        renderSummary(); renderTarget(); setTelemetry();
    }

    async function poll() {
        try {
            const res = await fetch('/status.json', { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            apply(await res.json());
            $('footer-meta').textContent = `actualizado ${hhmmss()} · cada 5 s`;
        } catch {
            $('telemetry').dataset.mode = 'offline';
            $('telemetry-text').textContent = 'Sin conexión con el monitor';
        }
    }

    // Diagnosis console: streams like a terminal, prints instantly with reduced motion
    const wait = (ms) => new Promise((r) => setTimeout(r, reduced ? 0 : ms));
    async function print(text, cls = '') {
        const line = document.createElement('div');
        if (cls) line.className = cls;
        $('console').append(line);
        if (reduced) line.textContent = text;
        else for (let i = 0; i < text.length; i += 3) { line.textContent = text.slice(0, i + 3); await wait(12); }
        $('console').scrollTop = $('console').scrollHeight;
    }

    async function simulateFailure() {
        const s = sites.find((x) => x.id === selectedId);
        if (!s || busy || view(s).state === 'down') return;
        busy = true; renderTarget(); $('console').textContent = '';
        const h = host(s.url);
        await print(`$ semaops check ${h} --watch`, 'c-cmd');
        for (let n = 1; n <= 3; n++) {
            await wait(700);
            sim.set(s.id, { state: n < 3 ? s.state : 'down', failures: n, last_ms: null });
            renderNode(s, true); renderSummary(); renderTarget(); setTelemetry();
            await print(`GET https://${h}/  →  HTTP 500 Internal Server Error  (fallo ${n}/3)`, n < 3 ? 'c-warn' : 'c-err');
        }
        logEvent('down', `${s.name}: ${LABELS[s.state]} → Caído (simulado)`);
        await print('Estado DOWN · alerta lista para Telegram y correo (no se envía en modo feria)', 'c-err');
        await wait(400);
        await print('');
        await print(`$ semaops diagnose ${h}`, 'c-cmd');
        await print('GET /wp-json/  →  HTTP 500');
        await print('error_log: PHP Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)', 'c-err');
        await print('Causa probable: error fatal de PHP tras actualizar un plugin; el sitio superó el límite de memoria asignado', 'c-step');
        await print(`WPVulnerability: core ${s.core_version ?? 'sin versión registrada'} · cruce real deshabilitado en modo feria`, 'c-dim');
        await print('');
        await print('Pasos recomendados', 'c-cmd');
        await print('1. Desactivar el plugin actualizado con WP-CLI:  wp plugin deactivate <slug>', 'c-step');
        await print("2. Subir WP_MEMORY_LIMIT a 256M en wp-config.php:  define('WP_MEMORY_LIMIT', '256M');", 'c-step');
        await print('3. Reactivar el plugin y verificar la estabilidad del sitio', 'c-step');
        busy = false; renderTarget(); armIdleReset();
    }

    async function heal() {
        const s = sites.find((x) => x.id === selectedId);
        if (!s || !sim.has(s.id) || busy) return;
        busy = true; renderTarget();
        await print('');
        await print(`$ semaops recheck ${host(s.url)}`, 'c-cmd');
        await wait(600);
        sim.delete(s.id);
        renderNode(s, true); renderSummary(); renderTarget(); setTelemetry();
        await print(`GET https://${host(s.url)}/  →  ${LABELS[s.state]} · ${s.last_ms ?? '—'} ms (telemetría real)`, 'c-ok');
        await print('Recuperado. Tu software, siempre en verde', 'c-ok');
        logEvent(s.state, `${s.name}: recuperado → ${LABELS[s.state]}`);
        busy = false; renderTarget();
    }

    function armIdleReset() {
        clearTimeout(idleTimer);
        // Kiosk: if nobody touches the screen, the board returns to real telemetry
        idleTimer = setTimeout(() => {
            if (busy) return armIdleReset();
            sim.clear();
            sites.forEach((s) => renderNode(s, false));
            renderSummary(); renderTarget(); setTelemetry();
            $('console').innerHTML = IDLE_CONSOLE;
        }, SIM_IDLE_RESET_MS);
    }

    $('matrix').addEventListener('click', (e) => {
        const node = e.target.closest('.node');
        if (!node || busy) return;
        selectedId = Number(node.dataset.siteId);
        renderTarget(); armIdleReset();
    });
    $('btn-fail').addEventListener('click', simulateFailure);
    $('btn-heal').addEventListener('click', heal);

    const tickClock = () => { $('clock').textContent = hhmmss(); };
    tickClock(); setInterval(tickClock, 1000);
    poll(); setInterval(poll, REFRESH_MS);
})();
</script>
</body>
</html>
