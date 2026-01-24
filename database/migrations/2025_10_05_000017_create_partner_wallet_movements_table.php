<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partner_wallet_movements')) return;

        Schema::create('partner_wallet_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('partner_id')->index();

            $table->enum('type', ['topup','debit','credit','fee','refund','adjust']);
            $table->enum('direction', ['credit','debit']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2)->nullable();

            $table->string('currency', 10)->default('MXN');

            $table->string('ref_type', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('external_ref', 190)->nullable();
            $table->string('notes', 255)->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id','partner_id','created_at'], 'ix_pwm_tenant_partner_time');
            $table->index(['ref_type','ref_id'], 'ix_pwm_ref');
            $table->index(['external_ref'], 'ix_pwm_external_ref');

            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_wallet_movements')) return;
        Schema::dropIfExists('partner_wallet_movements');
    }
};
