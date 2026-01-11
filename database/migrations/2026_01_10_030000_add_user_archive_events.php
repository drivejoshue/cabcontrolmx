<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('user_archive_events', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('tenant_id');
      $t->unsignedBigInteger('user_id');
      $t->string('action', 20); // deactivated|reactivated
      $t->unsignedBigInteger('performed_by')->nullable(); // admin que hizo la acción
      $t->string('reason', 255)->nullable();
      $t->json('snapshot')->nullable(); // name/email/role/etc al momento
      $t->timestamp('created_at')->useCurrent();

      $t->index(['tenant_id','user_id']);
      $t->index(['tenant_id','action']);

      $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
      $t->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
      $t->foreign('performed_by')->references('id')->on('users')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('user_archive_events');
  }
};
