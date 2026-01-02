<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('timezone');
        });

        // Default razonable para los existentes (ajusta si aplica)
        DB::table('tenants')->whereNull('country_code')->update(['country_code' => 'MX']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};