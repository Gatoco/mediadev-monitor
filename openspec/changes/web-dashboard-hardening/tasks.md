# Tasks: Web Dashboard Hardening + UI Refresh

## T1: Cookie flags de sesión
- [ ] `src/Auth/Auth.php` — `session_set_cookie_params` con httponly/secure/samesite en `startSession()`.

## T2: Security headers
- [ ] Crear `web/security.php` con `send_security_headers()`.
- [ ] Incluir en `login.php`, `index.php`, `site.php`, `logout.php` (y layout).

## T3: Rate-limit de login
- [ ] Crear `src/Auth/RateLimit.php`.
- [ ] Integrar en `web/login.php` (check antes de verificar credenciales, recordFailure en fallo, reset en éxito).

## T4: Quitar emoji
- [ ] `web/login.php` — quitar `🔭`, usar dot CSS.
- [ ] `web/layout.php` — quitar `🔭`, usar dot CSS.
- [ ] `web/site.php` — reemplazar `✅` por texto/badge.

## T5: Mejorar vista
- [ ] `web/style.css` — cards con borde semáforo, badges, login con marca.

## T6: Verificación
- [ ] `php -l` en todos los archivos modificados.
- [ ] Screenshot del dashboard (login + index + site).
- [ ] Grep sin emoji.
- [ ] Headers presentes (curl -I).
