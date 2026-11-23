<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#07080b">
    <title>SemaOPS · Remote</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #07080b; --panel: #0e1016; --panel-2: #141720; --edge: #1f2330; --edge-hi: #2c3242;
            --text: #eef1f6; --text-2: #9aa3b5; --muted: #5d6578;
            --ok: #35d08a; --warn: #f2b13d; --down: #ff4d57; --amber-glow: rgba(242, 177, 61, .35);
            --display: 'Chakra Petch', 'IBM Plex Mono', ui-monospace, monospace;
            --mono: 'IBM Plex Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        html { background: var(--bg); }
        body {
            color: var(--text); font-family: var(--mono); font-size: 13px; user-select: none;
            padding: 0 14px calc(28px + env(safe-area-inset-bottom)); min-height: 100vh;
            background:
                radial-gradient(120% 60% at 50% -10%, rgba(53, 208, 138, .10), transparent 60%),
                repeating-linear-gradient(0deg, rgba(255,255,255,.018) 0 1px, transparent 1px 3px),
                var(--bg);
        }
        body[data-alert="down"] { background:
                radial-gradient(120% 60% at 50% -10%, rgba(255, 77, 87, .16), transparent 60%),
                repeating-linear-gradient(0deg, rgba(255,255,255,.018) 0 1px, transparent 1px 3px), var(--bg); }

        /* ---------- top bar ---------- */
        .bar { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between;
               padding: calc(10px + env(safe-area-inset-top)) 0 10px; background: linear-gradient(var(--bg) 70%, transparent); }
        .brand { font-family: var(--display); font-weight: 700; font-size: 15px; letter-spacing: .14em; }
        .brand i { font-style: normal; color: var(--ok); }
        .link { display: flex; align-items: center; gap: 8px; color: var(--text-2); font-size: 11px; }
        .led { width: 8px; height: 8px; border-radius: 50%; background: var(--ok); box-shadow: 0 0 10px var(--ok); }
        .link[data-off] .led { background: var(--down); box-shadow: 0 0 10px var(--down); animation: blink 1s steps(2) infinite; }
        @keyframes blink { 50% { opacity: .2; } }

        /* ---------- hero gauge ---------- */
        .hero { display: grid; grid-template-columns: 132px 1fr; gap: 14px; align-items: center; margin-top: 4px;
                background: var(--panel); border: 1px solid var(--edge); border-radius: 18px; padding: 14px; position: relative; overflow: hidden; }
        .hero::after { content: ''; position: absolute; inset: 0; pointer-events: none;
                       background: linear-gradient(115deg, transparent 40%, rgba(255,255,255,.035) 50%, transparent 60%); }
        .gauge { position: relative; width: 132px; height: 132px; }
        .gauge svg { width: 100%; height: 100%; transform: rotate(-90deg); }
        .gauge circle { fill: none; stroke-width: 10; }
        .gauge .track { stroke: var(--edge); }
        .gauge .arc { stroke: var(--ok); stroke-linecap: round; transition: stroke-dashoffset .8s cubic-bezier(.2,.7,.2,1), stroke .4s; filter: drop-shadow(0 0 6px currentColor); }
        .gauge-txt { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
        .gauge-txt b { font-family: var(--display); font-size: 36px; line-height: 1; font-variant-numeric: tabular-nums; }
        .gauge-txt span { font-size: 10px; color: var(--text-2); letter-spacing: .12em; margin-top: 4px; }
        .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat { border-left: 2px solid var(--edge-hi); padding: 2px 0 2px 8px; }
        .stat b { display: block; font-family: var(--display); font-size: 22px; line-height: 1.05; font-variant-numeric: tabular-nums; }
        .stat small { font-size: 10px; color: var(--muted); letter-spacing: .08em; }
        .stat[data-k="ok"] { border-color: var(--ok); } .stat[data-k="ok"] b { color: var(--ok); }
        .stat[data-k="warn"] { border-color: var(--warn); } .stat[data-k="warn"] b { color: var(--warn); }
        .stat[data-k="down"] { border-color: var(--down); } .stat[data-k="down"] b { color: var(--down); }

        .ticker { margin-top: 10px; display: flex; gap: 8px; align-items: center; font-size: 11px; color: var(--text-2);
                  border: 1px dashed var(--edge-hi); border-radius: 10px; padding: 8px 10px; min-height: 34px; }
        .ticker time { color: var(--muted); flex: none; }
        .ticker span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ticker[data-tone="down"] span { color: var(--down); } .ticker[data-tone="ok"] span { color: var(--ok); } .ticker[data-tone="warn"] span { color: var(--warn); }

        /* ---------- scenarios ---------- */
        .sec { display: flex; align-items: baseline; justify-content: space-between; margin: 22px 2px 10px; }
        .sec h2 { font-family: var(--display); font-size: 13px; font-weight: 600; letter-spacing: .16em; color: var(--text-2); }
        .sec small { color: var(--muted); font-size: 10px; }
        .pads { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .pad { position: relative; overflow: hidden; min-height: 78px; border-radius: 14px; border: 1px solid var(--edge-hi);
               background: linear-gradient(180deg, var(--panel-2), var(--panel)); color: var(--text); font: inherit; text-align: left;
               padding: 10px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: inset 0 1px 0 rgba(255,255,255,.05), 0 3px 0 #05060a; }
        .pad:active { transform: translateY(2px); box-shadow: inset 0 1px 0 rgba(255,255,255,.05), 0 1px 0 #05060a; }
        .pad b { font-family: var(--display); font-size: 14px; font-weight: 600; letter-spacing: .02em; }
        .pad small { font-size: 10px; color: var(--muted); line-height: 1.3; }
        .pad .k { position: absolute; top: 8px; right: 9px; font-size: 9px; color: var(--muted); }
        .pad[data-tone="ok"] b { color: var(--ok); }
        .pad[data-tone="warn"] b { color: var(--warn); }
        .pad[data-tone="down"] b { color: var(--down); }
        .pad.hazard { grid-column: span 3; min-height: 60px; flex-direction: row; align-items: center; border-color: rgba(255,77,87,.5);
                      background: repeating-linear-gradient(-45deg, rgba(255,77,87,.10) 0 12px, transparent 12px 24px), var(--panel); }
        .pad.hazard .fill { position: absolute; inset: 0; background: rgba(255,77,87,.35); transform-origin: left; transform: scaleX(0); }
        .pad.hazard.holding .fill { transition: transform .9s linear; transform: scaleX(1); }
        .pad.hazard > * { position: relative; }
        .pad:disabled { opacity: .45; }

        /* ---------- channel strips ---------- */
        .filters { display: flex; gap: 6px; }
        .chip { font: inherit; font-size: 10px; color: var(--text-2); background: none; border: 1px solid var(--edge-hi); border-radius: 999px; padding: 4px 9px; }
        .chip[aria-pressed="true"] { color: var(--bg); background: var(--text); border-color: var(--text); }
        .strips { display: grid; gap: 6px; }
        .strip { display: grid; grid-template-columns: 30px 1fr auto; gap: 10px; align-items: center; padding: 10px 10px 10px 8px;
                 background: var(--panel); border: 1px solid var(--edge); border-radius: 14px; position: relative; transition: border-color .25s, background .25s; }
        .strip::before { content: ''; position: absolute; left: 0; top: 12px; bottom: 12px; width: 3px; border-radius: 0 3px 3px 0; background: var(--ok); box-shadow: 0 0 8px var(--ok); transition: background .25s, box-shadow .25s; }
        .strip[data-s="down"] { border-color: rgba(255,77,87,.55); background: linear-gradient(90deg, rgba(255,77,87,.12), var(--panel) 60%); }
        .strip[data-s="down"]::before { background: var(--down); box-shadow: 0 0 10px var(--down); }
        .strip[data-s="wp-degraded"] { border-color: rgba(242,177,61,.5); background: linear-gradient(90deg, rgba(242,177,61,.10), var(--panel) 60%); }
        .strip[data-s="wp-degraded"]::before { background: var(--warn); box-shadow: 0 0 10px var(--warn); }
        .strip[data-s="non-wp"]::before { background: var(--muted); box-shadow: none; }
        .strip.flash { animation: flash .6s ease-out; }
        @keyframes flash { from { box-shadow: 0 0 0 2px rgba(255,255,255,.35); } }
        .ch { font-size: 10px; color: var(--muted); text-align: center; line-height: 1.2; }
        .ch b { display: block; font-family: var(--display); font-size: 14px; color: var(--text-2); }
        .info { min-width: 0; }
        .nm { font-weight: 600; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .meta { display: flex; gap: 10px; align-items: center; margin-top: 5px; font-size: 10px; color: var(--muted); }
        .meta .ms { color: var(--text-2); font-variant-numeric: tabular-nums; min-width: 44px; }
        .strip[data-s="down"] .ms { color: var(--down); } .strip[data-s="wp-degraded"] .ms { color: var(--warn); }
        .spark { width: 54px; height: 16px; flex: none; }
        .spark polyline { fill: none; stroke: var(--ok); stroke-width: 1.5; stroke-linejoin: round; }
        .strip[data-s="down"] .spark polyline { stroke: var(--down); } .strip[data-s="wp-degraded"] .spark polyline { stroke: var(--warn); }
        .ctl { display: flex; gap: 6px; align-items: center; }
        .slow { width: 34px; height: 34px; border-radius: 10px; border: 1px solid var(--edge-hi); background: var(--panel-2); color: var(--warn);
                font: 600 14px var(--display); }
        .strip[data-s="wp-degraded"] .slow { background: var(--warn); color: var(--bg); border-color: var(--warn); box-shadow: 0 0 12px var(--amber-glow); }
        .rocker { width: 58px; height: 34px; border-radius: 10px; position: relative; border: 1px solid #0b2a1d;
                  background: linear-gradient(180deg, #1c6b49, #135238); box-shadow: inset 0 -3px 0 rgba(0,0,0,.35); transition: background .25s; }
        .rocker::after { content: 'ON'; position: absolute; top: 3px; bottom: 6px; right: 3px; width: 30px; border-radius: 7px; display: grid; place-items: center;
                         background: linear-gradient(180deg, #f4f6fa, #c9ced8); color: #135238; font: 700 10px var(--display); letter-spacing: .06em;
                         box-shadow: 0 2px 0 rgba(0,0,0,.4); transition: right .22s cubic-bezier(.2,.7,.2,1), color .2s; }
        .strip[data-s="down"] .rocker { background: linear-gradient(180deg, #4a1d22, #331317); border-color: #2a0d10; }
        .strip[data-s="down"] .rocker::after { content: 'OFF'; right: 25px; color: #a3262e; }

        #toast { position: fixed; left: 14px; right: 14px; bottom: calc(14px + env(safe-area-inset-bottom)); z-index: 9;
                 background: var(--down); color: #fff; border-radius: 12px; padding: 12px 14px; font-weight: 600; transform: translateY(150%); transition: transform .25s; }
        #toast.on { transform: none; }

        /* staggered boot */
        .boot > * { animation: rise .5s cubic-bezier(.2,.7,.2,1) both; }
        .boot > *:nth-child(2) { animation-delay: .06s; } .boot > *:nth-child(3) { animation-delay: .12s; }
        .boot > *:nth-child(4) { animation-delay: .18s; } .boot > *:nth-child(5) { animation-delay: .24s; }
        @keyframes rise { from { opacity: 0; transform: translateY(10px); } }
        @media (prefers-reduced-motion: reduce) { *, *::after { animation: none !important; transition: none !important; } }
    </style>
</head>
<body>
<div class="bar">
    <div class="brand">SEMA<i>OPS</i> // REMOTE</div>
    <div class="link" id="link"><span id="clock">--:--:--</span><span class="led"></span></div>
</div>

<main class="boot">
    <section class="hero" aria-label="Salud global">
        <div class="gauge">
            <svg viewBox="0 0 120 120" aria-hidden="true"><circle class="track" cx="60" cy="60" r="50"/><circle class="arc" id="arc" cx="60" cy="60" r="50" stroke-dasharray="314.16" stroke-dashoffset="0"/></svg>
            <div class="gauge-txt"><b id="pct">--</b><span>OPERATIVO</span></div>
        </div>
        <div class="stats">
            <div class="stat" data-k="ok"><b id="n-ok">0</b><small>EN LÍNEA</small></div>
            <div class="stat" data-k="down"><b id="n-down">0</b><small>CAÍDOS</small></div>
            <div class="stat" data-k="warn"><b id="n-warn">0</b><small>DEGRADADOS</small></div>
            <div class="stat"><b id="lat">--</b><small>LATENCIA MEDIA</small></div>
        </div>
    </section>

    <div class="ticker" id="ticker"><time id="t-time">--:--</time><span id="t-text">Listo. Toca un sitio para apagarlo en la pantalla grande.</span></div>

    <div class="sec"><h2>ESCENARIOS</h2><small>se ven en el PC en ~1,5 s</small></div>
    <div class="pads">
        <button class="pad" data-tone="ok" data-scn="green"><span class="k">F1</span><b>Todo verde</b><small>Recupera los {{ count($sites) }}</small></button>
        <button class="pad" data-tone="down" data-scn="one"><span class="k">F2</span><b>Cae uno</b><small>Al azar, con diagnóstico</small></button>
        <button class="pad" data-tone="down" data-scn="cascade"><span class="k">F3</span><b>Cascada</b><small>6 caen en serie</small></button>
        <button class="pad" data-tone="warn" data-scn="slow"><span class="k">F4</span><b>Lentitud</b><small>6 sitios degradados</small></button>
        <button class="pad" data-tone="warn" data-scn="crisis"><span class="k">F5</span><b>Crisis</b><small>5 caídos + 4 lentos</small></button>
        <button class="pad" data-tone="ok" data-scn="chain"><span class="k">F6</span><b>Recuperar</b><small>Uno a uno</small></button>
        <button class="pad hazard" data-scn="blackout" id="hazard"><span class="fill"></span><b style="color:var(--down)">APAGÓN TOTAL</b>&nbsp;&nbsp;<small>mantén presionado</small></button>
    </div>

    <div class="sec"><h2>CANALES</h2>
        <div class="filters">
            <button class="chip" data-f="all" aria-pressed="true">Todos</button>
            <button class="chip" data-f="down" aria-pressed="false">Caídos</button>
            <button class="chip" data-f="wp-degraded" aria-pressed="false">Lentos</button>
        </div>
    </div>
    <div class="strips" id="strips"></div>
</main>
<div id="toast" role="alert"></div>

<script>
(() => {
    const KEY = @json($key), SITES = @json($sites), $ = (id) => document.getElementById(id);
    const q = `?k=${encodeURIComponent(KEY)}`;
    const LABEL = { down: 'caído', 'wp-degraded': 'lento', 'wp-full': 'en línea', 'non-wp': 'en línea', unknown: 'sin datos' };
    const esc = (s) => String(s).replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
    const hhmm = (d = new Date()) => d.toLocaleTimeString('es-CL', { hour12: false });
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));
    const shuffle = (a) => [...a].sort(() => Math.random() - .5);

    let desired = {}, real = new Map(), filter = 'all', running = false;
    const byId = new Map(SITES.map((s) => [s.id, s]));
    const stateOf = (id) => desired[id] || real.get(id)?.state || 'wp-full';
    const up = () => SITES.filter((s) => !desired[s.id]);

    function spark(buckets) {
        const v = (buckets || []).map((b) => b.avg_ms).filter((x) => x != null);
        if (v.length < 2) return '';
        const max = Math.max(...v), min = Math.min(...v), span = Math.max(1, max - min), step = 54 / (v.length - 1);
        return v.map((x, i) => `${(i * step).toFixed(1)},${(14 - ((x - min) / span) * 12).toFixed(1)}`).join(' ');
    }

    function build() {
        $('strips').innerHTML = SITES.map((s, i) => `
            <div class="strip" data-id="${s.id}">
                <div class="ch">CH<b>${String(i + 1).padStart(2, '0')}</b></div>
                <div class="info">
                    <div class="nm">${esc(s.name)}</div>
                    <div class="meta"><span class="ms">—</span><svg class="spark" viewBox="0 0 54 16" preserveAspectRatio="none"><polyline points=""/></svg><span class="ver"></span></div>
                </div>
                <div class="ctl"><button class="slow" data-slow aria-label="Degradar ${esc(s.name)}">~</button><button class="rocker" data-sw aria-label="Encender o apagar ${esc(s.name)}"></button></div>
            </div>`).join('');
    }

    function render(changed = new Set()) {
        let ok = 0, down = 0, warn = 0, lat = [];
        for (const el of $('strips').children) {
            const id = Number(el.dataset.id), r = real.get(id) || {}, st = stateOf(id);
            el.dataset.s = st;
            el.hidden = filter !== 'all' && st !== filter;
            const ms = st === 'down' ? null : (r.last_ms != null ? (st === 'wp-degraded' ? Math.round(r.last_ms * 5) : r.last_ms) : null);
            el.querySelector('.ms').textContent = st === 'down' ? 'SIN RESPUESTA' : ms != null ? `${ms} ms` : '—';
            el.querySelector('.ver').textContent = [r.core_version ? `WP ${r.core_version}` : (r.state === 'non-wp' ? 'no-WP' : ''), r.uptime_24h != null ? `${r.uptime_24h}%` : ''].filter(Boolean).join(' · ');
            el.querySelector('polyline').setAttribute('points', spark(r.sparkline));
            el.querySelector('[data-sw]').setAttribute('aria-pressed', String(st !== 'down'));
            if (changed.has(id)) { el.classList.remove('flash'); void el.offsetWidth; el.classList.add('flash'); }
            if (st === 'down') down++; else if (st === 'wp-degraded') warn++; else ok++;
            if (ms != null) lat.push(ms);
        }
        const total = SITES.length || 1, pct = Math.round((ok / total) * 100);
        $('n-ok').textContent = ok; $('n-down').textContent = down; $('n-warn').textContent = warn;
        $('pct').textContent = `${pct}%`;
        $('lat').innerHTML = lat.length ? `${Math.round(lat.reduce((a, b) => a + b, 0) / lat.length)}<small style="font-size:11px"> ms</small>` : '—';
        const arc = $('arc');
        arc.setAttribute('stroke-dashoffset', String(314.16 * (1 - ok / total)));
        arc.style.stroke = down ? 'var(--down)' : warn ? 'var(--warn)' : 'var(--ok)';
        arc.style.color = arc.style.stroke;
        document.body.dataset.alert = down ? 'down' : '';
    }

    function tick(text, tone) { $('t-time').textContent = hhmm().slice(0, 5); $('t-text').textContent = text; $('ticker').dataset.tone = tone; }
    function toast(msg) { const t = $('toast'); t.textContent = msg; t.classList.add('on'); setTimeout(() => t.classList.remove('on'), 2400); }
    function online(on) { on ? $('link').removeAttribute('data-off') : $('link').setAttribute('data-off', ''); }

    function adopt(next) {
        const changed = new Set();
        for (const s of SITES) if ((desired[s.id] || null) !== (next[s.id] || null)) changed.add(s.id);
        desired = next || {};
        render(changed);
    }

    async function send(body, text, tone) {
        navigator.vibrate?.(tone === 'down' ? [30, 40, 30] : 25);
        try {
            const res = await fetch(`/demo/command${q}`, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
            if (!res.ok) throw new Error(res.status);
            adopt((await res.json()).sites);
            online(true);
            if (text) tick(text, tone);
        } catch { toast('Sin conexión con el monitor'); online(false); }
    }
    const set = (ids, state, text, tone) => send({ ids, state }, text, tone);
    const names = (list) => list.length === 1 ? list[0].name : `${list.length} sitios`;

    const scenarios = {
        green: () => send({ reset: true }, 'Todos los sitios recuperados', 'ok'),
        one: () => { const s = shuffle(up())[0]; return s && set([s.id], 'down', `${s.name} cayó · diagnóstico en pantalla`, 'down'); },
        slow: () => { const l = shuffle(up()).slice(0, 6); return set(l.map((s) => s.id), 'wp-degraded', `${names(l)} con respuesta lenta`, 'warn'); },
        blackout: () => set(SITES.map((s) => s.id), 'down', `Apagón: ${SITES.length} sitios sin respuesta`, 'down'),
        crisis: async () => {
            const p = shuffle(up());
            await set(p.slice(0, 5).map((s) => s.id), 'down');
            await set(p.slice(5, 9).map((s) => s.id), 'wp-degraded', 'Crisis: 5 caídos y 4 lentos', 'down');
        },
        cascade: async () => {
            for (const s of shuffle(up()).slice(0, 6)) { await set([s.id], 'down', `${s.name} cayó`, 'down'); await wait(1400); }
        },
        chain: async () => {
            for (const s of SITES.filter((x) => desired[x.id])) { await set([s.id], null, `${s.name} recuperado`, 'ok'); await wait(1100); }
        },
    };

    async function run(name) {
        if (running) return;
        running = true; document.querySelectorAll('.pad').forEach((x) => { x.disabled = true; });
        try { await scenarios[name](); } finally { running = false; document.querySelectorAll('.pad').forEach((x) => { x.disabled = false; }); }
    }
    document.querySelectorAll('.pad:not(.hazard)').forEach((b) => b.addEventListener('click', () => run(b.dataset.scn)));

    // Blackout needs a deliberate 0.9 s hold so it never fires by accident mid-demo
    const hz = $('hazard'); let hold = null;
    const cancel = () => { clearTimeout(hold); hz.classList.remove('holding'); };
    hz.addEventListener('pointerdown', () => { if (running) return; hz.classList.add('holding'); navigator.vibrate?.(15); hold = setTimeout(() => { cancel(); run('blackout'); }, 900); });
    ['pointerup', 'pointerleave', 'pointercancel'].forEach((ev) => hz.addEventListener(ev, cancel));

    $('strips').addEventListener('click', (e) => {
        const card = e.target.closest('.strip');
        if (!card) return;
        const id = Number(card.dataset.id), s = byId.get(id), cur = desired[id] || null;
        if (e.target.closest('[data-slow]')) {
            return cur === 'wp-degraded' ? set([id], null, `${s.name} vuelve a la normalidad`, 'ok') : set([id], 'wp-degraded', `${s.name} responde lento`, 'warn');
        }
        if (cur === 'down') set([id], null, `${s.name} encendido`, 'ok'); else set([id], 'down', `${s.name} apagado · mira la pantalla`, 'down');
    });

    document.querySelectorAll('.chip').forEach((c) => c.addEventListener('click', () => {
        filter = c.dataset.f;
        document.querySelectorAll('.chip').forEach((x) => x.setAttribute('aria-pressed', String(x === c)));
        render();
    }));

    // /status.json carries both the real telemetry and the demo overlay (same feed the PC reads)
    async function poll() {
        try {
            const d = await (await fetch('/status.json', { headers: { Accept: 'application/json' } })).json();
            real = new Map(d.sites.map((s) => [s.id, s]));
            if (d.demo) adopt(d.demo.sites); else render();
            online(true);
        } catch { online(false); }
    }

    build();
    desired = @json($state)['sites'] || {};
    render();
    poll(); setInterval(poll, 2000);
    const clock = () => { $('clock').textContent = hhmm(); }; clock(); setInterval(clock, 1000);
})();
</script>
</body>
</html>
