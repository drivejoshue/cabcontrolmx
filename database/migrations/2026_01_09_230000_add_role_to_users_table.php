<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // nullable para no romper nada al migrar
            $table->string('role', 20)->nullable()->after('email');
            $table->index(['tenant_id', 'role'], 'users_tenant_role_idx');
        });

        // Backfill seguro (MySQL/MariaDB)
        // 1) sysadmin
        DB::table('users')->where('is_sysadmin', 1)->update(['role' => 'sysadmin']);

        // 2) admin / dispatcher (solo si role aún está null para respetar sysadmin)
        DB::table('users')->whereNull('role')->where('is_admin', 1)->update(['role' => 'admin']);
        DB::table('users')->whereNull('role')->where('is_dispatcher', 1)->update(['role' => 'dispatcher']);

        // 3) drivers vinculados (solo si no son staff)
        $driverUserIds = DB::table('drivers')
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->values()
            ->all();

        if (!empty($driverUserIds)) {
            DB::table('users')
                ->whereNull('role')
                ->whereIn('id', $driverUserIds)
                ->update(['role' => 'driver']);
        }

        // 4) resto
        DB::table('users')->whereNull('role')->update(['role' => 'none']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_tenant_role_idx');
            $table->dropColumn('role');
        });
    }
};
