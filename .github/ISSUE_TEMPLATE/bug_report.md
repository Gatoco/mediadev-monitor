---
name: Reporte de error
about: Informa un comportamiento incorrecto de MediaDev Monitor
title: "[BUG] "
labels: bug
assignees: ""
---

## Severidad (semáforo)

Marca una sola opción:

- [ ] 🔴 **Crítico / Rojo**: sitio mal clasificado como caído, monitoreo detenido, pérdida de datos o panel inaccesible.
- [ ] 🟡 **Advertencia / Ámbar**: funcionalidad degradada con solución alternativa, o alerta duplicada o tardía.
- [ ] 🟢 **Consulta / Verde**: detalle menor, duda de uso o mejora de texto.

## Descripción

Qué ocurre, en una o dos frases.

## Comportamiento esperado

Qué debería ocurrir.

## Pasos para reproducir

1. 
2. 
3. 

## Evidencia

- Comando ejecutado y su salida (`php artisan monitor:check all`, `collector:uptime`, etc.):
- Código de salida (`0` / `1` / `2`), si aplica:
- Capturas o fragmentos de `laravel/storage/logs/laravel.log` (sin tokens ni URLs privadas):

## Entorno

- Versión o commit (`git rev-parse --short HEAD`):
- Método de despliegue: Docker / local
- PHP y versión de Laravel:
- Tipo de sitio afectado: WP-FULL / WP-DEGRADED / NON-WP / DOWN (si aplica)

> No pegues Application Passwords, tokens de Telegram ni contenido de `config/sites.php` o `.env`.
