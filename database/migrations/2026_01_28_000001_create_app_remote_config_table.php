<?php

// ============================
// 1) MIGRATION
// database/migrations/2026_01_28_000001_create_app_remote_config_table.php
// ============================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_remote_config', function (Blueprint $table) {
            $table->id();
            $table->string('app', 32); // passenger | driver
            $table->unsignedInteger('min_version_code')->default(1);
            $table->unsignedInteger('latest_version_code')->nullable();
            $table->boolean('force_update')->default(false);
            $table->string('message', 255)->nullable();
            $table->string('play_url', 255)->nullable();
            $table->timestamps();

            $table->unique('app');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_remote_config');
    }
};
