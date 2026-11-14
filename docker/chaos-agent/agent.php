<?php
/**
 * SemaOPS — chaos-agent.
 *
 * Único proceso con acceso al socket de Docker. Expone una API mínima para
 * que el Chaos Lab (contenedor monitor) suba/baje SOLO los contenedores de
 * la lista blanca CHAOS_CONTAINERS. Nada de create/exec/rm: aunque alguien
 * obtenga el token, no puede lanzar contenedores nuevos ni tocar otros.
 *
 *   GET  /containers                     → estado de los contenedores permitidos
 *   POST /containers/{name}/{action}     → action: start | stop | restart
 *
 * Auth: header "Authorization: Bearer <CHAOS_AGENT_TOKEN>".
 */

declare(strict_types=1);

const SOCKET = '/var/run/docker.sock';

function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

/** @return array{0:int,1:mixed} */
function docker(string $method, string $path): array
{
    $ch = curl_init('http://docker' . $path);
    curl_setopt_array($ch, [
        CURLOPT_UNIX_SOCKET_PATH => SOCKET,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [$status, $raw === false ? null : json_decode((string) $raw, true)];
}

$token = (string) getenv('CHAOS_AGENT_TOKEN');
$given = preg_replace('/^Bearer\s+/i', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
if ($token === '' || !hash_equals($token, (string) $given)) {
    respond(401, ['error' => 'unauthorized']);
}

$allowed = array_filter(array_map('trim', explode(',', (string) getenv('CHAOS_CONTAINERS'))));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($method === 'GET' && $path === '/containers') {
    $out = [];
    foreach ($allowed as $name) {
        [$status, $info] = docker('GET', '/containers/' . rawurlencode($name) . '/json');
        $out[$name] = $status === 200
            ? ['state' => $info['State']['Status'] ?? 'unknown', 'started_at' => $info['State']['StartedAt'] ?? null]
            : ['state' => 'missing', 'started_at' => null];
    }
    respond(200, ['containers' => $out]);
}

if ($method === 'POST' && preg_match('#^/containers/([A-Za-z0-9_.-]+)/(start|stop|restart)$#', $path, $m)) {
    [, $name, $action] = $m;
    if (!in_array($name, $allowed, true)) {
        respond(403, ['error' => 'container not allowed']);
    }
    $query = $action === 'start' ? '' : '?t=5';
    [$status] = docker('POST', '/containers/' . rawurlencode($name) . '/' . $action . $query);
    // 204 = hecho, 304 = ya estaba en ese estado.
    if ($status === 204 || $status === 304) {
        respond(200, ['ok' => true, 'container' => $name, 'action' => $action]);
    }
    respond(502, ['error' => 'docker error', 'status' => $status]);
}

respond(404, ['error' => 'not found']);
