<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('partner_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('partner_id');
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('currency', 8)->default('MXN');
            $table->timestamp('last_topup_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id','partner_id'], 'uq_partner_wallet_tenant_partner');
            $table->index(['tenant_id'], 'ix_partner_wallet_tenant');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
        });

        Schema::create('partner_wallet_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('partner_id');

            $table->string('type', 24); // credit|debit
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('MXN');

            $table->string('ref_type', 32)->nullable(); // topup|invoice|adjustment|...
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('external_ref', 128)->nullable(); // mp_payment_id, bank_ref, etc
            $table->string('notes', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id','partner_id','created_at'], 'ix_pwm_tenant_partner_time');
            $table->index(['tenant_id','ref_type','ref_id'], 'ix_pwm_tenant_ref');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_wallet_movements');
        Schema::dropIfExists('partner_wallets');
    }
};
