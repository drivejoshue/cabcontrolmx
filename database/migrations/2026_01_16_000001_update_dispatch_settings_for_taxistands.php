<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dispatch_settings', function (Blueprint $table) {
            // TaxiStand (one-by-one) step TTL
            if (!Schema::hasColumn('dispatch_settings', 'stand_step_sec')) {
                $table->unsignedInteger('stand_step_sec')
                    ->default(30)
                    ->after('stand_radius_km');
            }

            // Qué hacer al vencer turno en base
            // MariaDB: ENUM via raw
            if (!Schema::hasColumn('dispatch_settings', 'stand_on_timeout')) {
                // Lo agregamos como string y luego lo convertimos a ENUM para compatibilidad
                $table->string('stand_on_timeout', 16)
                    ->default('saltado')
                    ->after('stand_step_sec');
            }

            // Permitir candidatos on_ride en base/cola
            if (!Schema::hasColumn('dispatch_settings', 'stand_allow_onride')) {
                $table->boolean('stand_allow_onride')
                    ->default(false)
                    ->after('stand_on_timeout');
            }

            // Bonus ETA si el driver está on_ride (antes estaba fijo en 300)
            if (!Schema::hasColumn('dispatch_settings', 'stand_onride_eta_bonus_sec')) {
                $table->unsignedInteger('stand_onride_eta_bonus_sec')
                    ->default(300)
                    ->after('stand_allow_onride');
            }

            // Opcional: TTL global explícito (si no lo quieres, elimina este bloque)
            if (!Schema::hasColumn('dispatch_settings', 'offer_global_expires_sec')) {
                $table->unsignedInteger('offer_global_expires_sec')
                    ->nullable()
                    ->after('offer_expires_sec');
            }
        });

        // Convertir stand_on_timeout a ENUM si aún está como VARCHAR
        // (En MariaDB no hay "change()" con enum sin doctrine/dbal; hacemos raw)
        DB::statement("
            ALTER TABLE dispatch_settings
            MODIFY stand_on_timeout
            ENUM('saltado','salio') NOT NULL DEFAULT 'saltado'
        ");

        // Backfill: offer_global_expires_sec = offer_expires_sec donde sea NULL
        DB::statement("
            UPDATE dispatch_settings
            SET offer_global_expires_sec = offer_expires_sec
            WHERE offer_global_expires_sec IS NULL
        ");
    }

    public function down(): void
    {
        // Revertimos en orden seguro
        Schema::table('dispatch_settings', function (Blueprint $table) {
            if (Schema::hasColumn('dispatch_settings', 'offer_global_expires_sec')) {
                $table->dropColumn('offer_global_expires_sec');
            }
            if (Schema::hasColumn('dispatch_settings', 'stand_onride_eta_bonus_sec')) {
                $table->dropColumn('stand_onride_eta_bonus_sec');
            }
            if (Schema::hasColumn('dispatch_settings', 'stand_allow_onride')) {
                $table->dropColumn('stand_allow_onride');
            }
            if (Schema::hasColumn('dispatch_settings', 'stand_on_timeout')) {
                $table->dropColumn('stand_on_timeout');
            }
            if (Schema::hasColumn('dispatch_settings', 'stand_step_sec')) {
                $table->dropColumn('stand_step_sec');
            }
        });
    }
};
