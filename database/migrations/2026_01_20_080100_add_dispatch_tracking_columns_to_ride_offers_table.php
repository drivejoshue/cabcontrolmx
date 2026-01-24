<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ride_offers', function (Blueprint $table) {
            if (!Schema::hasColumn('ride_offers', 'dispatch_track_id')) {
                $table->unsignedBigInteger('dispatch_track_id')->nullable()->after('round_no')->index();
            }
            if (!Schema::hasColumn('ride_offers', 'dispatch_phase')) {
                $table->enum('dispatch_phase', ['stand','street'])->nullable()->after('dispatch_track_id')->index();
            }
            if (!Schema::hasColumn('ride_offers', 'dispatch_attempt_no')) {
                $table->unsignedSmallInteger('dispatch_attempt_no')->nullable()->after('dispatch_phase');
            }

            // Índices útiles para conteos / top-up
            $table->index(['tenant_id','dispatch_track_id','dispatch_phase'], 'idx_ro_track_phase_tenant');
            $table->index(['dispatch_track_id','dispatch_phase','driver_id'], 'idx_ro_track_phase_driver');
        });

        // FK separado para evitar problemas si el motor necesita re-orden
        Schema::table('ride_offers', function (Blueprint $table) {
            // FK lógico (si no quieres FK, comenta este bloque)
            $table->foreign('dispatch_track_id', 'fk_ro_dispatch_track')
                ->references('id')->on('ride_dispatch_tracks')
                ->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        // Primero drop FK e índices, luego columnas
        Schema::table('ride_offers', function (Blueprint $table) {
            if (Schema::hasColumn('ride_offers', 'dispatch_track_id')) {
                try { $table->dropForeign('fk_ro_dispatch_track'); } catch (\Throwable $e) {}
            }

            try { $table->dropIndex('idx_ro_track_phase_tenant'); } catch (\Throwable $e) {}
            try { $table->dropIndex('idx_ro_track_phase_driver'); } catch (\Throwable $e) {}

            if (Schema::hasColumn('ride_offers', 'dispatch_attempt_no')) {
                $table->dropColumn('dispatch_attempt_no');
            }
            if (Schema::hasColumn('ride_offers', 'dispatch_phase')) {
                $table->dropColumn('dispatch_phase');
            }
            if (Schema::hasColumn('ride_offers', 'dispatch_track_id')) {
                $table->dropColumn('dispatch_track_id');
            }
        });
    }
};
