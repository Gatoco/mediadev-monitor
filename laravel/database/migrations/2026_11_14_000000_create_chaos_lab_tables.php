<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaos Lab: experimentos de falla provocada + registro de alertas enviadas.
 *
 * notification_log es la evidencia de que la alerta salió (y por qué canal):
 * el lab mide "falla provocada → Caído detectado → alerta enviada".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            // down | recovered | version_changed | info
            $table->string('kind', 32);
            $table->string('site_name')->nullable();
            $table->text('text');
            // Canales que aceptaron el mensaje, ej. "telegram,email". Vacío = ninguno.
            $table->string('channels', 64)->default('');
            $table->boolean('delivered')->default(false);
            $table->timestamp('sent_at')->useCurrent();
            $table->index(['site_id', 'sent_at']);
        });

        Schema::create('chaos_experiments', function (Blueprint $table) {
            $table->id();
            // caida_total | error500 | lento | degradado | intermitente | masivo | manual
            $table->string('scenario', 32);
            // container | site
            $table->string('target_type', 16);
            // Contenedores (servicio compose) o hosts emulados afectados.
            $table->json('targets');
            // Modo inyectado: stop | caido | error500 | lento | degradado | intermitente
            $table->string('fault', 16);
            // down | wp-degraded | none (intermitente: no debería alertar)
            $table->string('expected', 16);
            $table->timestamp('injected_at');
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            // running | finished
            $table->string('status', 16)->default('running');
            // pass | fail (al terminar)
            $table->string('result', 8)->nullable();
            $table->timestamps();
            $table->index(['status', 'injected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chaos_experiments');
        Schema::dropIfExists('notification_log');
    }
};
