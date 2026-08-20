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
use Domain\Port\ActivitySnapshotRepository;
use Domain\Port\SiteHealthSnapshotRepository;
use Domain\Port\SiteRepository;
use Domain\Port\UptimeCheckRepository;
use Domain\Port\VersionSnapshotRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
