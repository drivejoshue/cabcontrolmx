<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_baselines', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('key', 64)->unique();          // ej: "2026-01-24"
            $t->string('name', 190);                  // ej: "Baseline SP accept_offer_v7 released losers"
            $t->text('notes')->nullable();            // opcional
            $t->timestamp('applied_at')->useCurrent();
        });

        DB::table('schema_baselines')->insert([
            'key'   => '2026-01-24',
            'name'  => 'Baseline: sp_accept_offer_v7 losers => released (RT cleanup aligned)',
            'notes' => 'Marca de corte: a partir de esta fecha se considera estable la semántica released para losers en accept_offer_v7.',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_baselines');
    }
};
