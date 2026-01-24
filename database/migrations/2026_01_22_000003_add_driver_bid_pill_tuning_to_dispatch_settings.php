<?php

// database/migrations/2026_01_22_000003_add_driver_bid_pill_tuning_to_dispatch_settings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dispatch_settings', function (Blueprint $t) {

            if (!Schema::hasColumn('dispatch_settings', 'driver_bid_step_percent')) {
                // guardamos porcentaje como enteros “basis points” para evitar floats en DB: 800 = 8.00%
                $t->unsignedSmallInteger('driver_bid_step_bps')->default(800)->after('taxi_stands_enabled');
            }

            if (!Schema::hasColumn('dispatch_settings', 'driver_bid_step_min_amount')) {
                $t->unsignedSmallInteger('driver_bid_step_min_amount')->default(5)->after('driver_bid_step_bps');
            }

            if (!Schema::hasColumn('dispatch_settings', 'driver_bid_step_max_amount')) {
                $t->unsignedSmallInteger('driver_bid_step_max_amount')->default(25)->after('driver_bid_step_min_amount');
            }

            if (!Schema::hasColumn('dispatch_settings', 'driver_bid_tiers')) {
                $t->unsignedTinyInteger('driver_bid_tiers')->default(3)->after('driver_bid_step_max_amount');
            }

            if (!Schema::hasColumn('dispatch_settings', 'driver_bid_round_to')) {
                $t->unsignedTinyInteger('driver_bid_round_to')->default(5)->after('driver_bid_tiers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_settings', function (Blueprint $t) {
            if (Schema::hasColumn('dispatch_settings', 'driver_bid_step_bps')) $t->dropColumn('driver_bid_step_bps');
            if (Schema::hasColumn('dispatch_settings', 'driver_bid_step_min_amount')) $t->dropColumn('driver_bid_step_min_amount');
            if (Schema::hasColumn('dispatch_settings', 'driver_bid_step_max_amount')) $t->dropColumn('driver_bid_step_max_amount');
            if (Schema::hasColumn('dispatch_settings', 'driver_bid_tiers')) $t->dropColumn('driver_bid_tiers');
            if (Schema::hasColumn('dispatch_settings', 'driver_bid_round_to')) $t->dropColumn('driver_bid_round_to');
        });
    }
};
