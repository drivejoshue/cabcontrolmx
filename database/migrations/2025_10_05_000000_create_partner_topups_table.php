<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partner_topups')) return;

        Schema::create('partner_topups', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('partner_id')->index();

            $table->string('provider', 30);
            $table->string('method', 30)->nullable();
            $table->tinyInteger('provider_account_slot')->unsigned()->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('MXN');

            $table->string('status', 20)->default('pending');

            // MercadoPago (o similar)
            $table->string('mp_preference_id', 80)->nullable();
            $table->string('mp_payment_id', 80)->nullable();
            $table->string('mp_status', 40)->nullable();
            $table->string('mp_status_detail', 120)->nullable();
            $table->text('init_point')->nullable();

            $table->string('external_reference', 120)->nullable();
            $table->string('bank_ref', 120)->nullable();

            $table->dateTime('deposited_at')->nullable();

            $table->string('payer_name', 120)->nullable();
            $table->string('payer_ref', 120)->nullable();

            $table->string('proof_path', 255)->nullable();

            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_status', 20)->nullable();
            $table->string('review_notes', 500)->nullable();

            $table->json('meta')->nullable();

            $table->dateTime('paid_at')->nullable();
            $table->dateTime('credited_at')->nullable();

            // Si quieres vincular con movement final al acreditar
            $table->unsignedBigInteger('apply_wallet_movement_id')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id','partner_id','external_reference'], 'uq_partner_topups_partner_extref');
            $table->index(['tenant_id','status'], 'ix_partner_topups_tenant_status');
            $table->index(['tenant_id','provider','status'], 'ix_partner_topups_tenant_provider_status');
            $table->index(['mp_preference_id'], 'ix_partner_topups_mp_pref');
            $table->index(['external_reference'], 'ix_partner_topups_extref');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            // apply_wallet_movement_id: FK opcional si lo quieres formalizar después
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_topups')) return;
        Schema::dropIfExists('partner_topups');
    }
};
