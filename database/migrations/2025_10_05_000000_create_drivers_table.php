<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            // Central / empresa a la que pertenece el chofer
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->unique();
            $table->string('document_id')->nullable(); // licencia/INE opcional
            $table->enum('status', ['offline','idle','busy'])->default('offline')->index();

            // Última ubicación (para tablero en tiempo real)
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->float('last_bearing')->nullable(); // rumbo
            $table->float('last_speed')->nullable();   // m/s o km/h según definas
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            // Índice geoespacial simple: por lat/lng (útil para “cercanos”)
            $table->index(['last_lat', 'last_lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
