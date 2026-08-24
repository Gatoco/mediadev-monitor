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
- **Dashboard Filament** — panel admin moderno con semáforo unificado y widgets
- **CLI** — `php artisan monitor:check all` para monitoreo desde terminal
- **Docker** — despliegue en un comando, SQLite embebido, scheduler integrado

## Stack

| Componente | Tecnología |
|------------|------------|
| Framework | Laravel 12 + Filament v4 (PHP 8.3) |
| Base de datos | SQLite (embebido) — Eloquent ORM |
| Dashboard | Filament admin panel (login, SiteResource, widgets) |
| CLI | Artisan commands con exit codes 0/1/2 |
| Despliegue | Docker + docker-compose + Laravel Scheduler |

## Arquitectura

```
 cron (* * * * *) → php artisan schedule:run
                          │
       ┌──────────────────┼──────────────────────┐
       ▼                  ▼                      ▼
 collector:uptime    collector:deep      monitor:check all
  (5 min)            (6 h)
       └──────────────▶ Collector (domain/)
                          │
                          ▼
 Degradation::classify(site) ──probe /wp-json──▶ RestClient ──HTTP──▶ sitios
                          │
 Uptime / Version / SiteHealth / Activity ◀──────┘
                          │
                          ▼
 Eloquent (sites, uptime_checks, version_snapshots, site_health_snapshots, activity_snapshots)
                          │
                          ├──▶ Filament panel /admin (SiteResource + widgets)
                          └──▶ artisan CLI (reporte terminal + exit code)
```

El dominio (`laravel/domain/`) está aislado del framework: clases puras PHP con *repository ports* implementados por adaptadores Eloquent. La lógica de clasificación/severidad/3-strike es byte-por-byte la del framework original verificado.

## Instalación

```bash
# Clonar
git clone https://github.com/Gatoco/mediadev-monitor.git
cd mediadev-monitor/laravel

# Instalar dependencias (requiere PHP 8.3 + ext-intl + ext-pdo_sqlite)
composer install

# Configurar .env y base de datos
cp .env.example .env
# Editar DB_DATABASE (apunta a ../data/mediadev.sqlite por defecto)
php artisan key:generate
php artisan migrate

# Configurar sitios (copiar plantilla en la raíz del repo)
cp ../config/sites.example.php ../config/sites.php
# Editar con las URLs y tokens Application Passwords

# Correr la primera recolección
php artisan collector:uptime
php artisan collector:deep

# Panel administrativo (login: crear un usuario con tinker)
php artisan serve
# http://localhost:8000/admin
```

### Docker

```bash
docker compose up -d
# Dashboard en http://localhost:8080/admin
# Scheduler: uptime cada 5 min, deep cada 6h (una línea de cron)
```

## Uso

### CLI (artisan)

```bash
# Monitorear todos los sitios (recolección profunda)
php artisan monitor:check all

# Uptime (cadencia corta)
php artisan collector:uptime

# Recolección profunda (versiones + salud + actividad)
php artisan collector:deep

# Listar sitios registrados
php artisan monitor:check --list
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

## Verificación E2E

```bash
# Levanta 5 fixtures WP + monitorea + corre 12 checks (requiere --profile e2e)
docker compose --profile e2e up -d
bin/e2e-assert.sh
```

## Roadmap

- [x] Análisis de cliente (28 sitios de MediaDev verificados)
- [x] Propuesta y specs
- [x] Diseño técnico
- [x] Migración a Laravel 12 + Filament v4
- [x] Fase 1: Infraestructura (SQLite, REST client, config)
- [x] Fase 2: Collectors (uptime, versiones, salud, actividad)
- [x] Fase 3: Interfaces (CLI artisan, dashboard Filament)
- [x] Fase 4: Docker + verificación E2E
- [x] Fase 5: Tests + README

## Licencia

MIT — ver [LICENSE](LICENSE).

---

© 2026 Mediadev Monitor · Proyecto open source para MediaDev.CL
