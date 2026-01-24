<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('partner_wallet_movements')) return;

        Schema::table('partner_wallet_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('partner_wallet_movements', 'direction')) {
                $table->string('direction', 10)->nullable()->after('type'); // credit|debit
            }
            if (!Schema::hasColumn('partner_wallet_movements', 'balance_after')) {
                $table->decimal('balance_after', 12, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('partner_wallet_movements', 'meta')) {
                $table->json('meta')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        // No tirar columnas en producción (auditoría).
    }
};
