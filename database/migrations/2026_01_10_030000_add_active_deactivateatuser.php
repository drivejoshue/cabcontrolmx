<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'active')) {
                $t->tinyInteger('active')->default(1)->after('is_sysadmin');
                $t->index(['tenant_id', 'active'], 'users_tenant_active_idx');
                $t->index(['tenant_id', 'role', 'active'], 'users_tenant_role_active_idx');
            }
            if (!Schema::hasColumn('users', 'deactivated_at')) {
                $t->timestamp('deactivated_at')->nullable()->after('active');
            }
        });

        // Backfill seguro
        DB::table('users')->whereNull('active')->update(['active' => 1]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users','deactivated_at')) $t->dropColumn('deactivated_at');
            if (Schema::hasColumn('users','active')) $t->dropColumn('active');
            // índices (si existen)
            try { $t->dropIndex('users_tenant_active_idx'); } catch (\Throwable $e) {}
            try { $t->dropIndex('users_tenant_role_active_idx'); } catch (\Throwable $e) {}
        });
    }
};
