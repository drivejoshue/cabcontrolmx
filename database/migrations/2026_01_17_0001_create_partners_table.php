<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partners')) return;

        Schema::create('partners', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->index();

            // Identidad
            $table->string('code', 32);                 // código interno (por tenant)
            $table->string('slug', 160)->nullable();    // opcional para URLs
            $table->string('name', 190);
            $table->enum('kind', ['partner','recruiter','affiliate'])->default('partner');
            $table->enum('status', ['active','suspended','closed'])->default('active');
            $table->boolean('is_active')->default(true);

            // Contacto
            $table->string('contact_name', 190)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email', 190)->nullable();

            // Dirección (operativa)
            $table->string('address_line1', 190)->nullable();
            $table->string('address_line2', 190)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('country', 120)->default('México');
            $table->string('postal_code', 20)->nullable();

            // Fiscal (por si después facturas/recibos por partner)
            $table->string('legal_name', 190)->nullable();
            $table->string('rfc', 30)->nullable();
            $table->string('tax_regime', 120)->nullable();
            $table->text('fiscal_address')->nullable();
            $table->string('cfdi_use_default', 50)->nullable();
            $table->string('tax_zip', 20)->nullable();

            // Bancario (payout futuro)
            $table->string('payout_bank', 120)->nullable();
            $table->string('payout_beneficiary', 190)->nullable();
            $table->string('payout_account', 60)->nullable();
            $table->string('payout_clabe', 30)->nullable();
            $table->string('payout_notes', 255)->nullable();

            // Reglas económicas futuras (si decides revenue share o incentivos)
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->decimal('commission_fixed', 10, 2)->nullable();
            $table->enum('settlement_schedule', ['weekly','biweekly','monthly','manual'])->default('weekly');
            $table->tinyInteger('settlement_day')->unsigned()->nullable(); // 1-31 (según schedule)
            $table->enum('risk_level', ['low','normal','high'])->default('normal');

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            // Auditoría
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code'], 'uq_partners_tenant_code');
            $table->unique(['tenant_id', 'slug'], 'uq_partners_tenant_slug');
            $table->index(['tenant_id', 'status', 'is_active'], 'ix_partners_tenant_status');

            // FKs (solo para nuevas tablas, seguro)
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partners')) return;
        Schema::dropIfExists('partners');
    }
};
