<?php

namespace App\Providers;

use App\Models\ActivitySnapshot;
use App\Models\Site;
use App\Models\SiteHealthSnapshot;
use App\Models\UptimeCheck;
use App\Models\VersionSnapshot;
use App\Repositories\EloquentActivitySnapshotRepository;
use App\Repositories\EloquentSiteHealthSnapshotRepository;
use App\Repositories\EloquentSiteRepository;
use App\Repositories\EloquentUptimeCheckRepository;
use App\Repositories\EloquentVersionSnapshotRepository;
use App\Support\SiteConfig;
use Domain\Collector\Collector;
use Domain\Infra\RestClient;
use Domain\Port\ActivitySnapshotRepository;
use Domain\Port\SiteHealthSnapshotRepository;
use Domain\Port\SiteRepository;
use Domain\Port\UptimeCheckRepository;
use Domain\Port\VersionSnapshotRepository;
use Domain\SiteRegistry\SiteRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SiteRepository::class, static fn () => new EloquentSiteRepository(new Site()));
        $this->app->singleton(UptimeCheckRepository::class, static fn () => new EloquentUptimeCheckRepository(new UptimeCheck()));
        $this->app->singleton(VersionSnapshotRepository::class, static fn () => new EloquentVersionSnapshotRepository(new VersionSnapshot()));
        $this->app->singleton(SiteHealthSnapshotRepository::class, static fn () => new EloquentSiteHealthSnapshotRepository(new SiteHealthSnapshot()));
        $this->app->singleton(ActivitySnapshotRepository::class, static fn () => new EloquentActivitySnapshotRepository(new ActivitySnapshot()));

        $this->app->singleton(SiteRegistry::class, static fn ($app) => new SiteRegistry($app->make(SiteRepository::class)));

        // The Collector orchestrates the domain collectors. It is wired here so
        // both the artisan commands and the scheduler share the same instance
        // (singleton repositories keep Eloquent identity consistent).
        $this->app->singleton(Collector::class, static function ($app) {
            $registry = $app->make(SiteRegistry::class);

            $registry->syncFromConfig(SiteConfig::sites());

            return new Collector(
                registry: $registry,
                client: new RestClient(),
                uptimeRepo: $app->make(UptimeCheckRepository::class),
                versionRepo: $app->make(VersionSnapshotRepository::class),
                healthRepo: $app->make(SiteHealthSnapshotRepository::class),
                activityRepo: $app->make(ActivitySnapshotRepository::class),
                cacheDir: config('mediadev.cache_dir'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
