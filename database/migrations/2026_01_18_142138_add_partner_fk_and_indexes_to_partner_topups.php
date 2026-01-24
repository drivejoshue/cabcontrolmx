<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_topups', function (Blueprint $table) {
            // índice útil para el portal: where tenant_id + partner_id orderBy id desc
            if (!Schema::hasColumn('partner_topups', 'partner_id')) return;

            $table->index(['tenant_id','partner_id','id'], 'ix_partner_topups_tenant_partner_id');

            // FK a partners (si no existe ya)
            $table->foreign('partner_id')
                ->references('id')->on('partners')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('partner_topups', function (Blueprint $table) {
            // Ojo: el nombre real del FK puede variar; si te falla el drop,
            // lo ajustamos con SHOW CREATE TABLE.
            $table->dropForeign(['partner_id']);
            $table->dropIndex('ix_partner_topups_tenant_partner_id');
        });
    }
};
