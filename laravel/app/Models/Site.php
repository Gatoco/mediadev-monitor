<?php

namespace App\Models;

use Domain\SiteRegistry\SiteState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory;

    protected $table = 'sites';

    protected $casts = [
        'current_state' => SiteState::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'url',
        'name',
        'type',
        'wp_user',
        'ap_token',
        'consecutive_failures',
        'current_state',
    ];

    public function uptimeChecks(): HasMany
    {
        return $this->hasMany(UptimeCheck::class)->orderBy('ts', 'desc');
    }

    public function versionSnapshots(): HasMany
    {
        return $this->hasMany(VersionSnapshot::class)->orderBy('ts', 'desc');
    }

    public function siteHealthSnapshots(): HasMany
    {
        return $this->hasMany(SiteHealthSnapshot::class)->orderBy('ts', 'desc');
    }

    public function activitySnapshots(): HasMany
    {
        return $this->hasMany(ActivitySnapshot::class)->orderBy('ts', 'desc');
    }

    public function latestUptime(): HasOne
    {
        return $this->hasOne(UptimeCheck::class)->latestOfMany('ts');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(VersionSnapshot::class)->latestOfMany('ts');
    }

    public function latestHealth(): HasOne
    {
        return $this->hasOne(SiteHealthSnapshot::class)->latestOfMany('ts');
    }

    public function latestActivity(): HasOne
    {
        return $this->hasOne(ActivitySnapshot::class)->latestOfMany('ts');
    }
}
