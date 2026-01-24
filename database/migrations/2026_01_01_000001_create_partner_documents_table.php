<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('partner_documents')) return;

        Schema::create('partner_documents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('partner_id')->index();

            $table->enum('type', [
                'ine',
                'rfc',
                'constancia_situacion_fiscal',
                'comprobante_domicilio',
                'caratula_estado_cuenta',
                'contrato',
                'otro'
            ]);

            $table->string('status', 16)->default('pending'); // pending/approved/rejected/expired
            $table->string('disk', 32)->default('local');
            $table->string('path', 500);

            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('uploaded_at')->nullable();

            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_notes', 400)->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id','partner_id','type'], 'uq_partner_docs_partner_type');
            $table->index(['tenant_id','status','type'], 'ix_partner_docs_tenant_status_type');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partner_documents')) return;
        Schema::dropIfExists('partner_documents');
    }
};
