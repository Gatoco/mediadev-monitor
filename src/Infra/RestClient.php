<?php

/**
 * Mediadev Monitor — Infra: Cliente REST (wrapper curl, cero dependencias).
 *
 * - Basic Auth para Application Passwords de WordPress
 * - Timeout por request
 * - Retry 2x con backoff
 * - Tolerancia 403/404 por endpoint: NO lanza excepción, el caller decide
 */

declare(strict_types=1);

namespace MediadevMonitor\Infra;

final class RestResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** @return array<mixed> */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }
}

final class RestClient
{
    public function __construct(
        private int $timeoutMs = 10000,
        private int $maxRetries = 2,
    ) {
    }

    public function get(string $url, ?string $basicAuth = null): RestResponse
    {
        $attempt = 0;
        do {
            $response = $this->request('GET', $url, $basicAuth);
            if ($response->status !== 0) {
                return $response;
            }
            $attempt++;
            if ($attempt <= $this->maxRetries) {
                usleep(500_000 * $attempt); // backoff: 500ms, 1s
            }
        } while ($attempt <= $this->maxRetries);

        return $response;
    }

    private function request(string $method, string $url, ?string $basicAuth): RestResponse
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $this->timeoutMs,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'MediadevMonitor/0.1 (+https://github.com/Gatoco/mediadev-monitor)',
        ]);

        if ($basicAuth !== null) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false) {
            return new RestResponse(0, '', []); // 0 = connection error (equivale a HTTP 000)
        }

        $headersRaw = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        $headers = [];
        foreach (explode("\n", $headersRaw) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[trim($k)] = trim($v);
            }
        }

        return new RestResponse($status, $body, $headers);
    }
}
