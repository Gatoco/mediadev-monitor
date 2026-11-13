<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// /status filtra por ventana de tiempo sobre una tabla que crece cada minuto.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uptime_checks', function (Blueprint $table) {
            $table->index('ts', 'uptime_checks_ts_index');
            $table->index(['site_id', 'ts'], 'uptime_checks_site_ts_index');
        });
    }

    public function down(): void
    {
        Schema::table('uptime_checks', function (Blueprint $table) {
            $table->dropIndex('uptime_checks_ts_index');
            $table->dropIndex('uptime_checks_site_ts_index');
        });
    }
};
