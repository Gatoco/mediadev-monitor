# Web Dashboard Security Specification

## Purpose

Endurecer el dashboard legacy (`web/`) contra fuerza bruta y exposición, y eliminar emoji del UI.

## Requirements

### REQ-SEC-01: Cookie flags de sesión
La sesión PHP debe configurarse con `HttpOnly`, `Secure` (cuando HTTPS) y `SameSite=Lax` antes de `session_start()`.

### REQ-SEC-02: Headers de seguridad
Todas las respuestas del dashboard deben incluir `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer` y una CSP básica (`default-src self`).

### REQ-SEC-03: Rate-limit de login
El login debe limitar a 10 intentos fallidos por IP en una ventana de 5 minutos, con mensaje de bloqueo.

### REQ-SEC-04: Sin emoji
El dashboard no debe contener emoji (`🔭`, `✅`). Reemplazar por elementos CSS/texto.

## Scenarios

### SC-01: Login exitoso
Dado credenciales válidas, cuando el usuario ingresa, se crea sesión con cookie `HttpOnly; Secure; SameSite=Lax` y redirige a `index.php`.

### SC-02: Fuerza bruta
Dado 10 intentos fallidos en 5 min desde la misma IP, cuando el usuario intenta de nuevo, se muestra "Demasiados intentos. Intenta en X min."

### SC-03: Headers presentes
Dado una petición a `index.php`, cuando se inspeccionan los headers, están presentes `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` y CSP.

### SC-04: Sin emoji
Dado el HTML renderizado del dashboard, cuando se busca emoji, no hay ninguno.

## Edge Cases

- `Secure` cookie: si no hay HTTPS (dev local), no forzar `Secure` para no romper login.
- Rate-limit: IPs distintas no se afectan entre sí.
- CSP: no debe romper el CSS inline ni el auto-refresh meta.
