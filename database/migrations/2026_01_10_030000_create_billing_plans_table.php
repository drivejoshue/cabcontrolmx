<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();         // PV_STARTER, PV_PRO, etc
            $table->string('name', 120);
            $table->string('billing_model', 30)->default('per_vehicle'); // per_vehicle / commission
            $table->string('currency', 10)->default('MXN');

            $table->decimal('base_monthly_fee', 10, 2)->default(0);
            $table->unsignedInteger('included_vehicles')->default(0);
            $table->decimal('price_per_vehicle', 10, 2)->default(0);

            $table->boolean('active')->default(true);
            $table->timestamp('effective_from')->nullable(); // opcional (para historial)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plans');
    }
};
