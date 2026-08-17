```
    __  ___         ___           __         
   /  |/  /__  ____/ (_)___ _____/ /__ _   __
  / /|_/ / _ \/ __  / / __ `/ __  / _ \ | / /
 / /  / /  __/ /_/ / / /_/ / /_/ /  __/ |/ / 
/_/  /_/\___/\__,_/_/\__,_/\__,_/\___/|___/  
                                             
    __  ___            _ __            
   /  |/  /___  ____  (_) /_____  _____
  / /|_/ / __ \/ __ \/ / __/ __ \/ ___/
 / /  / / /_/ / / / / / /_/ /_/ / /    
/_/  /_/\____/_/ /_/_/\__/\____/_/     
                                       
```

# Mediadev Monitor

**Framework open source para monitorear múltiples sitios WordPress desde un solo dashboard.**

Mediadev Monitor permite a agencias y dueños de sitios web controlar el estado de todas sus páginas en un solo lugar: disponibilidad (uptime), versiones y updates pendientes, salud del sitio (Site Health), actividad reciente y más — sin instalar ningún plugin en los sitios monitoreados.

> Proyecto académico — Innovación y Emprendimiento III (FGIE03), 2do semestre 2026.
> Cliente real: MediaDev.CL — agencia de diseño web en Talca, Chile (28 sitios monitoreados).

## Características

- **Monitoreo de uptime** — HTTP check cada 5 minutos con umbral de 3 fallos consecutivos
- **Versiones y updates pendientes** — core, plugins y temas con severidad (core=rojo, plugins/temas=amarillo)
- **Site Health** — métricas de salud vía WordPress REST API (Application Passwords)
- **Actividad reciente** — últimas publicaciones por sitio
- **Detección automática** — clasifica WP vs no-WP; degrada a uptime-only cuando REST no está disponible
- **Dashboard web** — semáforo unificado verde/amarillo/rojo, server-rendered PHP
- **CLI** — `mediadev check all` para monitoreo desde terminal
- **Docker** — despliegue en un comando, SQLite embebido, cron integrado

## Stack

| Componente | Tecnología |
|------------|------------|
| Lenguaje | PHP 8+ (puro, sin framework) |
| Gestión de paquetes | Composer (PSR-4 autoload) |
| Base de datos | SQLite (embebido) |
| Dashboard | Server-rendered PHP, sesión nativa |
| CLI | Script PHP propio (`bin/mediadev`) |
| Despliegue | Docker + docker-compose + cron |
| Licencia | MIT |

## Arquitectura

```
 Docker cron (5min / 6h)
       │
       ▼
 bin/collector.php <mode>
       │
       ▼
 Degradation::classify(site) ──probe /wp-json──▶ RestClient ──HTTP──▶ sitios
       │                                         │
       │  state = WP-full|WP-degraded|non-WP|down │
       ▼                                         │
 Uptime / Version / SiteHealth / Activity ◀──────┘
       │  (por eligibilidad según estado)
       ▼
 SQLite (sites, uptime_checks, version_snapshots, site_health_snapshots, activity_snapshots)
       │
       ├──▶ web/  (Dashboard, sesión protegida, server-rendered)
       └──▶ bin/mediadev check all|<url>  (reporte terminal + exit code)
```

## Instalación

```bash
# Clonar
git clone https://github.com/Gatoco/mediadev-monitor.git
cd mediadev-monitor

# Instalar dependencias
composer install

# Configurar sitios (copiar plantilla)
cp config/sites.example.php config/sites.php
# Editar config/sites.php con las URLs y tokens Application Passwords

# Configurar login del dashboard
cp config/auth.example.php config/auth.php
# Definir usuario y password_hash

# Correr la primera recolección
php bin/collector.php uptime
php bin/collector.php deep

# Ver el dashboard
php -S localhost:8080 -t web
```

### Docker

```bash
docker compose up -d
# Dashboard en http://localhost:8080
# Cron configurado: uptime cada 5 min, deep cada 6h
```

## Uso

### CLI

```bash
# Monitorear todos los sitios
bin/mediadev check all

# Monitorear un sitio específico
bin/mediadev check https://diariotalca.cl

# Listar sitios registrados
bin/mediadev list
```

Exit codes: `0` = todo OK · `1` = hay sitios caídos/críticos · `2` = error de uso/config

### Configuración de sitios

```php
<?php
// config/sites.php — gitignored, no subir a repositorio
return [
    [
        'url'   => 'https://mediadev.cl',
        'name'  => 'MediaDev',
        'type'  => 'auto',   // auto | wp | non-wp
        'token' => 'xxxx 24-caracteres application password',  // solo para WP
    ],
];
```

Los **Application Passwords** se generan en cada sitio WordPress: *Usuarios → Perfil → Application Passwords* (WP 5.6+). El tío no necesita instalar ningún plugin.

## Documentación del proyecto

El ciclo SDD completo está documentado en el vault de Obsidian:

- `.sdd/mediadev-client-analysis/exploration.md` — análisis del cliente (28 sitios verificados)
- `.sdd/mediadev-client-analysis/proposal.md` — propuesta del framework
- `.sdd/mediadev-client-analysis/specs/` — 7 specs, 31 requisitos, 50 escenarios
- `.sdd/mediadev-client-analysis/design.md` — diseño técnico
- `.sdd/mediadev-client-analysis/tasks.md` — plan de implementación (36 tareas)

## Roadmap

- [x] Análisis de cliente (28 sitios de MediaDev verificados)
- [x] Propuesta y specs
- [x] Diseño técnico
- [x] Plan de tareas
- [ ] Fase 1: Infraestructura (SQLite, REST client, config)
- [ ] Fase 2: Collectors (uptime, versiones, salud, actividad)
- [ ] Fase 3: Interfaces (CLI, dashboard)
- [ ] Fase 4: Docker + verificación E2E
- [ ] Fase 5: Tests + README

## Licencia

MIT — ver [LICENSE](LICENSE).

---

© 2026 Mediadev Monitor · Proyecto open source para MediaDev.CL
