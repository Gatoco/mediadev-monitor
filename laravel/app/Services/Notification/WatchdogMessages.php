<?php

namespace App\Services\Notification;

use Domain\SiteRegistry\SiteState;

/**
 * Mensajería de los eventos del monitor (texto plano, ES).
 * El mismo payload alimenta Telegram y email — sin HTML pesado.
 */
final class WatchdogMessages
{
    public static function subject(string $emoji, string $kind, string $siteName): string
    {
        return "[mediadev] {$emoji} {$kind}: {$siteName}";
    }

    public static function siteDown(string $siteName, string $url, int $failures): string
    {
        return implode("\n", [
            "🔴 SITIO CAÍDO: {$siteName}",
            "URL: {$url}",
            "Fallos consecutivos: {$failures}",
        ]);
    }

    public static function siteRecovered(string $siteName, string $url, ?int $responseMs, bool $flaky, int $windowMinutes): string
    {
        $header = $flaky
            ? "🟠 FALSO POSITIVO / RECUPERACIÓN: {$siteName}"
            : "✅ SITIO RECUPERADO: {$siteName}";

        $lines = [$header, "URL: {$url}"];
        if ($responseMs !== null) {
            $lines[] = "Respuesta actual: {$responseMs} ms";
        }
        if ($flaky) {
            $lines[] = "Estuvo caído menos de {$windowMinutes} min. Sin escalamiento.";
        }

        return implode("\n", $lines);
    }

    public static function versionChanged(
        string $siteName,
        string $url,
        ?string $from,
        ?string $to,
        string $severityAfter,
        ?string $phpVersion,
    ): string {
        $lines = [
            "🔵 WP ACTUALIZADO: {$siteName}",
            "URL: {$url}",
            "Core: " . ($from ?? 'desconocido') . " → " . ($to ?? 'desconocido'),
            "Severidad actual: {$severityAfter}",
        ];
        if ($phpVersion !== null && $phpVersion !== '') {
            $lines[] = "PHP del sitio: {$phpVersion}";
        }

        return implode("\n", $lines);
    }

    /**
     * Resumen periódico del flujo de páginas (24h).
     *
     * @param array{from:string,to:string,total:int,chks:int,failures:int,availability:float|null,
     *               avg_ms:int|null,by_state:array<string,int>,down:array<int, array{name:string,state:string,failures:int}>,
     *               sev:array{red:int,yellow:int,green:int}} $stats
     */
    public static function digest(array $stats): string
    {
        $lines = [
            '📊 RESUMEN 24h — Mediadev Monitor',
            "Ventana: {$stats['from']} → {$stats['to']}",
            "Sitios: {$stats['total']} · Checks: {$stats['chks']} · Fallidos: {$stats['failures']}",
        ];

        if ($stats['availability'] !== null) {
            $lines[] = 'Disponibilidad: ' . number_format($stats['availability'], 2) . '%';
        }
        if ($stats['avg_ms'] !== null) {
            $lines[] = "Respuesta media: {$stats['avg_ms']} ms";
        }

        $order = [
            SiteState::DOWN->value => 'down',
            SiteState::WP_DEGRADED->value => 'wp-degraded',
            SiteState::WP_FULL->value => 'wp-full',
            SiteState::NON_WP->value => 'non-wp',
            SiteState::UNKNOWN->value => 'unknown',
        ];
        $parts = [];
        foreach ($order as $key => $label) {
            $count = (int) ($stats['by_state'][$key] ?? 0);
            if ($count > 0) {
                $parts[] = "{$label} {$count}";
            }
        }
        $lines[] = 'Estados: ' . ($parts !== [] ? implode(' · ', $parts) : 'sin datos');

        $lines[] = sprintf(
            'Severidad WP: red %d · yellow %d · green %d',
            $stats['sev']['red'],
            $stats['sev']['yellow'],
            $stats['sev']['green'],
        );

        if ($stats['down'] !== []) {
            $lines[] = 'Caídos ahora:';
            foreach ($stats['down'] as $d) {
                $lines[] = "- {$d['name']} ({$d['state']}, {$d['failures']} fallos)";
            }
        } else {
            $lines[] = 'Caídos ahora: ninguno';
        }

        return implode("\n", $lines);
    }
}
