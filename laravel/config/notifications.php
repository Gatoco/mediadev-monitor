<?php

/*
|--------------------------------------------------------------------------
| Mediadev Monitor — Agente de notificaciones
|--------------------------------------------------------------------------
|
| Canales: Telegram (HTTP API, cero dependencias) y email (mailer Laravel).
| Los destinatarios NUNCA van hardcodeados: todo sale del .env.
|
|   TELEGRAM_BOT_TOKEN     token del bot (BotFather)
|   TELEGRAM_CHAT_ID       chat_id destino
|   NOTIFY_EMAIL_TO        correo(s) destino, separados por coma
|   NOTIFY_RECOVERY_WINDOW minutos dentro de los cuales un down que vuelve
|                          se considera falso positivo (default: 15)
|
| Mailer: si MAIL_MAILER=log los correos quedan en storage/logs (útil para
| probar). Para SMTP real: MAIL_MAILER=smtp + MAIL_HOST/PORT/USER/PASS.
|
*/

return [

    'telegram' => [
        'token'   => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'api_base' => env('TELEGRAM_API_BASE', 'https://api.telegram.org'),
    ],

    // Lista de correos destino (string[]). Vacío = canal email deshabilitado.
    'emails' => array_values(array_filter(array_map(
        static fn (string $e): string => trim($e),
        explode(',', (string) env('NOTIFY_EMAIL_TO', ''))
    ))),

    // Ventana (min) para clasificar un down recuperado como falso positivo.
    'recovery_window_minutes' => (int) env('NOTIFY_RECOVERY_WINDOW', 15),

];
