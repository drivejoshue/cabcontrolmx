<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'active')) {
                $table->tinyInteger('active')->default(1)->after('role');
                $table->timestamp('disabled_at')->nullable()->after('active');
                $table->index(['tenant_id','role','active'], 'users_tenant_role_active_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'active')) {
                $table->dropIndex('users_tenant_role_active_idx');
                $table->dropColumn(['active','disabled_at']);
            }
        });
    }
};
