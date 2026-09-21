<?php

namespace App\Services\WordPress;

use App\Models\Site;
use Illuminate\Support\Facades\Log;

/**
 * Verificador de Application Passwords WP.
 *
 * Hace un GET real a /wp-json/wp/v2/users/me con Basic Auth
 * (wp_user + ap_token) y clasifica el resultado:
 *
 *   valid       credenciales OK, devuelve info del usuario WP
 *   invalid     401/403 del endpoint → AP revocado o usuario malo
 *   unreachable ni siquiera respondió el WP (DNS, TLS, timeout, 404 del REST,
 *               5xx del server, o el sitio no es WP)
 *   unknown     sin credenciales cargadas aún
 *
 * Persiste el resultado en `sites.wp_token_status` + mensaje + fecha para
 * que el dashboard lo muestre sin tener que re-llamar al endpoint.
 *
 * Cero dependencias: usa curl nativo (mismo patrón que TelegramChannel).
 */
class WpTokenVerifier
{
    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const UNREACHABLE = 'unreachable';
    public const UNKNOWN = 'unknown';

    /**
     * @return array{status:string,message:string,user:?array}
     */
    public function verify(Site $site): array
    {
        $user = trim((string) ($site->wp_user ?? ''));
        $token = trim((string) ($site->ap_token ?? ''));

        if ($user === '' || $token === '') {
            return $this->finalize($site, self::UNKNOWN, 'Sin credenciales WP cargadas', null);
        }

        // Los AP de WP vienen como "xxxx xxxx xxxx". Authorization va sin espacios.
        $tokenClean = preg_replace('/\s+/', '', $token) ?? $token;

        $url = rtrim((string) $site->url, '/') . '/wp-json/wp/v2/users/me';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT_MS => 8000,
            CURLOPT_CONNECTTIMEOUT_MS => 4000,
            // No verificamos TLS: el tío tiene sitios con certificados raros, y
            // lo único que importa acá es si el AP es válido, no el cert.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $user . ':' . $tokenClean,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'mediadev-monitor/1.0',
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $raw === false ? curl_error($ch) : null;
        curl_close($ch);

        if ($raw === false) {
            Log::info('wp_token.unreachable', ['site' => $site->id, 'error' => $error]);
            return $this->finalize($site, self::UNREACHABLE, 'No respondió: ' . ($error ?: 'error de red'), null);
        }

        if ($status === 401 || $status === 403) {
            $decoded = json_decode((string) $raw, true);
            $code = is_array($decoded) ? (string) ($decoded['code'] ?? '') : '';
            $msg = $status === 401
                ? 'Application Password inválido o revocado'
                : 'El usuario no tiene permiso para users/me';
            if ($code !== '') {
                $msg .= " ({$code})";
            }
            return $this->finalize($site, self::INVALID, $msg, null);
        }

        if ($status === 404) {
            return $this->finalize($site, self::UNREACHABLE, 'REST API no expuesta (404). ¿Es WP de verdad?', null);
        }

        if ($status >= 500) {
            return $this->finalize($site, self::UNREACHABLE, "WP devolvió {$status}", null);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || !isset($decoded['id'])) {
            return $this->finalize($site, self::UNREACHABLE, 'Respuesta no es JSON WP o le falta id', null);
        }

        $wpUser = [
            'id' => (int) $decoded['id'],
            'name' => (string) ($decoded['name'] ?? ''),
            'slug' => (string) ($decoded['slug'] ?? ''),
        ];

        return $this->finalize(
            $site,
            self::VALID,
            'OK · usuario "' . ($wpUser['name'] !== '' ? $wpUser['name'] : $wpUser['slug']) . '" (ID ' . $wpUser['id'] . ')',
            $wpUser,
        );
    }

    /**
     * Persiste el resultado en sites. Devuelve el array de resultado.
     *
     * @param array{id:int,name:string,slug:string}|null $user
     * @return array{status:string,message:string,user:?array}
     */
    private function finalize(Site $site, string $status, string $message, ?array $user): array
    {
        $site->forceFill([
            'wp_token_status' => $status,
            'wp_token_message' => mb_substr($message, 0, 250),
            'wp_token_checked_at' => now(),
        ])->save();

        return ['status' => $status, 'message' => $message, 'user' => $user];
    }
}
