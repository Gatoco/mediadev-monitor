<?php

namespace App\Services\Chaos;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente del chaos-agent (docker/chaos-agent): único proceso con acceso al
 * socket de Docker. El monitor nunca toca Docker directamente.
 */
class ContainerAgent
{
    public function __construct(
        private string $baseUrl,
        private string $token,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self((string) config('chaos.agent_url'), (string) config('chaos.agent_token'));
    }

    /**
     * Estado por container_name: running | exited | restarting | missing | unknown.
     *
     * @return array<string, string>
     */
    public function states(): array
    {
        try {
            $response = $this->client()->connectTimeout(2)->timeout(5)->get('/containers');
        } catch (\Throwable) {
            return [];
        }

        if (!$response->ok()) {
            return [];
        }

        return collect($response->json('containers', []))
            ->map(fn (array $c): string => (string) ($c['state'] ?? 'unknown'))
            ->all();
    }

    /** @param 'start'|'stop'|'restart' $action */
    public function act(string $container, string $action): void
    {
        $response = $this->client()->timeout(45)->post("/containers/{$container}/{$action}");

        if (!$response->ok()) {
            throw new RuntimeException(sprintf(
                'chaos-agent rechazó %s %s (HTTP %d)',
                $action,
                $container,
                $response->status(),
            ));
        }
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->token)
            ->acceptJson();
    }
}
