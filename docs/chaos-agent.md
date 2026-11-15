# Chaos Agent: cómo la app enciende y apaga contenedores

Esta guía explica cómo la app Android (y la web `/lab`) encienden y apagan los
contenedores de los **sitios clonados** para demostrar que el monitor detecta las
caídas y avisa. Nunca se toca un sitio en producción.

## Piezas

| Pieza | Dónde vive | Qué hace |
|:--|:--|:--|
| **App Android** | `android/` | Una pantalla: lista los contenedores y los apaga o enciende. |
| **Panel Laravel** | contenedor `mediadev-monitor` | Valida el token de la app, traduce servicio → contenedor y le pide la acción al agente. También mide cada prueba como *experimento*. |
| **chaos-agent** | contenedor `mediadev-chaos-agent` (`docker/chaos-agent/`) | El **único** servicio con acceso al socket de Docker. Solo ejecuta `start`, `stop` o `restart` sobre una lista fija de contenedores. |
| **Contenedores de clones** | perfil `e2e` de `docker-compose.yml` | `wp-full`, `wp-outdated`, `wp-hardened`, `non-wp`, `down`, `portfolio-emulator` (27 sitios clonados) y `portfolio-down`. |

## Recorrido de una acción

```
App Android
   │  HTTPS  ·  Authorization: Bearer CHAOS_APP_TOKEN
   │          (+ CF-Access-Client-Id / Secret si hay Cloudflare Access)
   ▼
Panel Laravel  /lab/app/containers/{servicio}/{start|stop}
   │  HTTP interno, red net-monitor  ·  Bearer CHAOS_AGENT_TOKEN
   ▼
chaos-agent  :8090  /containers/{contenedor}/{start|stop|restart}
   │  /var/run/docker.sock
   ▼
Docker  →  detiene o inicia mediadev-wp-full, mediadev-portfolio, …
```

1. Tocas **Apagar** en `wp-full` y la app envía `POST /lab/app/containers/wp-full/stop`.
2. Laravel (`EnsureChaosAppToken`) compara el Bearer con `CHAOS_APP_TOKEN`. Si no coincide, o si el token del servidor está vacío, responde **401**.
3. `config/chaos.php` traduce `wp-full` a `mediadev-wp-full`. Si el servicio no está en esa lista, responde **422** y no llega a contactar al agente.
4. `ContainerAgent` llama a `POST http://chaos-agent:8090/containers/mediadev-wp-full/stop` con `CHAOS_AGENT_TOKEN`.
5. El agente revisa su token y su propia lista blanca (`CHAOS_CONTAINERS`), y recién entonces llama a la API de Docker por el socket.
6. Laravel registra un **experimento**. La app refresca la lista cada 5 s (`GET /lab/app/containers`) y muestra el contenedor apagado.

Si el panel no logra hablar con el agente, la app muestra *"El servidor no logra hablar con el chaos-agent"* y desactiva los botones.

## Cómo se comprueba que el monitor detecta la caída

El agente solo apaga el contenedor. La detección la hace el monitor normal, sin atajos:

1. El chequeo de uptime, que corre cada 5 min, no logra conectar con el sitio.
2. Al **3er fallo consecutivo** el sitio pasa a `down`.
3. `notify:status` envía **una** alerta por Telegram o email y la guarda en `notification_log`.
4. Cuando vuelves a **encender** el contenedor, el primer chequeo OK cierra la caída y se envía la alerta de recuperado.

Cada experimento guarda las horas de inyección, detección, alerta y recuperación. Se consultan en `/lab`, en la sección *Historial*.

## Seguridad: por qué está separado así

- **Socket aislado.** Montar `docker.sock` equivale a tener control casi total del host, por eso solo el agente lo monta y no hace nada más.
- **Doble lista blanca.** Una está en Laravel (`config/chaos.php`) y otra en el agente (`CHAOS_CONTAINERS`). Aunque alguien comprometa el panel, no puede apagar `mysql`, el `monitor` ni nada fuera de la lista.
- **Dos tokens.** `CHAOS_APP_TOKEN` viaja por internet hasta la app. `CHAOS_AGENT_TOKEN` nunca sale de la red interna de Docker.
- **Sin puertos publicados.** El agente solo es accesible desde `net-monitor`.
- **Apagado por defecto.** Sin `CHAOS_LAB_ENABLED=true`, `/lab` responde 404. El agente solo existe con `--profile e2e`.

## Desplegar en el servidor

Lo hace quien tenga acceso al servidor:

```bash
cd mediadev-monitor
git fetch origin && git checkout Integración-App      # o merge a main

# .env junto a docker-compose.yml
CHAOS_LAB_ENABLED=true
CHAOS_AGENT_TOKEN=$(openssl rand -hex 24)
CHAOS_APP_TOKEN=$(openssl rand -hex 24)

docker compose --profile e2e up -d --build
docker compose exec monitor php artisan migrate --force
bin/seed-portfolio.sh

docker ps --filter name=mediadev-chaos-agent           # debe aparecer "Up"
```

Antes de terminar, revisa dos cosas en el servidor:

- **Ubicación del socket.** El agente monta `/var/run/docker.sock`. Si usan Docker *rootless* o el socket está en otra ruta, ajusta el `volumes` del servicio `chaos-agent`.
- **Cloudflare Access.** El panel está detrás de Cloudflare Access, así que la app necesita un **Service Token**: créalo en *Zero Trust → Access → Service Auth* y agrégalo a la política del panel con la regla *Service Token*.

## Configurar la app

1. Compila e instala la app: en Android Studio, abre `android/` y ejecuta *Run*. Desde consola: `cd android && ./gradlew assembleDebug`.
2. Toca el ⚙ y completa:
   - **URL del panel**: `https://panel.t420.dev`. Para el servidor local visto desde el emulador usa `http://10.0.2.2:8000`.
   - **Token de la app**: el valor de `CHAOS_APP_TOKEN`.
   - **Cloudflare Access Client ID / Secret**: los del Service Token.

La app solo permite HTTP sin cifrar hacia `10.0.2.2` y `localhost`. Cualquier otro servidor debe usar HTTPS.

## API de la app

| Método | Ruta | Respuesta |
|:--|:--|:--|
| `GET` | `/lab/app/containers` | `{ agent_ok, containers: [{ service, container, container_state, monitor_state, sites }] }` |
| `POST` | `/lab/app/containers/{servicio}/start` | `{ ok: true }` |
| `POST` | `/lab/app/containers/{servicio}/stop` | `{ ok: true }` y abre un experimento |

Errores: `401` token inválido · `404` lab deshabilitado · `422` servicio no permitido · `502` el agente no respondió.

## Qué no pasa por el agente

Los **modos de falla** de los 27 sitios clonados (error 500, lento, WP degradado, intermitente) se cambian desde la web `/lab`. Laravel los escribe en `modes.json`, dentro del volumen compartido `chaos-state`, y el `portfolio-emulator` lo lee en cada request. Docker no interviene.
