<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de notificaciones por sitio+evento.
 *
 * Clave para idempotencia: solo se dispara UNA alerta por transición.
 * El detector marca cada evento con la firma del snapshot que lo produjo
 * (ej. ts del último uptime check) y este registro guarda qué firma ya se
 * notificó. Si el comando se re-corre, no spamea.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_states')) {
            Schema::create('notification_states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                // down | recovered | version_changed | token_invalid
                $table->string('kind', 32);
                // Firma del evento notificado (ts/id del snapshot). NULL = nunca notificado.
                $table->string('fingerprint', 64)->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
                $table->unique(['site_id', 'kind']);
            });
        }

        // Resultado de la última verificación del Application Password WP.
        if (!Schema::hasColumn('sites', 'wp_token_status')) {
            Schema::table('sites', function (Blueprint $table) {
                // unknown | valid | invalid | unreachable
                // Nota SQLite: ->after() no tiene efecto, las columnas van al final.
                $table->string('wp_token_status', 16)->default('unknown');
                $table->string('wp_token_message', 255)->nullable();
                $table->timestamp('wp_token_checked_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_states');
        if (Schema::hasColumn('sites', 'wp_token_status')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropColumn(['wp_token_status', 'wp_token_message', 'wp_token_checked_at']);
            });
        }
    }
};
