<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ride_shares', function (Blueprint $table) {
            if (!Schema::hasColumn('ride_shares', 'last_viewed_at')) {
                $table->timestamp('last_viewed_at')->nullable()->after('revoked_at');
            }

            if (!Schema::hasColumn('ride_shares', 'views_count')) {
                $table->unsignedInteger('views_count')->default(0)->after('last_viewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ride_shares', function (Blueprint $table) {
            if (Schema::hasColumn('ride_shares', 'views_count')) {
                $table->dropColumn('views_count');
            }
            if (Schema::hasColumn('ride_shares', 'last_viewed_at')) {
                $table->dropColumn('last_viewed_at');
            }
        });
    }
};
