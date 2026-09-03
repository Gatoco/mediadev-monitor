# Proposal: Web Dashboard Hardening + UI Refresh

## Intent

El dashboard legacy (`web/`) que se sirve en `panel.t420.dev` tiene dos problemas:
1. **Seguridad**: sesión PHP nativa sin cookie flags (HttpOnly/Secure/SameSite), sin rate-limit en login, sin headers de seguridad (CSP/X-Frame-Options), y el emoji `🔭`/`✅` rompe la coherencia visual.
2. **Vista**: el dashboard es funcional pero plano; se pide mejorar la vista manteniendo el estilo LM Studio existente.

## Scope

### In Scope
- Quitar emoji (`🔭` en login/layout, `✅` en site.php) del dashboard legacy.
- Endurecer la sesión: cookie flags `HttpOnly` + `Secure` + `SameSite=Lax` en `Auth::startSession()`.
- Añadir headers de seguridad en `web/*.php` (CSP básica, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`).
- Rate-limit simple de login (por IP, en memoria/archivo) para mitigar fuerza bruta.
- Mejorar la vista del dashboard (cards, semáforo, layout) sin cambiar el stack.

### Out of Scope
- Migración a Filament (ya existe en otra rama, no es este cambio).
- Tests PHPUnit (T1 pendiente de Conteso, no bloquea este cambio).
- Seguridad de los sitios WordPress monitoreados (es otro dominio).

## Capabilities

### New Capabilities
- `web-dashboard-security`: headers de seguridad, cookie flags de sesión, rate-limit de login.

### Modified Capabilities
- None (refactor de vista + hardening, sin cambio de comportamiento de specs existentes).

## Approach

1. `Auth::startSession()` → `session_set_cookie_params` con `httponly`, `secure`, `samesite=Lax` antes de `session_start()`.
2. Helper `send_security_headers()` en `web/layout.php` (o un `web/security.php`) incluido en login/index/site/logout.
3. Rate-limit: contador por IP en `data/` (archivo JSON) con ventana de 5 min y máximo 10 intentos.
4. Quitar emoji: `🔭` → logo CSS (dot rojo IBM), `✅` → texto "al día".
5. Mejorar vista: refinar `style.css` (cards, badges, semáforo) manteniendo el shell LM Studio.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `src/Auth/Auth.php` | Modified | Cookie flags de sesión |
| `web/login.php` | Modified | Rate-limit + quitar emoji + headers |
| `web/layout.php` | Modified | Quitar emoji + headers |
| `web/site.php` | Modified | Quitar emoji + headers |
| `web/index.php` | Modified | Headers |
| `web/logout.php` | Modified | Headers |
| `web/style.css` | Modified | Mejora de vista |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| `Secure` cookie rompe login en HTTP local | Med | Solo aplica si `$_SERVER[HTTPS]` o detrás de tunnel HTTPS |
| Rate-limit bloquea al tío | Low | Ventana generosa (10/5min) + mensaje claro |
| Headers CSP rompen algo | Low | CSP permisiva (default-src self) |

## Rollback Plan

`git revert` del commit; los cambios son aislados en `web/` y `src/Auth/Auth.php`.

## Dependencies

- Ninguna externa.

## Success Criteria

- [ ] Sin emoji en `web/` (grep limpio).
- [ ] Login con cookie `HttpOnly; Secure; SameSite=Lax`.
- [ ] Headers de seguridad presentes en todas las respuestas.
- [ ] Rate-limit bloquea tras 10 intentos fallidos en 5 min.
- [ ] Dashboard renderiza correctamente (screenshot).
