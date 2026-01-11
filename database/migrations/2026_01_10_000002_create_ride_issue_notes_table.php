<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ride_issue_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('ride_issue_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // admin/sysadmin que comentó
            $table->enum('visibility', ['tenant', 'platform'])->default('tenant'); // qué panel la ve
            $table->text('note');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('ride_issue_id')->references('id')->on('ride_issues')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ride_issue_notes');
    }
};