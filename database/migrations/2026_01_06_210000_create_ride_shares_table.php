<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ⚠️ Rebuild for local/dev: drop & create
        Schema::dropIfExists('ride_shares');

        Schema::create('ride_shares', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('ride_id');

            // Amarrar a passenger real (resuelto por firebase_uid)
            $table->unsignedBigInteger('passenger_id')->nullable();

            // Token público (no adivinable)
            $table->string('token', 80)->unique();

            // active | ended | revoked
            $table->string('status', 20)->default('active')->index();

            // Control de vida
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['tenant_id', 'ride_id']);
            $table->index(['ride_id', 'status']);
            $table->index(['tenant_id', 'ride_id', 'status']);

            // FKs
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onDelete('cascade');

            $table->foreign('ride_id')
                ->references('id')->on('rides')
                ->onDelete('cascade');

            $table->foreign('passenger_id')
                ->references('id')->on('passengers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_shares');
    }
};
