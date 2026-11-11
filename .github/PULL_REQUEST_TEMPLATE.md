## Resumen

Qué cambia y por qué, en pocas líneas.

Tipo de cambio (Conventional Commits): `feat` · `fix` · `docs` · `refactor` · `test` · `chore`

Issue relacionado: Closes #

## Evidencia de verificación (obligatoria)

### Tests

- [ ] `cd laravel && php artisan test` pasa sin fallos.
- [ ] Salida adjunta (resumen final de PHPUnit):

```text
# pegar aquí el resumen final de la suite
```

### Estabilidad

- [ ] `bin/e2e-assert.sh` pasa (requiere `docker compose --profile e2e up -d`), o se justifica por qué no aplica.
- [ ] Los comandos Artisan afectados mantienen sus exit codes (`0` / `1` / `2`).
- [ ] Repetí la ejecución de los tests afectados al menos dos veces y obtuve el mismo resultado (chequeo de flakiness).

### Checklist

- [ ] Commits atómicos con Conventional Commits.
- [ ] No se agregaron secretos, tokens ni cambios en `config/sites.php`, `config/auth.php` o `.env`.
- [ ] Documentación actualizada (README, CLI o comentarios) si cambia el comportamiento.
- [ ] Sin dependencias nuevas en los sitios monitoreados (zero-plugins).
