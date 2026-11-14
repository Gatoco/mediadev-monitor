<?php

namespace App\Services\Chaos;

/**
 * Modos de falla por sitio emulado. Se guardan en un JSON compartido con el
 * portfolio-emulator ({ "<host>": "<modo>" }); el emulador lo lee en cada
 * request, así que el cambio es inmediato.
 */
class FaultModes
{
    /** modo => [etiqueta, descripción, estado que debería detectar el monitor] */
    public const MODES = [
        'normal' => ['Normal', 'Responde como el sitio real', null],
        'caido' => ['Caído', 'Sin respuesta', 'down'],
        'error500' => ['Error 500', 'Todas las rutas fallan', 'down'],
        'lento' => ['Lento', 'Más de 10 s por respuesta', 'down'],
        'degradado' => ['WP degradado', '403 en /wp-json', 'wp-degraded'],
        'intermitente' => ['Intermitente', 'Falla 1 de cada 3 chequeos', 'none'],
    ];

    public function __construct(private string $file)
    {
    }

    public static function fromConfig(): self
    {
        return new self((string) config('chaos.modes_file'));
    }

    public static function label(string $mode): string
    {
        return self::MODES[$mode][0] ?? $mode;
    }

    public static function expected(string $mode): ?string
    {
        return self::MODES[$mode][2] ?? null;
    }

    /** @return array<string, string> host => modo (solo los que no son normal) */
    public function all(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->file), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function get(string $host): string
    {
        return $this->all()[$host] ?? 'normal';
    }

    public function set(string $host, string $mode): void
    {
        $modes = $this->all();

        if ($mode === 'normal') {
            unset($modes[$host]);
        } else {
            $modes[$host] = $mode;
        }

        $this->write($modes);
    }

    public function clear(): void
    {
        $this->write([]);
    }

    /** Escritura atómica: el emulador nunca lee un JSON a medias. */
    private function write(array $modes): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $tmp = $this->file . '.' . getmypid() . '.tmp';
        file_put_contents($tmp, json_encode((object) $modes, JSON_PRETTY_PRINT) . "\n");
        rename($tmp, $this->file);
    }
}
