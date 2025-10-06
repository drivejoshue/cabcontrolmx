<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'last_lat')) {
                $table->decimal('last_lat', 10, 7)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('drivers', 'last_lng')) {
                $table->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
            }
            if (!Schema::hasColumn('drivers', 'last_bearing')) {
                $table->float('last_bearing')->nullable()->after('last_lng');
            }
            if (!Schema::hasColumn('drivers', 'last_speed')) {
                $table->float('last_speed')->nullable()->after('last_bearing');
            }
            if (!Schema::hasColumn('drivers', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('last_speed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['last_lat','last_lng','last_bearing','last_speed','last_seen_at']);
        });
    }
};
