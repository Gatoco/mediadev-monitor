# Design: Web Dashboard Hardening + UI Refresh

## Auth cookie flags (`src/Auth/Auth.php`)

En `startSession()`, antes de `session_start()`:

```php
$secure = (!empty($_SERVER[HTTPS]) && $_SERVER[HTTPS] !== off)
    || ($_SERVER[HTTP_X_FORWARDED_PROTO] ?? ) === https;
session_set_cookie_params([
    lifetime => 0,
    path => /,
    httponly => true,
    secure => $secure,
    samesite => Lax,
]);
```

Detrás del tunnel Cloudflare, `X-Forwarded-Proto: https` → `Secure` se activa.

## Security headers (`web/security.php` nuevo)

Helper incluido al inicio de cada página:

```php
function send_security_headers(): void {
    header(X-Frame-Options: DENY);
    header(X-Content-Type-Options: nosniff);
    header(Referrer-Policy: no-referrer);
    header("Content-Security-Policy: default-src self; style-src self unsafe-inline; img-src self data:; frame-ancestors none");
}
```

`style-src unsafe-inline` porque el CSS es inline en `<style>` de layout. `frame-ancestors none` refuerza X-Frame-Options.

## Rate-limit (`src/Auth/RateLimit.php` nuevo)

Archivo JSON en `data/rate-limit.json` (gitignored):

```php
final class RateLimit {
    private string $file;
    public function __construct(string $file) { $this->file = $file; }
    public function check(string $ip): array { /* [allowed, retry_after_sec] */ }
    public function recordFailure(string $ip): void { /* incrementa contador */ }
    public function reset(string $ip): void { /* borra tras login OK */ }
}
```

Ventana 5 min, máx 10 fallos. `file_put_contents` con `LOCK_EX`.

## Emoji → CSS

- `🔭` (login + layout) → dot rojo IBM (`<span class="brand-dot"></span>`) + texto.
- `✅` (site.php "Sin updates pendientes") → texto "al día" con badge verde.

## Vista (`web/style.css`)

Refinar sin romper el shell LM Studio:
- Cards con borde izquierdo de color según semáforo (red/yellow/green).
- Badges más legibles, hover sutil.
- Login centrado con dot rojo de marca.
