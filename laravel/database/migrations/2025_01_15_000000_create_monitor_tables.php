<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Sites table — replicates original SQLite schema with TEXT defaults
        if (!Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table) {
                $table->id();
                $table->string('url')->unique();
                $table->string('name');
                $table->string('type')->default('auto');
                $table->string('wp_user')->nullable();
                $table->text('ap_token')->nullable();
                $table->integer('consecutive_failures')->default(0);
                $table->string('current_state')->default('unknown');
                $table->timestamp('created_at')->default(now());
                $table->timestamp('updated_at')->default(now());
            });
        }

        // Uptime checks table
        if (!Schema::hasTable('uptime_checks')) {
            Schema::create('uptime_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->timestamp('ts')->default(now());
                $table->integer('status')->nullable();
                $table->integer('response_ms')->nullable();
                $table->string('tls_state')->nullable();
            });
        }

        // Version snapshots table
        if (!Schema::hasTable('version_snapshots')) {
            Schema::create('version_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->timestamp('ts')->default(now());
                $table->string('core_version')->nullable();
                $table->text('plugins_json')->nullable();
                $table->text('themes_json')->nullable();
                $table->text('pending_json')->nullable();
                $table->string('severity')->default('green');
            });
        }

        // Site health snapshots table
        if (!Schema::hasTable('site_health_snapshots')) {
            Schema::create('site_health_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->timestamp('ts')->default(now());
                $table->text('tests_json')->nullable();
                $table->integer('score')->nullable();
            });
        }

        // Activity snapshots table
        if (!Schema::hasTable('activity_snapshots')) {
            Schema::create('activity_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->timestamp('ts')->default(now());
                $table->text('posts_json')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_snapshots');
        Schema::dropIfExists('site_health_snapshots');
        Schema::dropIfExists('version_snapshots');
        Schema::dropIfExists('uptime_checks');
        Schema::dropIfExists('sites');
    }
};
