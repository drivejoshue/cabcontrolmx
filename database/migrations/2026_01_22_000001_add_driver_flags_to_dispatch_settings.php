<?php

// database/migrations/2026_01_22_000001_add_driver_flags_to_dispatch_settings.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dispatch_settings', function (Blueprint $t) {
            if (!Schema::hasColumn('dispatch_settings', 'taxi_stands_enabled')) {
                $t->boolean('taxi_stands_enabled')->default(true)->after('wave_size_n')->index();
            }

            // Opcional: versionado para el cliente
            if (!Schema::hasColumn('dispatch_settings', 'client_config_version')) {
                $t->unsignedInteger('client_config_version')->default(1)->after('taxi_stands_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_settings', function (Blueprint $t) {
            if (Schema::hasColumn('dispatch_settings', 'taxi_stands_enabled')) $t->dropColumn('taxi_stands_enabled');
            if (Schema::hasColumn('dispatch_settings', 'client_config_version')) $t->dropColumn('client_config_version');
        });
    }
};
