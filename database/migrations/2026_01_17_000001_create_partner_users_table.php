<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partner_users')) return;

        Schema::create('partner_users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('partner_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            $table->enum('role', ['owner','admin','staff','viewer'])->default('staff');
            $table->boolean('is_primary')->default(false);

            // Invitaciones / lifecycle
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            // Overrides por miembro (si luego necesitas granularidad)
            $table->json('permissions')->nullable();

            $table->timestamps();

            $table->unique(['partner_id', 'user_id'], 'uq_partner_users_partner_user');
            $table->index(['tenant_id', 'partner_id', 'role'], 'ix_partner_users_tenant_partner_role');
            $table->index(['tenant_id', 'user_id'], 'ix_partner_users_tenant_user');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_users')) return;
        Schema::dropIfExists('partner_users');
    }
};
