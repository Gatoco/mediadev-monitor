<?php

namespace App\Console\Commands;

use App\Models\Site;
use Domain\Collector\Collector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Datos sintéticos para la demo sin internet: 24 h de checks sanos por sitio.
 * Reemplaza uptime_checks y version_snapshots; deja los sitios en verde (o No-WP).
 */
class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed';

    protected $description = 'Seed 24 h of healthy synthetic telemetry for the offline demo';

    private const WP_VERSIONS = ['7.1', '7.1', '7.1', '7.0.4', '6.8.8'];

    public function handle(Collector $collector): int
    {
        // Resolver el Collector sincroniza config/sites.php con la tabla sites.
        DB::table('uptime_checks')->delete();
        DB::table('version_snapshots')->delete();

        $now = now();
        foreach (Site::all() as $site) {
            $nonWp = $site->type === 'non-wp';
            $base = 60 + crc32($site->name) % 260;

            $rows = [];
            for ($i = 288; $i >= 0; $i--) {
                $rows[] = [
                    'site_id' => $site->id,
                    'ts' => $now->copy()->subMinutes($i * 5)->toDateTimeString(),
                    'status' => 200,
                    'response_ms' => $base + random_int(-25, 45),
                    'tls_state' => null,
                ];
            }
            foreach (array_chunk($rows, 300) as $chunk) {
                DB::table('uptime_checks')->insert($chunk);
            }

            if (! $nonWp) {
                DB::table('version_snapshots')->insert([
                    'site_id' => $site->id,
                    'ts' => $now->toDateTimeString(),
                    'core_version' => self::WP_VERSIONS[crc32($site->name) % count(self::WP_VERSIONS)],
                    'severity' => 'green',
                ]);
            }

            $site->update(['current_state' => $nonWp ? 'non-wp' : 'wp-full', 'consecutive_failures' => 0]);
        }

        $this->info(Site::count().' sitios con telemetría de demo');

        return self::SUCCESS;
    }
}
