<?php

/**
 * Mediadev Monitor — CLI: Reporter (tabla terminal + ANSI + exit codes).
 */

declare(strict_types=1);

namespace MediadevMonitor\Cli;

use MediadevMonitor\Collector\CollectionReport;
use MediadevMonitor\SiteRegistry\SiteState;

final class Reporter
{
    private bool $color;

    public function __construct(?bool $color = null)
    {
        $this->color = $color ?? (function_exists('posix_isatty') && posix_isatty(STDOUT));
    }

    public function render(CollectionReport $report): string
    {
        $lines = [];
        $lines[] = $this->paint("Mediadev Monitor — Reporte", 'bold');
        $lines[] = str_repeat('─', 72);
        $lines[] = sprintf("%-4s %-36s %-12s %s", 'ID', 'SITIO', 'ESTADO', 'DETALLE');

        foreach ($report->sites as $siteReport) {
            $lines[] = sprintf(
                "%-4d %-36s %-12s %s",
                $siteReport->site->id,
                $siteReport->site->name,
                $this->stateLabel($siteReport->state),
                $this->detail($siteReport),
            );
        }

        $lines[] = str_repeat('─', 72);

        $down = count(array_filter(
            $report->sites,
            fn ($r) => $r->state === SiteState::DOWN
        ));

        $lines[] = sprintf(
            "Total: %d sitios · %d caídos",
            count($report->sites),
            $down,
        );

        return implode("\n", $lines) . "\n";
    }

    private function stateLabel(SiteState $state): string
    {
        return match ($state) {
            SiteState::WP_FULL => $this->paint('wp-full', 'green'),
            SiteState::WP_DEGRADED => $this->paint('wp-degraded', 'yellow'),
            SiteState::NON_WP => $this->paint('non-wp', 'cyan'),
            SiteState::DOWN => $this->paint('DOWN', 'red'),
            SiteState::UNKNOWN => $this->paint('unknown', 'gray'),
        };
    }

    private function detail(object $siteReport): string
    {
        $bits = [];
        $metrics = $siteReport->metrics;

        if (isset($metrics['uptime']) && $metrics['uptime']->status !== 0) {
            $bits[] = 'HTTP ' . $metrics['uptime']->status;
        }
        if (isset($metrics['versions']['severity'])) {
            $bits[] = 'severity=' . $metrics['versions']['severity'];
        }

        return implode(' ', $bits);
    }

    private function paint(string $text, string $style): string
    {
        if (!$this->color) {
            return $text;
        }

        $codes = [
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'cyan' => "\033[36m",
            'gray' => "\033[90m",
            'bold' => "\033[1m",
            'reset' => "\033[0m",
        ];

        return $codes[$style] . $text . $codes['reset'];
    }
}
