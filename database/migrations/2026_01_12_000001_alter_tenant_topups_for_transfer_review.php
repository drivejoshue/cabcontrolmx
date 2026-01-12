<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_topups', function (Blueprint $table) {
            // Para transferencias: cuenta 1 o 2
            if (!Schema::hasColumn('tenant_topups', 'provider_account_slot')) {
                $table->unsignedTinyInteger('provider_account_slot')->nullable()->after('method'); // 1|2
            }

            // Estados de revisión (solo para transfer)
            if (!Schema::hasColumn('tenant_topups', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('proof_path');
                $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
                $table->string('review_status', 20)->nullable()->after('reviewed_at'); // pending|approved|rejected
                $table->string('review_notes', 500)->nullable()->after('review_status');
            }

            // Opcional: si quieres amarrar un pago directo a factura
            if (!Schema::hasColumn('tenant_topups', 'apply_invoice_id')) {
                $table->unsignedBigInteger('apply_invoice_id')->nullable()->after('external_reference');
                $table->index('apply_invoice_id');
            }

            $table->index(['tenant_id','provider','status']);
            $table->index(['tenant_id','provider','review_status']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_topups', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_topups', 'provider_account_slot')) {
                $table->dropColumn('provider_account_slot');
            }
            if (Schema::hasColumn('tenant_topups', 'reviewed_by')) {
                $table->dropColumn(['reviewed_by','reviewed_at','review_status','review_notes']);
            }
            if (Schema::hasColumn('tenant_topups', 'apply_invoice_id')) {
                $table->dropIndex(['apply_invoice_id']);
                $table->dropColumn('apply_invoice_id');
            }
        });
    }
};
