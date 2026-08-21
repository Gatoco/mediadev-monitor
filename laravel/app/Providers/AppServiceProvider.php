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

        // Domain infrastructure used by the collectors.
        $this->app->singleton(RestClient::class, static fn () => new RestClient());
        $this->app->singleton(SiteRegistry::class, static fn ($app) => new SiteRegistry($app->make(SiteRepository::class)));
        $this->app->singleton(Collector::class, static function ($app) {
            return new Collector(
                $app->make(SiteRegistry::class),
                $app->make(RestClient::class),
                $app->make(UptimeCheckRepository::class),
                $app->make(VersionSnapshotRepository::class),
                $app->make(SiteHealthSnapshotRepository::class),
                $app->make(ActivitySnapshotRepository::class),
                storage_path('app'),
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
