<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();

            // Estado
            $table->boolean('active')->default(true);

            // Datos básicos (requeridos mínimos)
            $table->string('display_name', 190);     // “Orbana” / “Orbana Dispatch”
            $table->string('contact_name', 190);     // César Josue Méndez Costeño
            $table->string('phone', 50)->nullable();
            $table->string('email_support', 190)->nullable(); // soporte@...
            $table->string('email_admin', 190)->nullable();   // admin@...

            // Dirección comercial / contacto
            $table->string('address_line1', 190)->nullable();
            $table->string('address_line2', 190)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('country', 120)->default('México');
            $table->string('postal_code', 20)->nullable();

            // Datos fiscales
            $table->string('legal_name', 190)->nullable();     // Razón social
            $table->string('rfc', 30)->nullable();
            $table->string('tax_regime', 120)->nullable();     // Régimen fiscal
            $table->text('fiscal_address')->nullable();        // Domicilio fiscal (texto)
            $table->string('cfdi_use_default', 50)->nullable(); // opcional (G03, etc.)
            $table->string('tax_zip', 20)->nullable();         // CP fiscal (si lo quieres separado)

            // 2 cuentas fijas (sin tabla extra)
            $table->string('acc1_bank', 120)->nullable();
            $table->string('acc1_beneficiary', 190)->nullable();
            $table->string('acc1_account', 50)->nullable();
            $table->string('acc1_clabe', 30)->nullable();

            $table->string('acc2_bank', 120)->nullable();
            $table->string('acc2_beneficiary', 190)->nullable();
            $table->string('acc2_account', 50)->nullable();
            $table->string('acc2_clabe', 30)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
    }
};
