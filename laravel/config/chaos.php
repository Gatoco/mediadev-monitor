<?php

/*
|--------------------------------------------------------------------------
| SemaOPS — Chaos Lab
|--------------------------------------------------------------------------
|
| Laboratorio para provocar fallas en los sitios CLONADOS (stack e2e) y
| medir cuánto tarda el monitor en detectarlas y avisar. Nunca apuntar a
| sitios en producción: el lab baja contenedores y cambia respuestas del
| emulador.
|
| Deshabilitado por defecto: /lab responde 404 salvo CHAOS_LAB_ENABLED=true.
|
*/

$emulated = [
    'diariotalca.cl', 'casa-humana.cl', 'agriculthor.cl', 'aikidomaipukai.com',
    'alimentate.cl', 'cardiomedica.cl', 'carrosdelmaule.cl', 'cecinasdonjacinto.cl',
    'conequip.cl', 'estadioespanoltalca.cl', 'hubot.cl', 'innovacion.ucm.cl',
    'integravita.cl', 'itma.cl', 'laboratorioaleman.cl', 'macmedica.cl',
    'mapeko.org', 'meymaq.cl', 'morissalud.cl', 'myfcardiosalud.cl',
    'okrental.cl', 'radiataforestal.cl', 'servivalchile.cl', 'villalegre.cl',
    'vinadetoroalexander.cl', 'mediadev.cl', 'septimaruta.cl',
];

return [
    'enabled' => (bool) env('CHAOS_LAB_ENABLED', false),

    // Sidecar con acceso al socket de Docker (docker/chaos-agent). Solo
    // permite start/stop/restart de los contenedores listados abajo.
    'agent_url' => env('CHAOS_AGENT_URL', 'http://chaos-agent:8090'),
    'agent_token' => env('CHAOS_AGENT_TOKEN', ''),

    // Token Bearer de la app Android (/lab/app/*). Vacío = app deshabilitada.
    'app_token' => env('CHAOS_APP_TOKEN', ''),

    // Archivo compartido con el portfolio-emulator (volumen chaos-state):
    // { "<host>": "<modo>" }. El emulador lo lee en cada request.
    'modes_file' => env('CHAOS_MODES_FILE', '/var/lib/mediadev/chaos/modes.json'),

    // Cadencia del chequeo de uptime del scheduler (routes/console.php).
    'check_every_minutes' => 5,

    /*
    | Contenedores controlables: servicio compose => container_name + los
    | hosts de sitios monitoreados que dependen de él.
    */
    'containers' => [
        'wp-full' => ['container' => 'mediadev-wp-full', 'hosts' => ['wp-full']],
        'wp-outdated' => ['container' => 'mediadev-wp-outdated', 'hosts' => ['wp-outdated']],
        'wp-hardened' => ['container' => 'mediadev-wp-hardened', 'hosts' => ['wp-hardened']],
        'non-wp' => ['container' => 'mediadev-non-wp', 'hosts' => ['non-wp']],
        'down' => ['container' => 'mediadev-down', 'hosts' => ['down']],
        'portfolio-emulator' => ['container' => 'mediadev-portfolio', 'hosts' => $emulated],
        'portfolio-down' => ['container' => 'mediadev-portfolio-down', 'hosts' => ['hotelvivomontana.cl']],
    ],

    // Sitios atendidos por el portfolio-emulator (admiten modo de falla).
    'emulated_hosts' => $emulated,
];
