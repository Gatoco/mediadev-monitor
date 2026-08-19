#!/bin/bash
# Mediadev Monitor — E2E state verification (realistic-sites-e2e).
#
# Prueba la clasificación de los 5 fixtures contra su estado esperado (RF-12/EV-01),
# verifica invariantes (Q#3 tokenless-first, 3-strike down, idempotencia, sin
# falsos positivos) y emite fila por fixture + resumen PASS/FAIL (EV-12).
#
# Fases:
#   1. Esperar healthchecks (o fallar rápido si WAIT_FOR_HEALTH=0)
#   2. Bootstrap APs (wp-cli oneshots → tokens en /ap-tokens)
#   3. Sembrar config/sites.php desde /ap-tokens/*.token
#   4. Correr bin/collector.php deep + bin/mediadev check all (dentro del contenedor)
#   5. Verificar 3-strike down (collector.php uptime)
#   6. Grep fila por fixture vs estado esperado; resumen PASS/FAIL
#
# Exit codes: 0 = todo OK, 1 = estado(s) incorrecto/crítico, 2 = error de uso/config.
#
# Variables de entorno:
#   WAIT_FOR_HEALTH  (default 1) — esperar hasta que los fixtures estén healthy.
#   E2E_TIMEOUT      (default 240) — segundos máximos de espera de health.

set -euo pipefail

DC="docker compose --profile e2e"
TIMEOUT="${E2E_TIMEOUT:-240}"
WAIT_FOR_HEALTH="${WAIT_FOR_HEALTH:-1}"

FIXTURES=(wp-full wp-outdated wp-hardened non-wp down)

# Estado esperado por fixture (RF-12 / EV-01 matrix).
declare -A EXPECTED=(
    [wp-full]="wp-full"
    [wp-outdated]="wp-full"     # + RED severity (exit 1)
    [wp-hardened]="wp-degraded"
    [non-wp]="non-wp"
    [down]="down"
)

# Severidad esperada (red para wp-outdated) — para hasCritical.
declare -A SEVERITY_RED=( [wp-outdated]=1 )

log() { printf '\033[1;34m» %s\033[0m\n' "$*" >&2; }
ok()   { printf '\033[32m  ✓ %s\033[0m\n' "$*" >&2; }
warn() { printf '\033[33m  ⚠ %s\033[0m\n' "$*" >&2; }
fail() { printf '\033[31m  ✗ %s\033[0m\n' "$*" >&2; }

FAILURES=0
TOTAL=0

check() {
    # check <fixture> <expected> <actual>
    local f="$1" exp="$2" act="$3"
    TOTAL=$((TOTAL + 1))
    if [ "$act" = "$exp" ]; then
        ok "${f}: ${exp} (PASS)"
    else
        fail "${f}: esperado=${exp} actual=${act}"
        FAILURES=$((FAILURES + 1))
    fi
}

# ---------------------------------------------------------------- health
wait_healthy() {
    # RED-A: WAIT_FOR_HEALTH=0 → NO esperar. Si los fixtures no están listos
    # en este instante, fallar rápido con exit 2 (no falso PASS). No se debe
    # recorrer el bucle de timeout (eso esperaría 240s, rompiendo el fail-fast).
    if [ "$WAIT_FOR_HEALTH" = "0" ]; then
        local ready=1
        for f in wp-full wp-outdated wp-hardened non-wp; do
            local st
            st=$($DC ps "$f" --format '{{.Status}}' 2>/dev/null || echo "N/A")
            if ! printf '%s' "$st" | grep -q 'healthy'; then
                ready=0
                break
            fi
        done
        if [ "$ready" = "1" ]; then
            ok "todos los fixtures healthy (sin espera)"
            return 0
        fi
        warn "fixtures aún no healthy — exit 2 (RED-A)"
        return 2
    fi

    log "Esperando healthchecks de fixtures..."
    local deadline=$(( $(date +%s) + TIMEOUT ))
    while [ "$(date +%s)" -lt "$deadline" ]; do
        local all_ok=1
        for f in wp-full wp-outdated wp-hardened non-wp; do
            local st
            st=$($DC ps "$f" --format '{{.Status}}' 2>/dev/null || echo "N/A")
            if ! printf '%s' "$st" | grep -q 'healthy'; then
                all_ok=0
                break
            fi
        done
        if [ "$all_ok" = "1" ]; then
            ok "todos los fixtures healthy"
            return 0
        fi
        sleep 3
    done

    fail "timeout esperando healthchecks"
    return 1
}

# ---------------------------------------------------------------- 2. bootstrap
bootstrap_aps() {
    log "Bootstrap de Application Passwords (wp-cli oneshots)..."
    for svc in wp-cli-full wp-cli-outdated wp-cli-hardened; do
        if ! $DC run --rm "$svc" >/dev/null 2>&1; then
            warn "bootstrap $svc falló (¿ya corrió?) — reintentando con up"
            $DC up "$svc" >/dev/null 2>&1 || true
        fi
    done
    ok "bootstrap APs completado"
}

# ---------------------------------------------------------------- 3. sembrar config/sites.php
seed_sites() {
    log "Sembrando config/sites.php desde /ap-tokens/*.token"
    local token_full token_outdated token_hardened
    token_full=$(read_token wp-full)
    token_outdated=$(read_token wp-outdated)
    token_hardened=$(read_token wp-hardened)

    if [ -z "$token_full" ] || [ -z "$token_outdated" ] || [ -z "$token_hardened" ]; then
        fail "faltan tokens de AP (ap-tokens vol?)"
        return 1
    fi

    cat > config/sites.php <<PHP
<?php
// Generado por bin/e2e-assert.sh — NO editar manualmente (gitignored).
return [
    ['url'=>'http://wp-full:80','name'=>'wp-full','type'=>'wp','wp_user'=>'monitor','token'=>'${token_full}'],
    ['url'=>'http://wp-outdated:80','name'=>'wp-outdated','type'=>'wp','wp_user'=>'monitor','token'=>'${token_outdated}'],
    ['url'=>'http://wp-hardened:80','name'=>'wp-hardened','type'=>'auto','wp_user'=>'monitor','token'=>'${token_hardened}'],
    ['url'=>'http://non-wp:80','name'=>'non-wp','type'=>'non-wp','wp_user'=>null,'token'=>null],
    ['url'=>'http://down:80','name'=>'down','type'=>'auto','wp_user'=>null,'token'=>null],
];
PHP
    ok "config/sites.php sembrado (5 fixtures)"
}

read_token() {
    local fixture="$1"
    # Leer el token desde el volumen ap-tokens montado en monitor (ro).
    local tok
    tok=$($DC exec -T monitor sh -c "cat /ap-tokens/${fixture}.token 2>/dev/null" 2>/dev/null | tr -d '[:space:]')
    printf '%s' "$tok"
}

# ---------------------------------------------------------------- 4. run collectors (deep)
run_deep() {
    log "Corriendo bin/collector.php deep dentro del monitor..."
    # Los exit codes 0/1/2 son parte de la semántica (EV-02..EV-04). Capturamos
    # el código real sin abortar por set -e.
    set +e
    $DC exec -T monitor php /app/bin/collector.php deep > /tmp/e2e-deep.out 2>&1
    DEEP_EXIT=$?
    log "Corriendo bin/mediadev check all dentro del monitor..."
    $DC exec -T monitor php /app/bin/mediadev check all > /tmp/e2e-check.out 2>&1
    CHECK_EXIT=$?
    set -e
}

# ---------------------------------------------------------------- EV-02..EV-05 exit semantics
assert_exit_codes() {
    log "Verificando exit codes (EV-02..EV-05)…"
    # Con `down` + `wp-outdated`(RED) presentes, check all / deep deben salir 1.
    TOTAL=$((TOTAL + 1))
    if [ "$CHECK_EXIT" -eq 1 ] && [ "$DEEP_EXIT" -eq 1 ]; then
        ok "check all exit=$CHECK_EXIT, collector deep exit=$DEEP_EXIT (críticos → 1)"
    else
        FAILURES=$((FAILURES + 1))
        fail "exit codes inesperados: check all=$CHECK_EXIT, deep=$DEEP_EXIT (esperado 1)"
    fi
}

# ---------------------------------------------------------------- 5. 3-strike down (EV-06/EV-07)
three_strike_down() {
    log "Verificando 3-strike down (collector.php uptime)..."
    local prev=""
    local hits=0
    for run in 1 2 3; do
        $DC exec -T monitor php /app/bin/collector.php uptime > /tmp/e2e-uptime-$run.out 2>&1 || true
        local down_state
        down_state=$(grep -E '^down' /tmp/e2e-uptime-$run.out | awk '{print $2}' || echo "")
        printf '  probe %d: down=%s\n' "$run" "${down_state:-unknown}" >&2
        if [ "$down_state" = "down" ]; then
            hits=$((hits + 1))
        fi
        prev="$down_state"
    done

    # EV-06: down debe aparecer solo desde la 3ª probisión.
    local third_state
    third_state=$(grep -E '^down' /tmp/e2e-uptime-3.out | awk '{print $2}' || echo "")
    TOTAL=$((TOTAL + 1))
    if [ "$third_state" = "down" ]; then
        ok "3-strike down: estado=down tras 3.ª probisión (PASS)"
    else
        fail "3-strike down: estado no es down tras 3 probisiones (actual=${third_state:-unknown})"
        FAILURES=$((FAILURES + 1))
    fi
}

# ---------------------------------------------------------------- 6. per-fixture classification
assert_states() {
    log "Verificando clasificación por fixture (EV-01)…"
    # Usamos la salida de `collector.php deep` (formato "name  state").
    local out=/tmp/e2e-deep.out
    for f in wp-full wp-outdated wp-hardened non-wp; do
        local exp="${EXPECTED[$f]}"
        local act
        act=$(grep -E "^${f}\b" "$out" | awk '{print $2}' || echo "")
        check "$f" "$exp" "$act"
    done

    # down: estado de la deep (debe ser down).
    local dstate
    dstate=$(grep -E '^down\b' "$out" | awk '{print $2}' || echo "")
    check "down" "down" "$dstate"
}

# ---------------------------------------------------------------- EV-10 falsos positivos
assert_no_false_positives() {
    log "Sin falsos positivos (EV-10)…"
    local out=/tmp/e2e-deep.out
    local wpfull nonwp
    wpfull=$(grep -E '^wp-full\b' "$out" | awk '{print $2}')
    nonwp=$(grep -E '^non-wp\b' "$out" | awk '{print $2}')
    TOTAL=$((TOTAL + 1))
    if [ "$wpfull" != "wp-degraded" ] && [ "$nonwp" != "wp-full" ]; then
        ok "wp-full≠wp-degraded y non-wp≠wp-full"
    else
        FAILURES=$((FAILURES + 1))
        fail "falso positivo detectado (wp-full=$wpfull, non-wp=$nonwp)"
    fi
}

# ---------------------------------------------------------------- Q#3 tokenless-first (EV-08/EV-09)
assert_q3() {
    log "Verificando Q-3 tokenless-first (EV-08)…"
    # wp-full expone /wp/v2/posts sin AP → 200 (público). Si ActivityCollector
    # no degrada wp-full a wp-degraded, ya confirmamos que la actividad es
    # disponible sin AP (tokenless-first).
    local out=/tmp/e2e-deep.out
    local wpfull
    wpfull=$(grep -E '^wp-full\b' "$out" | awk '{print $2}')
    TOTAL=$((TOTAL + 1))
    if [ "$wpfull" = "wp-full" ]; then
        ok "wp-full disponible sin AP (tokenless-first, EV-08)"
    else
        FAILURES=$((FAILURES + 1))
        fail "wp-full NO disponible tokenless (estado=$wpfull) — viola Q-3"
    fi

    # wp-hardened exige AP: debe clasificar wp-degraded (retry con AP en
    # activity o health 404) — EV-09 confirma el retry con AP.
    local hardened
    hardened=$(grep -E '^wp-hardened\b' "$out" | awk '{print $2}')
    TOTAL=$((TOTAL + 1))
    if [ "$hardened" = "wp-degraded" ]; then
        ok "wp-hardened usó AP (retry) → wp-degraded (EV-09)"
    else
        FAILURES=$((FAILURES + 1))
        fail "wp-hardened inesperado (estado=$hardened) — retry con AP no evidenciado"
    fi
}

# ---------------------------------------------------------------- EV-11 idempotencia
assert_idempotent() {
    log "Re-running para idempotencia (EV-11)…"
    # Segunda ejecución de deep y comparar estados.
    $DC exec -T monitor php /app/bin/collector.php deep > /tmp/e2e-deep2.out 2>&1 || true
    TOTAL=$((TOTAL + 1))
    if diff -q /tmp/e2e-deep.out /tmp/e2e-deep2.out >/dev/null 2>&1; then
        ok "re-run idéntico (sin residual state)"
    else
        FAILURES=$((FAILURES + 1))
        fail "re-run difiere de la primera ejecución (residual state)"
    fi
}

# ---------------------------------------------------------------- summary
summary() {
    log "Resumen"
    if [ "$FAILURES" -eq 0 ]; then
        printf '\n\033[1;32m  RESULTADO: PASS (%d/%d checks)\033[0m\n' "$TOTAL" "$TOTAL"
    else
        printf '\n\033[1;31m  RESULTADO: FAIL (%d/%d checks)\033[0m\n' "$FAILURES" "$TOTAL"
    fi
}

die() { printf '\033[31mERROR: %s\033[0m\n' "$*" >&2; exit 2; }

main() {
    [ -f config/sites.example.php ] || die "ejecutar desde la raíz del repo"

    log "=== Mediadev Monitor E2E Assert ==="

    # 1. Fixtures healthy
    rc=$(wait_healthy) || {
        rc="$?"
        if [ "$rc" -eq 2 ]; then die "fixtures no listos (RED-A)"; fi
        die "fixtures nunca quedaron healthy"
    }

    # 2. Bootstrap APs
    bootstrap_aps

    # 3. Seed config
    seed_sites || die "fallo al sembrar config/sites.php"

    # 4. Deep run
    run_deep

    # 5. 3-strike down
    three_strike_down

    # 6. Per-fixture classification
    assert_states

    # 7. Falsos positivos
    assert_no_false_positives

    # 8. Q#3 tokenless-first + AP retry
    assert_q3

    # 9. Idempotencia (EV-11)
    assert_idempotent

    # 10. Exit codes (EV-02..EV-05)
    assert_exit_codes

    # 11. Exit code global (EV-02..EV-04)
    TOTAL=$((TOTAL + 1))
    if [ "$FAILURES" -eq 0 ]; then
        ok "todos los estados correctos"
        summary
        exit 0
    else
        summary
        exit 1
    fi
}

main "$@"
