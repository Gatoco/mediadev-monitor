<?php

/**
 * Mediadev Monitor — RateLimit: limita intentos de login por IP (fuerza bruta).
 * Persistencia: archivo JSON en data/ (gitignored). Ventana 5 min, máx 10 fallos.
 */

declare(strict_types=1);

namespace MediadevMonitor\Auth;

final class RateLimit
{
    private const WINDOW = 300;      // 5 min
    private const MAX_FAILS = 10;

    public function __construct(private string $file)
    {
    }

    /** @return array{allowed:bool, retry_after:int} */
    public function check(string $ip): array
    {
        $data = $this->read();
        $entry = $data[$ip] ?? ['fails' => 0, 'first' => time()];
        if ($entry['fails'] >= self::MAX_FAILS) {
            $elapsed = time() - $entry['first'];
            if ($elapsed < self::WINDOW) {
                return ['allowed' => false, 'retry_after' => self::WINDOW - $elapsed];
            }
            unset($data[$ip]); // ventana expirada → reset
            $this->write($data);
        }
        return ['allowed' => true, 'retry_after' => 0];
    }

    public function recordFailure(string $ip): void
    {
        $data = $this->read();
        $entry = $data[$ip] ?? ['fails' => 0, 'first' => time()];
        $entry['fails']++;
        $data[$ip] = $entry;
        $this->write($data);
    }

    public function reset(string $ip): void
    {
        $data = $this->read();
        unset($data[$ip]);
        $this->write($data);
    }

    /** @return array<string, array{fails:int, first:int}> */
    private function read(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $raw = file_get_contents($this->file);
        $data = json_decode($raw ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    private function write(array $data): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents($this->file, json_encode($data), LOCK_EX);
    }
}
