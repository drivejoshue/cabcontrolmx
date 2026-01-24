<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_wallet_movements', function (Blueprint $table) {
            // si no existe, asegúrate que sea nullable (ya lo es en tu dump)
            // Unique: permite múltiples NULL, pero evita duplicados cuando external_ref NO es NULL.
            $table->unique(['tenant_id','partner_id','external_ref'], 'uq_pwm_tenant_partner_extref');
        });
    }

    public function down(): void
    {
        Schema::table('partner_wallet_movements', function (Blueprint $table) {
            $table->dropUnique('uq_pwm_tenant_partner_extref');
        });
    }
};
