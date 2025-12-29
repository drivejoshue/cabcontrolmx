<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('passengers', function (Blueprint $table) {
      $table->boolean('is_disabled')->default(false)->after('avatar_url');
      $table->timestamp('disabled_at')->nullable()->after('is_disabled');
      $table->string('disabled_reason', 190)->nullable()->after('disabled_at');
    });
  }

  public function down(): void {
    Schema::table('passengers', function (Blueprint $table) {
      $table->dropColumn(['is_disabled','disabled_at','disabled_reason']);
    });
  }
};