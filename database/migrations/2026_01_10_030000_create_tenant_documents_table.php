<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_documents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');

            // id_official | proof_address | tax_certificate
            $table->string('type', 32);

            // pending | approved | rejected
            $table->string('status', 16)->default('pending');

            $table->string('disk', 32)->default('local'); // privado
            $table->string('path', 500);                  // storage path
            $table->string('original_name', 255)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('uploaded_at')->nullable();

            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_notes', 400)->nullable();

            $table->timestamps();

            $table->unique(['tenant_id','type'], 'tenant_docs_unique');
            $table->index(['tenant_id','status']);
            $table->index(['type']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            // reviewed_by / uploaded_by -> users (si aplica)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_documents');
    }
};
