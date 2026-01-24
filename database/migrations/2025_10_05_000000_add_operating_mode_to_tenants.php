<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tenants')) return;

        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'operating_mode')) {
                $table->enum('operating_mode', ['traditional','partner_network','hybrid','whitelabel'])
                    ->default('traditional')
                    ->after('allow_marketplace');
                $table->index(['operating_mode'], 'ix_tenants_operating_mode');
            }

            // Para controlar si la cobranza “por taxi” vive en wallet tenant o wallet partner
            if (!Schema::hasColumn('tenants', 'partner_billing_wallet')) {
                $table->enum('partner_billing_wallet', ['tenant_wallet','partner_wallet'])
                    ->default('tenant_wallet')
                    ->after('operating_mode');
            }

            if (!Schema::hasColumn('tenants', 'partner_require_assignment')) {
                $table->boolean('partner_require_assignment')->default(true)->after('partner_billing_wallet');
            }

            if (!Schema::hasColumn('tenants', 'partner_min_active_vehicles')) {
                $table->smallInteger('partner_min_active_vehicles')->unsigned()->default(0)->after('partner_require_assignment');
            }

            if (!Schema::hasColumn('tenants', 'partner_max_vehicles_per_partner')) {
                $table->smallInteger('partner_max_vehicles_per_partner')->unsigned()->nullable()->after('partner_min_active_vehicles');
            }
        });
    }

    public function down(): void
    {
        // Igual: si luego quieres revertir, lo armamos con drops condicionados.
    }
};
