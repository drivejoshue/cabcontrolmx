<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partner_audit_events')) return;

        Schema::create('partner_audit_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('partner_id')->index();

            $table->string('action', 40); // e.g. partner.created, partner.updated, partner.wallet.topup, etc.
            $table->unsignedBigInteger('actor_user_id')->nullable();

            $table->string('target_type', 60)->nullable(); // 'driver','vehicle','charge','fee',etc
            $table->unsignedBigInteger('target_id')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->json('snapshot')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id','partner_id','created_at'], 'ix_partner_audit_tenant_partner_time');
            $table->index(['tenant_id','action','created_at'], 'ix_partner_audit_tenant_action_time');
            $table->index(['target_type','target_id'], 'ix_partner_audit_target');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_audit_events')) return;
        Schema::dropIfExists('partner_audit_events');
    }
};
