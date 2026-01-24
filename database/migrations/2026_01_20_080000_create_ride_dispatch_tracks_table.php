<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ride_dispatch_tracks')) return;

        Schema::create('ride_dispatch_tracks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('ride_id');

            // Stand detectado (si aplica)
            $table->unsignedBigInteger('stand_id')->nullable()->index();

            // Estado del flujo
            $table->enum('state', [
                'stand_active',
                'street_active',
                'completed',
                'canceled',
            ])->default('stand_active')->index();

            // -------------------------
            // BASE (one-by-one)
            // -------------------------
            $table->unsignedSmallInteger('stand_step_sec')->default(30);
            $table->unsignedSmallInteger('stand_attempt_no')->default(0);

            $table->unsignedBigInteger('stand_current_driver_id')->nullable()->index();
            $table->unsignedBigInteger('stand_current_offer_id')->nullable()->index();

            $table->dateTime('stand_next_action_at')->nullable()->index();

            // -------------------------
            // CALLE (wave + top-up)
            // -------------------------
            $table->decimal('street_radius_km', 8, 2)->nullable();
            $table->unsignedSmallInteger('street_target_n')->default(8);
            $table->unsignedSmallInteger('street_sent_n')->default(0);

            $table->dateTime('street_expires_at')->nullable()->index();
            $table->dateTime('street_last_fill_at')->nullable()->index();

            // -------------------------
            // LOCK simple (MariaDB-friendly)
            // -------------------------
            $table->string('locked_by', 64)->nullable();
            $table->dateTime('locked_at')->nullable()->index();

            $table->timestamps();

            // Un track por ride
            $table->unique(['tenant_id', 'ride_id'], 'uq_rdt_tenant_ride');

            // FKs (opcional pero recomendable)
            $table->foreign('tenant_id', 'fk_rdt_tenant')
                ->references('id')->on('tenants')
                ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('ride_id', 'fk_rdt_ride')
                ->references('id')->on('rides')
                ->onDelete('cascade')->onUpdate('cascade');

            // Si existe taxi_stands:
            if (Schema::hasTable('taxi_stands')) {
                $table->foreign('stand_id', 'fk_rdt_stand')
                    ->references('id')->on('taxi_stands')
                    ->onDelete('set null')->onUpdate('cascade');
            }

            // Drivers / Offers existen en tu esquema
            if (Schema::hasTable('drivers')) {
                $table->foreign('stand_current_driver_id', 'fk_rdt_cur_driver')
                    ->references('id')->on('drivers')
                    ->onDelete('set null')->onUpdate('cascade');
            }

            if (Schema::hasTable('ride_offers')) {
                $table->foreign('stand_current_offer_id', 'fk_rdt_cur_offer')
                    ->references('id')->on('ride_offers')
                    ->onDelete('set null')->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_dispatch_tracks');
    }
};
