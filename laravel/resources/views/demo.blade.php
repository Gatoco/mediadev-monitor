<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mediadev Monitor — Sala de Control (Demo)</title>
  <meta name="description" content="Demo pública del semáforo unificado de MediaDev: 28 sitios WordPress monitoreados sin instalar nada." />
  <meta name="robots" content="noindex" />
  <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml" />
  <link rel="stylesheet" href="{{ asset('css/demo.css') }}" />
</head>
<body>
  <div class="dm-wrap">
    <header class="dm-hero">
      <span class="dm-kicker">Mediadev Monitor · Demo</span>
      <h1 class="dm-h1">El semáforo de<br/>tu cartera web.</h1>
      <p class="dm-sub">{{ $total }} sitios monitoreados ahora mismo — sin instalar nada en ellos. Última medición: {{ $lastCheck }}.</p>
      <div class="dm-cta">
        <a class="dm-btn dm-btn-primary" href="https://mediadev.t420.dev">Volver a la ficha</a>
        <a class="dm-btn dm-btn-ghost" href="/admin">Panel completo</a>
      </div>
    </header>

    <p class="dm-banner">Datos de demostración — portafolio real de mediadev.cl emulado. El panel completo (con edición) requiere login.</p>

    <main aria-label="Estado de los sitios monitoreados">
      @foreach ($groups as $state => $group)
        <section class="dm-group" aria-label="{{ $group['label'] }}">
          <h2 class="dm-group-title"><span class="dm-lamp dm-lamp--{{ $state }}" aria-hidden="true"></span>{{ $group['label'] }} <span class="dm-count">{{ count($group['sites']) }}</span></h2>
          <div class="dm-grid">
            @forelse ($group['sites'] as $site)
              <article class="dm-site-card">
                <span class="dm-lamp dm-lamp--{{ $state }}" aria-hidden="true"></span>
                <span class="dm-name">{{ $site->name }}</span>
                <span class="dm-badge dm-badge--{{ $state }}">{{ $state }}</span>
              </article>
            @empty
              <p class="dm-empty">Ningún sitio en este estado.</p>
            @endforelse
          </div>
        </section>
      @endforeach
    </main>

    <footer class="dm-foot">
      <a href="https://mediadev.t420.dev">mediadev.t420.dev</a>
      <span>·</span>
      <a href="/admin">Panel Filament (login)</a>
      <span>·</span>
      <a href="https://github.com/Gatoco/mediadev-monitor">GitHub</a>
      <span class="dm-note">Demo — se actualiza cada 5 min</span>
    </footer>
  </div>
</body>
</html>