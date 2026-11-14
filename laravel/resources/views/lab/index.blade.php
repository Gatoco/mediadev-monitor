<!DOCTYPE html>
<html lang="es-CL">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#080a0f">
<title>SemaOPS Chaos Lab</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #080a0f; --surface: #0f131a; --surface-2: #151a23; --lamp-off: #1b212b;
    --line: rgba(226,232,240,.06); --line-2: rgba(226,232,240,.16); --line-3: rgba(226,232,240,.24);
    --text: #e6eaf0; --text-2: #a3adbd; --muted: #7a8494;
    --ok: #2fb57c; --warn: #e0a23a; --nonwp: #7c8798; --down: #e5484d; --down-soft: #f08a8d;
    --ease: cubic-bezier(.2,.7,.2,1);
    --mono: 'JetBrains Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace;
    --stripes: repeating-linear-gradient(135deg, rgba(226,232,240,.035) 0 8px, transparent 8px 16px);
    --stripes-strong: repeating-linear-gradient(135deg, rgba(226,232,240,.06) 0 6px, transparent 6px 12px);
}
* { box-sizing: border-box; }
html, body { margin: 0; height: 100%; background: var(--bg); }
body { font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif; color: var(--text); -webkit-font-smoothing: antialiased; }
button { font-family: inherit; }
a { color: inherit; }
.mono { font-family: var(--mono); }
.num { font-family: var(--mono); font-variant-numeric: tabular-nums; }

@keyframes semaPulse { 0%,100% { opacity: 1 } 50% { opacity: .4 } }
.sema-pulse { animation: semaPulse 2.4s var(--ease) infinite; }
@keyframes sheetIn { from { transform: translateY(24px); opacity: 0 } to { transform: none; opacity: 1 } }
.sheet { animation: sheetIn .3s var(--ease); }
@media (prefers-reduced-motion: reduce) { .sema-pulse, .sheet { animation: none; } }

.app { position: relative; max-width: 560px; height: 100dvh; margin: 0 auto; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); }
@media (min-width: 600px) { .app { border-left: 1px solid var(--line); border-right: 1px solid var(--line); } }

header.top { flex: none; height: 60px; padding: 0 20px; display: flex; align-items: center; gap: 10px; }
.brand { font-size: 15px; font-weight: 600; }
.brand span { font-weight: 400; color: var(--text-2); }
.btn-restore { margin-left: auto; height: 40px; padding: 0 14px; display: flex; align-items: center; gap: 7px; background: var(--text); color: var(--bg); border: 0; border-radius: 20px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-back { width: 40px; height: 40px; margin-left: -10px; display: flex; align-items: center; justify-content: center; background: transparent; border: 0; color: var(--text); cursor: pointer; border-radius: 20px; }

main { flex: 1; min-height: 0; overflow-y: auto; padding: 12px 20px 24px; display: flex; flex-direction: column; gap: 20px; }
main.tight { gap: 12px; }
main.exp { padding: 8px 24px 24px; gap: 32px; }
h1 { margin: 0; font-size: 28px; font-weight: 600; letter-spacing: -.02em; }
.lead { margin: 0 0 8px; font-size: 15px; line-height: 22px; color: var(--text-2); }

.banner { padding: 12px 14px; border-radius: 12px; border: 1px dashed var(--line-3); background: var(--stripes); font-size: 14px; line-height: 20px; color: var(--text-2); }

.exp-card { display: flex; align-items: center; gap: 14px; padding: 16px; border-radius: 14px; background: var(--surface); text-decoration: none; color: var(--text); border: 0; text-align: left; cursor: pointer; width: 100%; }
.exp-card .meta { flex: 1; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.exp-card .k { font-size: 13px; color: var(--text-2); }
.exp-card .v { font-size: 16px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.exp-card .clock { font-size: 22px; font-weight: 500; }

.tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 4px; background: var(--surface); border-radius: 12px; }
.tabs button { height: 40px; border: 0; border-radius: 9px; background: transparent; color: var(--text-2); font-size: 14.5px; font-weight: 500; cursor: pointer; }
.tabs button[aria-selected="true"] { background: var(--lamp-off); color: var(--text); }

ul.plain { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
ul.cards { gap: 8px; }
.row-card { min-height: 72px; padding: 12px 12px 12px 16px; display: flex; align-items: center; gap: 12px; border-radius: 14px; background: var(--surface); border: 1px solid transparent; }
.row-card.stopped { background: var(--stripes); border: 1px dashed var(--line-3); }
.row-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.row-name { font-family: var(--mono); font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.row-sub { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text-2); }
.row-sub .by-you { color: var(--muted); }
.lamp { width: 8px; height: 8px; flex: none; border-radius: 50%; }
.btn-pill { width: 92px; height: 44px; flex: none; border-radius: 22px; font-size: 14.5px; font-weight: 600; cursor: pointer; background: transparent; color: var(--text); border: 1px solid var(--line-3); }
.btn-pill.solid { background: var(--text); color: var(--bg); border-color: var(--text); }
.btn-pill:disabled { opacity: .5; cursor: progress; }

.row-line { min-height: 64px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--line); }
.row-line .row-name { font-family: inherit; font-size: 15.5px; }
.row-line .row-sub { font-size: 13px; }
.btn-mode { height: 40px; padding: 0 12px; display: flex; align-items: center; gap: 6px; border-radius: 20px; font-size: 14px; font-weight: 500; cursor: pointer; color: var(--text-2); background: transparent; border: 1px solid rgba(226,232,240,.1); white-space: nowrap; }
.btn-mode.injected { color: var(--text); background: var(--stripes-strong); border: 1px dashed rgba(226,232,240,.35); }

nav.bottom { flex: none; height: 64px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-top: 1px solid var(--line); padding-bottom: env(safe-area-inset-bottom); }
nav.bottom button { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; background: transparent; border: 0; color: var(--text-2); font-size: 11.5px; font-weight: 500; cursor: pointer; }
nav.bottom button[aria-current="page"] { color: var(--text); font-weight: 600; }

/* Experimento */
.exp-head { display: flex; flex-direction: column; gap: 8px; }
.exp-kicker { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--text-2); }
.exp-title { margin: 0; font-size: 26px; font-weight: 600; letter-spacing: -.01em; }
.exp-title .mono { font-weight: 500; }
.big-clock { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 8px 0; }
.big-clock .t { font-size: 72px; line-height: 80px; font-weight: 500; letter-spacing: -.03em; }
.big-clock .t.small { font-size: 34px; line-height: 80px; letter-spacing: -.01em; }
.big-clock .s { font-size: 15px; color: var(--text-2); text-align: center; }
ol.checks { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
ol.checks li { display: flex; flex-direction: column; align-items: center; gap: 8px; }
.chk { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.chk.fail { background: rgba(229,72,77,.14); color: var(--down-soft); }
.chk.ok { background: rgba(47,181,124,.14); color: var(--ok); }
.chk.next { border: 2px dashed rgba(226,232,240,.35); }
.chk.wait { border: 2px dashed rgba(226,232,240,.14); }
.chk-l { font-size: 13px; color: var(--text-2); }
.chk-l.now { color: var(--text); }
.btn-big { height: 52px; display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--text); color: var(--bg); border: 0; border-radius: 26px; font-size: 16px; font-weight: 600; cursor: pointer; width: 100%; }
.btn-big:disabled { opacity: .55; cursor: progress; }
.next-list { display: flex; flex-direction: column; gap: 4px; }
.next-list h2 { margin: 0 0 8px; font-size: 14px; font-weight: 500; color: var(--text-2); }
.next-item { min-height: 44px; display: flex; align-items: center; gap: 14px; font-size: 15.5px; }
.next-item .dot { width: 10px; height: 10px; flex: none; border-radius: 50%; border: 1.5px solid; }
.next-item .when { margin-left: auto; font-family: var(--mono); font-size: 14px; color: var(--muted); }
.next-item.done .when { color: var(--text); }
.link-quiet { align-self: center; height: 44px; padding: 0 16px; display: flex; align-items: center; color: var(--text-2); background: transparent; border: 0; font-size: 15px; font-weight: 500; cursor: pointer; }

.verdict { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 12px 0; }
.verdict .badge { width: 72px; height: 72px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--bg); }
.verdict .badge.pass { background: var(--ok); box-shadow: 0 0 24px rgba(47,181,124,.4); }
.verdict .badge.fail { background: var(--down); box-shadow: 0 0 24px rgba(229,72,77,.4); }
.verdict .badge.wait { background: var(--surface-2); color: var(--text); }
.verdict .txt { display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; }
.verdict .txt b { font-size: 28px; font-weight: 600; letter-spacing: -.02em; }
.verdict .txt span { font-size: 15px; color: var(--text-2); }
dl.times { margin: 0; display: flex; flex-direction: column; }
dl.times div { min-height: 52px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--line); }
dl.times dt { font-size: 15px; color: var(--text-2); }
dl.times dd { margin: 0; font-family: var(--mono); font-size: 15px; }

/* Escenarios / historial / alertas */
.scen { width: 100%; min-height: 76px; display: flex; align-items: center; gap: 12px; border: 0; border-bottom: 1px solid var(--line); background: transparent; color: var(--text); text-align: left; cursor: pointer; padding: 0; }
.scen .meta { flex: 1; display: flex; flex-direction: column; gap: 5px; }
.scen .t { font-size: 16px; font-weight: 500; }
.scen .d { font-size: 13.5px; color: var(--text-2); }
.alert-card { padding: 16px; border-radius: 14px; background: var(--surface); display: flex; flex-direction: column; gap: 8px; }
.alert-card .head { display: flex; align-items: center; gap: 10px; }
.alert-card .st { font-size: 16px; font-weight: 600; }
.alert-card .time { margin-left: auto; font-family: var(--mono); font-size: 13.5px; color: var(--muted); }
.alert-card .site { font-family: var(--mono); font-size: 14px; }
.alert-card .detail { font-size: 14px; line-height: 20px; color: var(--text-2); white-space: pre-line; }
.alert-card .ch { font-size: 13px; color: var(--muted); }
.hist-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.btn-ghost { height: 40px; padding: 0 14px; display: flex; align-items: center; gap: 7px; background: transparent; border: 1px solid var(--line-2); border-radius: 20px; color: var(--text); font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; }
.hist-row { min-height: 72px; display: flex; align-items: center; gap: 14px; border-bottom: 1px solid var(--line); border-top: 0; border-left: 0; border-right: 0; background: transparent; color: var(--text); width: 100%; padding: 0; text-align: left; cursor: pointer; }
.hist-ic { width: 32px; height: 32px; flex: none; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.hist-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.hist-main .t { font-size: 15.5px; font-weight: 500; }
.hist-main .g { font-family: var(--mono); font-size: 13px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.hist-res { font-size: 14px; text-align: right; white-space: nowrap; }
.empty { padding: 32px 8px; text-align: center; color: var(--text-2); font-size: 15px; line-height: 22px; }

/* Hojas (bottom sheets) */
.scrim { position: absolute; inset: 0; background: rgba(8,10,15,.75); display: flex; align-items: flex-end; z-index: 10; }
.sheet-box { width: 100%; max-height: 88%; overflow-y: auto; padding: 12px 20px 28px; background: var(--surface); border-radius: 20px 20px 0 0; display: flex; flex-direction: column; gap: 20px; }
.grabber { align-self: center; width: 40px; height: 4px; border-radius: 2px; background: rgba(226,232,240,.18); flex: none; }
.sheet-box h2 { margin: 0; font-size: 21px; font-weight: 600; letter-spacing: -.01em; }
.sheet-box p { margin: 0; font-size: 15px; line-height: 23px; color: var(--text-2); }
.sheet-actions { display: flex; flex-direction: column; gap: 10px; }
.btn-primary { height: 50px; background: var(--text); color: var(--bg); border: 0; border-radius: 25px; font-size: 16px; font-weight: 600; cursor: pointer; }
.btn-secondary { height: 50px; background: transparent; color: var(--text); border: 1px solid var(--line-2); border-radius: 25px; font-size: 16px; font-weight: 500; cursor: pointer; }
.btn-tertiary { height: 44px; background: transparent; color: var(--text-2); border: 0; font-size: 14.5px; font-weight: 500; cursor: pointer; }
.opt { width: 100%; min-height: 56px; padding: 0; display: flex; align-items: center; gap: 14px; background: transparent; border: 0; border-bottom: 1px solid var(--line); color: var(--text); text-align: left; cursor: pointer; }
.opt .meta { flex: 1; display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.opt .n { font-size: 15.5px; font-weight: 500; }
.opt .n.mono { font-size: 15px; }
.opt .d { font-size: 13px; color: var(--muted); }
.radio { width: 22px; height: 22px; flex: none; border-radius: 50%; border: 2px solid rgba(226,232,240,.25); display: flex; align-items: center; justify-content: center; }
.radio span { width: 10px; height: 10px; border-radius: 50%; }
.opt[aria-selected="true"] .radio { border-color: var(--text); }
.opt[aria-selected="true"] .radio span { background: var(--text); }

.toast { position: absolute; left: 20px; right: 20px; bottom: 76px; padding: 12px 14px; border-radius: 12px; background: var(--surface-2); border: 1px solid var(--line-2); font-size: 14px; line-height: 20px; z-index: 20; }
.toast.err { border-color: rgba(229,72,77,.5); }
</style>
</head>
<body>
<div class="app" id="app">
    <header class="top" id="top"></header>
    <main id="view"></main>
    <nav class="bottom" id="nav" aria-label="Secciones"></nav>
</div>

<script>
(() => {
    'use strict';

    const SCENARIOS = @json($scenarios);
    const MODES = @json($modes);
    const EMULATED_COUNT = @json($emulatedCount);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const API = '/lab/api';

    // ---------------------------------------------------------------- iconos
    const svg = (w, vb, inner, extra = '') =>
        `<svg width="${w}" height="${w}" viewBox="${vb}" fill="none" stroke="currentColor" ${extra} aria-hidden="true">${inner}</svg>`;
    const I = {
        logo: '<svg width="20" height="26" viewBox="0 0 48 62" aria-hidden="true"><rect x="12" y="2" width="24" height="42" rx="9" fill="#334155"/><circle cx="24" cy="12" r="5" fill="#dc2626"/><circle cx="24" cy="23" r="5" fill="#f59e0b"/><circle cx="24" cy="34" r="5" fill="#16a34a"/><polyline points="3,54 14,54 18,48 24,59 29,51 32,54 45,54" fill="none" stroke="#16a34a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        restore: svg(14, '0 0 24 24', '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>', 'stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"'),
        chevron: svg(18, '0 0 24 24', '<path d="m9 6 6 6-6 6"/>', 'stroke="#7a8494" stroke-width="2" stroke-linecap="round"'),
        back: svg(22, '0 0 24 24', '<path d="m15 6-6 6 6 6"/>', 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'),
        down: svg(14, '0 0 24 24', '<path d="m6 9 6 6 6-6"/>', 'stroke-width="2" stroke-linecap="round"'),
        bolt: svg(13, '0 0 24 24', '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/>', 'stroke-width="2" stroke-linejoin="round"'),
        x: svg(18, '0 0 24 24', '<path d="M18 6 6 18M6 6l12 12"/>', 'stroke-width="2.6" stroke-linecap="round"'),
        ok: svg(18, '0 0 24 24', '<path d="M20 6 9 17l-5-5"/>', 'stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"'),
        okBig: svg(34, '0 0 24 24', '<path d="M20 6 9 17l-5-5"/>', 'stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"'),
        xBig: svg(32, '0 0 24 24', '<path d="M18 6 6 18M6 6l12 12"/>', 'stroke-width="2.6" stroke-linecap="round"'),
        clockBig: svg(32, '0 0 24 24', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'stroke-width="2" stroke-linecap="round"'),
        refresh: svg(17, '0 0 24 24', '<path d="M21 12a9 9 0 0 1-15.5 6.3L3 16"/><path d="M3 12a9 9 0 0 1 15.5-6.3L21 8"/><path d="M21 3v5h-5M3 21v-5h5"/>', 'stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"'),
        export: svg(15, '0 0 24 24', '<path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/>', 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'),
        histOk: svg(16, '0 0 24 24', '<path d="M20 6 9 17l-5-5"/>', 'stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"'),
        histFail: svg(15, '0 0 24 24', '<path d="M18 6 6 18M6 6l12 12"/>', 'stroke-width="2.8" stroke-linecap="round"'),
        histRun: svg(16, '0 0 24 24', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'stroke-width="2.2" stroke-linecap="round"'),
        navHome: svg(20, '0 0 24 24', '<path d="M21 8 12 3 3 8v8l9 5 9-5V8z"/><path d="m3 8 9 5 9-5M12 13v8"/>', 'stroke-width="2" stroke-linejoin="round"'),
        navScen: svg(20, '0 0 24 24', '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/>', 'stroke-width="2" stroke-linejoin="round"'),
        navAlert: svg(20, '0 0 24 24', '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>', 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'),
        navHist: svg(20, '0 0 24 24', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'stroke-width="2" stroke-linecap="round"'),
    };

    // Estado que ve el monitor → luz del semáforo.
    const LAMPS = {
        'wp-full': { c: '#2fb57c', s: '0 0 8px rgba(47,181,124,.55)', l: 'OK' },
        'wp-degraded': { c: '#e0a23a', s: '0 0 8px rgba(224,162,58,.55)', l: 'Degradado' },
        'non-wp': { c: '#7c8798', s: '0 0 6px rgba(124,135,152,.4)', l: 'No-WP' },
        'down': { c: '#e5484d', s: '0 0 10px rgba(229,72,77,.6)', l: 'Caído', pulse: true },
        'unknown': { c: '#1b212b', s: 'none', l: 'Sin datos' },
        'unmonitored': { c: '#1b212b', s: 'none', l: 'Sin monitorear' },
    };
    const lampOf = (state) => LAMPS[state] || LAMPS.unknown;

    // ---------------------------------------------------------------- helpers
    function h(tag, attrs = {}, ...children) {
        const el = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (v === null || v === undefined || v === false) continue;
            if (k === 'class') el.className = v;
            else if (k === 'style') el.style.cssText = v;
            else if (k === 'html') el.innerHTML = v; // solo SVG estáticos de I
            else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
            else el.setAttribute(k, v === true ? '' : v);
        }
        for (const c of children.flat()) {
            if (c === null || c === undefined || c === false) continue;
            el.append(c instanceof Node ? c : document.createTextNode(String(c)));
        }
        return el;
    }
    const icon = (markup) => h('span', { html: markup, style: 'display:flex' });
    const lampEl = (state, size = 8) => {
        const l = lampOf(state);
        return h('span', { class: 'lamp' + (l.pulse ? ' sema-pulse' : ''), style: `width:${size}px;height:${size}px;background:${l.c};box-shadow:${l.s}` });
    };
    const hhmm = (iso) => iso ? new Date(iso).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', hour12: false }) : '—';
    const hhmmss = (iso) => iso ? new Date(iso).toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }) : '—';
    const mmss = (ms) => {
        const s = Math.max(0, Math.round(ms / 1000));
        return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    };
    const channelsText = (ch, delivered) => {
        if (!ch) return 'No salió: no hay canales configurados';
        const names = ch.split(',').map((c) => ({ telegram: 'Telegram', email: 'email', stdout: 'consola' }[c] || c));
        return names.length > 1 ? names.slice(0, -1).join(', ') + ' y ' + names.at(-1) : names[0];
    };

    async function api(path, opts = {}) {
        const res = await fetch(API + path, {
            method: opts.method || 'GET',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: opts.body ? JSON.stringify(opts.body) : undefined,
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || data.message || `Error ${res.status}`);
        return data;
    }

    // ---------------------------------------------------------------- estado
    const S = {
        route: 'home',          // home | experiment | scenarios | alerts | history
        tab: 'containers',      // containers | sites
        data: null,             // /state
        experiment: null,       // experimento abierto en la vista
        alerts: null,
        history: null,
        sheet: null,            // { kind, ... }
        busy: false,
        error: null,
    };

    function toast(msg, isErr = true) {
        document.querySelector('.toast')?.remove();
        const t = h('div', { class: 'toast' + (isErr ? ' err' : ''), role: 'status' }, msg);
        document.getElementById('app').append(t);
        setTimeout(() => t.remove(), 5000);
    }

    async function run(fn) {
        if (S.busy) return;
        S.busy = true; render();
        try { await fn(); } catch (e) { toast(e.message); }
        S.busy = false;
        await refresh();
    }

    async function refresh() {
        try {
            if (S.route === 'alerts') { S.alerts = (await api('/alerts')).alerts; render(); }
            if (S.route === 'history') { S.history = (await api('/history')).experiments; render(); }
            S.data = await api('/state');
            if (S.route === 'experiment' && S.experiment) {
                S.experiment = await api('/experiments/' + S.experiment.id);
            }
            S.error = null;
        } catch (e) {
            S.error = e.message;
        }
        render();
    }

    const HASH = { home: 'inicio', experiment: 'experimento', scenarios: 'escenarios', alerts: 'alertas', history: 'historial' };

    function go(route, extra = {}) {
        Object.assign(S, { route, sheet: null }, extra);
        const hash = '#' + HASH[route] + (route === 'experiment' && S.experiment ? '/' + S.experiment.id : '');
        if (location.hash !== hash) history.replaceState(null, '', hash);
        document.getElementById('view').scrollTop = 0;
        render();
        refresh();
    }

    // ---------------------------------------------------------------- acciones
    const act = {
        stop: (service) => run(async () => {
            const r = await api(`/containers/${encodeURIComponent(service)}/stop`, { method: 'POST' });
            S.sheet = null;
            if (r.experiment) go('experiment', { experiment: r.experiment });
        }),
        start: (service) => run(() => api(`/containers/${encodeURIComponent(service)}/start`, { method: 'POST' })),
        restart: (service) => run(async () => {
            await api(`/containers/${encodeURIComponent(service)}/restart`, { method: 'POST' });
            S.sheet = null;
            toast(`${service} reiniciado`, false);
        }),
        mode: (host, mode) => run(async () => {
            const r = await api(`/sites/${encodeURIComponent(host)}/mode`, { method: 'POST', body: { mode } });
            S.sheet = null;
            if (r.experiment) go('experiment', { experiment: r.experiment });
        }),
        scenario: (key, target) => run(async () => {
            const r = await api(`/scenarios/${encodeURIComponent(key)}`, { method: 'POST', body: { target } });
            S.sheet = null;
            go('experiment', { experiment: r.experiment });
        }),
        restoreAll: () => run(async () => {
            await api('/restore', { method: 'POST' });
            S.sheet = null;
            toast('Todo restaurado: contenedores arriba y sitios en Normal.', false);
        }),
        check: (deep) => run(async () => {
            await api('/check', { method: 'POST', body: { deep } });
            toast(deep ? 'Análisis profundo en curso…' : 'Chequeo forzado en curso…', false);
        }),
        finish: (id) => run(async () => {
            S.experiment = await api(`/experiments/${id}/finish`, { method: 'POST' });
        }),
    };

    // ---------------------------------------------------------------- vistas
    function renderTop() {
        const top = document.getElementById('top');
        top.replaceChildren(...[
            S.route === 'experiment'
                ? h('button', { class: 'btn-back', 'aria-label': 'Volver', onclick: () => go('home'), html: I.back })
                : null,
            icon(I.logo),
            h('span', { class: 'brand' }, 'SemaOPS ', h('span', {}, 'Chaos Lab')),
            h('button', { class: 'btn-restore', onclick: () => { S.sheet = { kind: 'restore' }; render(); } }, icon(I.restore), 'Restaurar todo'),
        ].filter(Boolean));
    }

    function renderNav() {
        const items = [['home', 'Inicio', I.navHome], ['scenarios', 'Escenarios', I.navScen], ['alerts', 'Alertas', I.navAlert], ['history', 'Historial', I.navHist]];
        const current = S.route === 'experiment' ? 'home' : S.route;
        document.getElementById('nav').replaceChildren(...items.map(([r, label, ic]) =>
            h('button', { 'aria-current': current === r ? 'page' : null, onclick: () => go(r) }, icon(ic), label)));
    }

    function experimentSummary(e) {
        if (!e) return null;
        const next = new Date(e.next_check_at) - Date.now();
        let what;
        if (e.restored_at) what = 'esperando recuperación';
        else if (e.detected_at) what = e.alerted_at ? 'detectado y avisado' : 'detectado · esperando alerta';
        else what = { down: 'esperando Caído', 'wp-degraded': 'esperando Degradado', none: 'vigilando falsos positivos' }[e.expected];
        return h('button', { class: 'exp-card', onclick: () => go('experiment', { experiment: e }) },
            h('span', { class: 'meta' },
                h('span', { class: 'k' }, 'Experimento en curso'),
                h('span', { class: 'v' }, h('span', { class: 'mono' }, e.target_label), ' · ', what)),
            h('span', { class: 'num clock' }, S.data?.checking ? '…' : mmss(next)),
            icon(I.chevron));
    }

    function viewHome() {
        const d = S.data;
        const main = [h('h1', {}, 'Inicio')];

        if (d && !d.agent_ok) {
            main.push(h('div', { class: 'banner' }, 'El chaos-agent no responde: no se puede ver ni cambiar el estado de los contenedores. Revisa que el servicio ', h('span', { class: 'mono' }, 'chaos-agent'), ' esté arriba.'));
        }
        if (S.error) main.push(h('div', { class: 'banner' }, 'Sin conexión con el lab: ' + S.error));

        main.push(experimentSummary(d?.experiment));

        main.push(h('div', { class: 'tabs', role: 'tablist', 'aria-label': 'Objetivos' },
            h('button', { role: 'tab', 'aria-selected': String(S.tab === 'containers'), onclick: () => { S.tab = 'containers'; render(); } }, 'Contenedores'),
            h('button', { role: 'tab', 'aria-selected': String(S.tab === 'sites'), onclick: () => { S.tab = 'sites'; render(); } }, 'Sitios')));

        if (!d) {
            main.push(h('p', { class: 'empty' }, 'Cargando…'));
        } else if (S.tab === 'containers') {
            main.push(h('ul', { class: 'plain cards' }, d.containers.map((c) => {
                const stopped = d.agent_ok && c.container_state !== 'running';
                const missing = c.container_state === 'missing';
                const lamp = lampOf(c.monitor_state);
                return h('li', { class: 'row-card' + (stopped ? ' stopped' : '') },
                    h('span', { class: 'row-main' },
                        h('span', { class: 'row-name' }, c.service),
                        h('span', { class: 'row-sub' }, lampEl(c.monitor_state), lamp.l,
                            c.sites > 1 ? h('span', { class: 'by-you' }, `· ${c.sites} sitios`) : null,
                            missing ? h('span', { class: 'by-you' }, '· no existe') : stopped ? h('span', { class: 'by-you' }, '· bajado por ti') : null)),
                    h('button', {
                        class: 'btn-pill' + (stopped ? ' solid' : ''),
                        disabled: S.busy || !d.agent_ok || missing,
                        onclick: () => stopped ? act.start(c.service) : (S.sheet = { kind: 'stop', service: c.service, sites: c.sites }, render()),
                    }, stopped ? 'Subir' : 'Bajar'));
            })));
        } else {
            main.push(h('p', { style: 'margin:0;font-size:14px;line-height:21px;color:var(--text-2)' },
                `${EMULATED_COUNT} sitios en `, h('span', { class: 'mono' }, 'portfolio-emulator'), '. Toca el modo para provocar una falla.'));
            main.push(h('ul', { class: 'plain' }, d.sites.map((s) => {
                const injected = s.mode !== 'normal';
                const mode = MODES.find((m) => m.key === s.mode) || MODES[0];
                const lamp = lampOf(s.monitor_state);
                const waiting = injected && s.monitor_state !== 'down' && s.monitor_state !== 'wp-degraded';
                return h('li', { class: 'row-line' },
                    h('span', { class: 'row-main', style: 'gap:5px' },
                        h('span', { class: 'row-name' }, s.host),
                        h('span', { class: 'row-sub' }, lampEl(s.monitor_state), lamp.l + (waiting ? ' · esperando' : ''),
                            s.failures > 0 && s.monitor_state !== 'down' ? h('span', { class: 'by-you num' }, `· ${s.failures}/3`) : null)),
                    h('button', {
                        class: 'btn-mode' + (injected ? ' injected' : ''),
                        'aria-haspopup': 'dialog',
                        'aria-label': `Modo de falla de ${s.host}: ${mode.name}`,
                        onclick: () => { S.sheet = { kind: 'mode', host: s.host, current: s.mode }; render(); },
                    }, injected ? icon(I.bolt) : null, mode.name, icon(I.down)));
            })));
        }
        return main;
    }

    function viewExperiment() {
        const e = S.experiment;
        if (!e || !e.status) return [h('p', { class: 'empty' }, 'Cargando…')];
        return e.status === 'finished' ? viewExperimentDone(e) : viewExperimentRunning(e);
    }

    function viewExperimentRunning(e) {
        const checking = S.data?.checking;
        const needed = e.checks_needed;
        // Racha de fallos desde la inyección (lo que cuenta para el umbral).
        let streak = 0;
        for (const c of e.checks) streak = c.ok ? 0 : streak + 1;
        const recent = e.checks.slice(-needed);
        const nextIso = e.next_check_at;

        const kicker = h('span', { class: 'exp-kicker' },
            h('span', { class: 'sema-pulse', style: 'width:7px;height:7px;border-radius:50%;background:var(--text)' }),
            `${e.scenario_label} · ${e.restored_at ? 'restaurado' : 'en curso'}`);
        const title = h('h1', { class: 'exp-title' },
            h('span', { class: 'mono' }, e.target_label), ' ',
            e.fault === 'stop' ? 'bajado' : `en ${e.fault_label}`);

        // Reloj grande
        let clock, sub;
        if (checking) { clock = h('span', { class: 'num t small' }, 'Chequeando…'); sub = 'el monitor está consultando los sitios'; }
        else if (e.restored_at) { clock = h('span', { class: 'num t' }, mmss(new Date(nextIso) - Date.now())); sub = 'para confirmar la recuperación'; }
        else if (e.detected_at) { clock = h('span', { class: 'num t small' }, 'Detectado'); sub = `en ${e.detection_minutes} min · ${hhmm(e.detected_at)}`; }
        else if (e.expected === 'wp-degraded') { clock = h('span', { class: 'num t small' }, 'Falta análisis'); sub = 'el degradado se detecta en el análisis profundo (cada 6 h)'; }
        else {
            clock = h('span', { class: 'num t' }, mmss(new Date(nextIso) - Date.now()));
            sub = e.expected === 'none' ? 'para el próximo chequeo' : `para el chequeo ${Math.min(streak + 1, needed)} de ${needed}`;
        }

        const checks = e.expected === 'wp-degraded' ? null : h('ol', { class: 'checks', 'aria-label': 'Chequeos' },
            Array.from({ length: needed }, (_, i) => {
                const c = recent[i];
                if (c) return h('li', {},
                    h('span', { class: 'chk ' + (c.ok ? 'ok' : 'fail'), html: c.ok ? I.ok : I.x }),
                    h('span', { class: 'chk-l' }, c.ok ? 'Respondió ' : 'Falló ', h('span', { class: 'mono' }, hhmm(c.ts))));
                const isNext = i === recent.length;
                return h('li', {},
                    h('span', { class: 'chk ' + (isNext ? 'next' : 'wait') },
                        isNext ? h('span', { class: 'sema-pulse', style: 'width:8px;height:8px;border-radius:50%;background:var(--text)' }) : null),
                    h('span', { class: 'chk-l' + (isNext ? ' now' : '') }, isNext ? 'Próximo ' : 'Luego ',
                        h('span', { class: 'mono' }, isNext ? hhmm(nextIso) : '—')));
            }));

        const deep = e.needs_deep && !e.detected_at;
        const force = h('button', { class: 'btn-big', disabled: checking || S.busy, onclick: () => act.check(deep) },
            icon(I.refresh), checking ? 'Chequeando…' : deep ? 'Forzar análisis ahora' : 'Forzar chequeo ahora');

        const step = (label, color, at, extra) => h('div', { class: 'next-item' + (at ? ' done' : '') },
            h('span', { class: 'dot', style: `border-color:${color};${at ? `background:${color}` : ''}` }), label,
            h('span', { class: 'when' }, at ? hhmm(at) : (extra || '')));

        const steps = h('div', { class: 'next-list' }, h('h2', {}, 'Qué sigue'));
        if (e.expected === 'down') {
            const eta = new Date(new Date(nextIso).getTime() + Math.max(0, needed - streak - 1) * 5 * 60000).toISOString();
            steps.append(
                step('Caído detectado', '#e5484d', e.detected_at, '≈ ' + hhmm(eta)),
                step(e.alerted_at ? `Alerta por ${channelsText(e.alert_channels)}` : 'Alerta por Telegram y email', 'rgba(226,232,240,.35)', e.alerted_at),
                step(e.target_type === 'container' ? 'Recuperado al subirlo' : 'Recuperado al restaurarlo', '#2fb57c', e.recovered_at));
        } else if (e.expected === 'wp-degraded') {
            steps.append(step('Degradado detectado', '#e0a23a', e.detected_at, 'al forzar análisis'));
        } else {
            steps.append(step('Sin alerta de caída mientras dure la falla', '#2fb57c', null, 'al terminar'));
        }

        return [
            h('div', { class: 'exp-head' }, kicker, title),
            h('div', { class: 'big-clock' }, clock, h('span', { class: 's' }, sub)),
            checks, force, steps,
            e.restored_at ? null : h('button', { class: 'link-quiet', disabled: S.busy, onclick: () => act.finish(e.id) },
                e.target_type === 'container' ? 'Subir y terminar' : 'Restaurar y terminar'),
        ];
    }

    function viewExperimentDone(e) {
        const pass = e.result === 'pass';
        let headline, text;
        if (e.expected === 'none') {
            headline = pass ? 'Sin falsos positivos' : 'Alertó una falla intermitente';
            text = pass ? 'El monitor no avisó caídas por fallas aisladas.' : 'Se envió una alerta de caída que no correspondía.';
        } else if (e.detected_at) {
            headline = `Detectado en ${e.detection_minutes} min`;
            text = e.expected === 'wp-degraded' ? 'El análisis lo clasificó como Degradado.'
                : e.alerted_at ? (e.alert_channels ? `La alerta llegó por ${channelsText(e.alert_channels)}.` : 'La alerta se generó, pero no hay canales configurados.')
                : 'No se registró la alerta.';
        } else {
            headline = 'No detectado';
            text = 'El monitor no marcó la falla antes de que se restaurara.';
        }

        const row = (k, v) => h('div', {}, h('dt', {}, k), h('dd', {}, v ? hhmmss(v) : '—'));

        return [
            h('div', { class: 'exp-head' },
                h('span', { class: 'exp-kicker' }, `${e.scenario_label} · terminado`),
                h('h1', { class: 'exp-title mono', style: 'font-size:22px;font-weight:500' }, e.targets.length > 1 ? e.targets.join(', ') : e.target_label)),
            h('div', { class: 'verdict' },
                h('span', { class: 'badge ' + (pass ? 'pass' : 'fail'), html: pass ? I.okBig : I.xBig }),
                h('div', { class: 'txt' }, h('b', {}, headline), h('span', {}, text))),
            h('dl', { class: 'times' },
                row('Falla provocada', e.injected_at),
                e.expected === 'none' ? null : row(e.expected === 'wp-degraded' ? 'Degradado detectado' : 'Caído detectado', e.detected_at),
                e.expected === 'down' ? row('Alerta enviada', e.alerted_at) : null,
                row('Restaurado', e.restored_at),
                e.expected === 'down' ? row('Recuperado', e.recovered_at) : null),
            h('button', { class: 'btn-secondary', style: 'margin-top:auto', onclick: () => go('scenarios') }, 'Otro escenario'),
        ];
    }

    function viewScenarios() {
        return [
            h('h1', {}, 'Escenarios'),
            h('p', { class: 'lead' }, 'Elige uno y la app cronometra cuánto tarda el monitor en avisar.'),
            h('ul', { class: 'plain' }, SCENARIOS.map((s) => h('li', {},
                h('button', { class: 'scen', onclick: () => { S.sheet = { kind: 'scenario', scenario: s }; render(); } },
                    h('span', { class: 'meta' }, h('span', { class: 't' }, s.t), h('span', { class: 'd' }, s.d)),
                    icon(I.chevron))))),
        ];
    }

    function viewAlerts() {
        const list = S.alerts;
        return [
            h('h1', {}, 'Alertas'),
            h('p', { class: 'lead' }, 'Así llegan a Telegram y al correo.'),
            !list ? h('p', { class: 'empty' }, 'Cargando…')
                : list.length === 0 ? h('p', { class: 'empty' }, 'Todavía no se ha enviado ninguna alerta.')
                : h('ul', { class: 'plain', style: 'gap:10px' }, list.map((a) => {
                    const down = a.kind === 'down';
                    const detail = a.text.split('\n').slice(1).join('\n').trim() || a.text;
                    return h('li', { class: 'alert-card' },
                        h('span', { class: 'head' },
                            h('span', { class: down ? 'sema-pulse' : '', style: `width:9px;height:9px;border-radius:50%;background:${down ? '#e5484d' : '#2fb57c'};box-shadow:0 0 9px ${down ? 'rgba(229,72,77,.6)' : 'rgba(47,181,124,.5)'}` }),
                            h('span', { class: 'st' }, down ? 'Caído' : 'Recuperado'),
                            h('span', { class: 'time' }, hhmm(a.sent_at))),
                        h('span', { class: 'site' }, a.site || ''),
                        h('span', { class: 'detail' }, detail.length > 220 ? detail.slice(0, 220) + '…' : detail),
                        h('span', { class: 'ch' }, channelsText(a.channels, a.delivered)));
                })),
        ];
    }

    function viewHistory() {
        const list = S.history;
        const today = (list || []).filter((e) => new Date(e.injected_at).toDateString() === new Date().toDateString());
        const done = today.filter((e) => e.status === 'finished');
        const passed = done.filter((e) => e.result === 'pass');
        return [
            h('div', { class: 'hist-head' }, h('h1', {}, 'Historial'),
                h('a', { class: 'btn-ghost', href: '/lab/historial.csv', download: true }, icon(I.export), 'Exportar')),
            h('p', { class: 'lead', style: 'margin:0 0 8px' }, done.length ? `Hoy: ${passed.length} de ${done.length} aprobados` : 'Hoy no hay experimentos terminados'),
            !list ? h('p', { class: 'empty' }, 'Cargando…')
                : list.length === 0 ? h('p', { class: 'empty' }, 'Aún no hay experimentos. Lanza uno desde Escenarios.')
                : h('ul', { class: 'plain' }, list.map((e) => {
                    const run = e.status !== 'finished', pass = e.result === 'pass';
                    const st = run ? { bg: '#151a23', ic: '#e6eaf0', i: I.histRun, l: 'En curso', c: '#a3adbd' }
                        : pass ? { bg: 'rgba(47,181,124,.14)', ic: '#2fb57c', i: I.histOk, l: 'Aprobado', c: '#e6eaf0' }
                        : { bg: 'rgba(229,72,77,.14)', ic: '#f08a8d', i: I.histFail, l: 'Fallido', c: '#e6eaf0' };
                    return h('li', {}, h('button', { class: 'hist-row', onclick: () => go('experiment', { experiment: e }) },
                        h('span', { class: 'hist-ic', style: `background:${st.bg};color:${st.ic}`, role: 'img', 'aria-label': st.l, html: st.i }),
                        h('span', { class: 'hist-main' }, h('span', { class: 't' }, e.scenario_label), h('span', { class: 'g' }, `${e.target_label} · ${hhmm(e.injected_at)}`)),
                        h('span', { class: 'hist-res', style: `color:${st.c}` }, e.result_label)));
                })),
        ];
    }

    // ---------------------------------------------------------------- hojas
    function sheetFrame(titleId, ...children) {
        const close = () => { S.sheet = null; render(); };
        return h('div', { class: 'scrim', onclick: (ev) => { if (ev.target === ev.currentTarget) close(); } },
            h('div', { class: 'sheet sheet-box', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId },
                h('span', { class: 'grabber', 'aria-hidden': 'true' }), ...children));
    }

    function renderSheet() {
        const sh = S.sheet;
        if (!sh) return null;
        const close = () => { S.sheet = null; render(); };

        if (sh.kind === 'restore') {
            const d = S.data;
            const stopped = d ? d.containers.filter((c) => d.agent_ok && c.container_state !== 'running' && c.container_state !== 'missing').length : 0;
            const injected = d ? d.sites.filter((s) => s.mode !== 'normal').length : 0;
            const parts = [];
            parts.push(`Se ${stopped === 1 ? 'sube' : 'suben'} ${stopped} ${stopped === 1 ? 'contenedor' : 'contenedores'}`);
            parts.push(injected ? `${injected} ${injected === 1 ? 'sitio vuelve' : 'sitios vuelven'} a modo Normal.` : 'los sitios vuelven a modo Normal.');
            return sheetFrame('sh-t',
                h('div', { style: 'display:flex;flex-direction:column;gap:8px' }, h('h2', { id: 'sh-t' }, '¿Restaurar todo?'), h('p', {}, parts.join(' y '))),
                h('div', { class: 'sheet-actions' },
                    h('button', { class: 'btn-primary', disabled: S.busy, onclick: act.restoreAll }, 'Restaurar todo'),
                    h('button', { class: 'btn-secondary', onclick: close }, 'Cancelar')));
        }

        if (sh.kind === 'stop') {
            const emu = sh.service === 'portfolio-emulator';
            return sheetFrame('sh-t',
                h('div', { style: 'display:flex;flex-direction:column;gap:8px' },
                    h('h2', { id: 'sh-t' }, emu ? '¿Bajar el emulador?' : `¿Bajar ${sh.service}?`),
                    h('p', {}, emu ? `Caen los ${sh.sites} sitios a la vez. El monitor debería marcarlos Caído en 15 min o menos.` : 'El monitor debería marcarlo Caído en 15 min o menos.')),
                h('div', { class: 'sheet-actions' },
                    h('button', { class: 'btn-primary', disabled: S.busy, onclick: () => act.stop(sh.service) }, emu ? 'Bajar emulador' : 'Bajar'),
                    h('button', { class: 'btn-secondary', onclick: close }, 'Cancelar'),
                    h('button', { class: 'btn-tertiary', disabled: S.busy, onclick: () => act.restart(sh.service) }, 'Reiniciar en lugar de bajar')));
        }

        if (sh.kind === 'mode') {
            return sheetFrame('sh-t',
                h('div', { style: 'display:flex;flex-direction:column;gap:4px' }, h('h2', { id: 'sh-t' }, 'Modo de falla'), h('span', { class: 'mono', style: 'font-size:14px;color:var(--text-2)' }, sh.host)),
                h('ul', { class: 'plain', role: 'listbox', 'aria-label': 'Modo de falla' }, MODES.map((m) => h('li', {},
                    h('button', { class: 'opt', role: 'option', 'aria-selected': String(m.key === sh.current), disabled: S.busy, onclick: () => m.key === sh.current ? close() : act.mode(sh.host, m.key) },
                        h('span', { class: 'meta' }, h('span', { class: 'n' }, m.name), h('span', { class: 'd' }, m.desc)),
                        h('span', { class: 'radio' }, h('span')))))),
                h('button', { class: 'btn-secondary', onclick: close }, 'Cerrar'));
        }

        if (sh.kind === 'scenario') {
            const s = sh.scenario;
            if (s.key === 'masivo') {
                return sheetFrame('sh-t',
                    h('div', { style: 'display:flex;flex-direction:column;gap:8px' }, h('h2', { id: 'sh-t' }, '¿Lanzar incidente masivo?'),
                        h('p', {}, '5 sitios al azar quedan sin respuesta a la vez. El monitor debería marcarlos todos Caído y avisar por cada uno.')),
                    h('div', { class: 'sheet-actions' },
                        h('button', { class: 'btn-primary', disabled: S.busy, onclick: () => act.scenario('masivo', null) }, 'Lanzar incidente'),
                        h('button', { class: 'btn-secondary', onclick: close }, 'Cancelar')));
            }
            const d = S.data;
            const options = s.type === 'container'
                ? (d?.containers || []).filter((c) => c.container_state === 'running').map((c) => ({ v: c.service, n: c.service, d: c.sites > 1 ? `${c.sites} sitios` : lampOf(c.monitor_state).l }))
                : [{ v: null, n: 'Al azar', d: 'Un sitio en modo Normal' }].concat((d?.sites || []).filter((x) => x.mode === 'normal').map((x) => ({ v: x.host, n: x.host, d: lampOf(x.monitor_state).l })));
            return sheetFrame('sh-t',
                h('div', { style: 'display:flex;flex-direction:column;gap:4px' }, h('h2', { id: 'sh-t' }, s.t), h('span', { style: 'font-size:14px;color:var(--text-2)' }, s.type === 'container' ? '¿Qué contenedor bajamos?' : '¿En qué sitio?')),
                options.length === 0 ? h('p', {}, 'No hay objetivos disponibles. Usa Restaurar todo.')
                    : h('ul', { class: 'plain' }, options.map((o) => h('li', {},
                        h('button', { class: 'opt', disabled: S.busy, onclick: () => act.scenario(s.key, o.v) },
                            h('span', { class: 'meta' }, h('span', { class: 'n' + (o.v ? ' mono' : '') }, o.n), h('span', { class: 'd' }, o.d)),
                            icon(I.chevron))))),
                h('button', { class: 'btn-secondary', onclick: close }, 'Cancelar'));
        }
        return null;
    }

    // ---------------------------------------------------------------- render
    function render() {
        renderTop();
        renderNav();
        const view = document.getElementById('view');
        const views = { home: viewHome, experiment: viewExperiment, scenarios: viewScenarios, alerts: viewAlerts, history: viewHistory };
        view.className = S.route === 'experiment' ? 'exp' : S.route === 'home' ? '' : 'tight';
        const scroll = view.scrollTop;
        view.replaceChildren(...views[S.route]().filter(Boolean));
        view.scrollTop = scroll;
        // La hoja solo se re-crea si cambió (evita repetir la animación en cada tick).
        if (S.sheet !== renderedSheet || S.busy !== renderedBusy || !document.querySelector('.scrim')) {
            document.querySelector('.scrim')?.remove();
            const sheet = renderSheet();
            if (sheet) document.getElementById('app').append(sheet);
            renderedSheet = S.sheet;
            renderedBusy = S.busy;
        }
    }
    let renderedSheet = null, renderedBusy = false;

    // Reloj: repinta cada segundo (cuentas regresivas) y consulta cada 5 s.
    let tick = 0;
    setInterval(() => {
        tick++;
        if (tick % 5 === 0) refresh();
        else if (S.route === 'experiment' || S.route === 'home') render();
    }, 1000);

    // Ruta inicial desde la URL (#escenarios, #experimento/12, …).
    const [name, id] = location.hash.slice(1).split('/');
    const initial = Object.keys(HASH).find((k) => HASH[k] === name) || 'home';
    if (initial === 'experiment' && id) S.experiment = { id: Number(id) };
    S.route = initial === 'experiment' && !id ? 'home' : initial;

    render();
    refresh();
})();
</script>
</body>
</html>
