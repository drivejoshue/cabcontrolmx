<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispatch_outbox')) {
            return;
        }

        Schema::create('dispatch_outbox', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('ride_id')->nullable();
            $t->unsignedBigInteger('offer_id')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable();

            // offer.new | offer.update | ride.update | ...
            $t->string('topic', 64);

            // clave idempotente para evitar duplicados por reintentos
            $t->string('dedupe_key', 190);

            // payload opcional
            $t->json('payload')->nullable();

            // PENDING | PROCESSING | SENT | FAILED | DEAD
            $t->string('status', 24)->default('PENDING');

            $t->unsignedSmallInteger('attempts')->default(0);

            // retry/backoff y lock
            $t->dateTime('available_at')->nullable();
            $t->dateTime('locked_at')->nullable();
            $t->string('locked_by', 64)->nullable();

            $t->text('last_error')->nullable();

            $t->timestamps();

            // Índices
            $t->index('tenant_id');
            $t->index('ride_id');
            $t->index('offer_id');
            $t->index('driver_id');
            $t->index('topic');
            $t->index('status');

            // Para “claim” eficiente en el worker: filtrar por estado + available_at
            $t->index(['status', 'available_at'], 'dispatch_outbox_status_available_idx');

            // Lock lookup
            $t->index('locked_at');
            $t->index('locked_by');

            // Único idempotencia
            $t->unique('dedupe_key', 'dispatch_outbox_dedupe_key_uq');
        });

        // Opcional: FKs (actívalas solo si tus tablas existen y te conviene bloquear deletes)
        Schema::table('dispatch_outbox', function (Blueprint $t) {
            // Si manejas multi-tenant estricto, suele convenir RESTRICT/NO ACTION
            // Si borras rides/offers/drivers, normalmente conviene SET NULL.
            if (Schema::hasTable('tenants')) {
                $t->foreign('tenant_id', 'dispatch_outbox_tenant_fk')
                    ->references('id')->on('tenants')
                    ->onDelete('cascade');
            }

            if (Schema::hasTable('rides')) {
                $t->foreign('ride_id', 'dispatch_outbox_ride_fk')
                    ->references('id')->on('rides')
                    ->onDelete('set null');
            }

            if (Schema::hasTable('ride_offers')) {
                $t->foreign('offer_id', 'dispatch_outbox_offer_fk')
                    ->references('id')->on('ride_offers')
                    ->onDelete('set null');
            }

            // Ajusta el nombre real de tu tabla de drivers:
            // - si es users (role=driver) -> users
            // - si es drivers -> drivers
            if (Schema::hasTable('drivers')) {
                $t->foreign('driver_id', 'dispatch_outbox_driver_fk')
                    ->references('id')->on('drivers')
                    ->onDelete('set null');
            } elseif (Schema::hasTable('users')) {
                $t->foreign('driver_id', 'dispatch_outbox_driver_fk')
                    ->references('id')->on('users')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('dispatch_outbox')) {
            return;
        }

        // Drop FKs de forma segura
        Schema::table('dispatch_outbox', function (Blueprint $t) {
            // Ignora si no existen (por si los comentaste)
            try { $t->dropForeign('dispatch_outbox_tenant_fk'); } catch (\Throwable $e) {}
            try { $t->dropForeign('dispatch_outbox_ride_fk'); } catch (\Throwable $e) {}
            try { $t->dropForeign('dispatch_outbox_offer_fk'); } catch (\Throwable $e) {}
            try { $t->dropForeign('dispatch_outbox_driver_fk'); } catch (\Throwable $e) {}
        });

        Schema::dropIfExists('dispatch_outbox');
    }
};
