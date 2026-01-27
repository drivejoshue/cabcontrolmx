<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ride_offers', function (Blueprint $table) {
            $table->unsignedInteger('bid_seq')
                ->default(0)
                ->after('driver_offer');

            $table->dateTime('bid_expires_at')
                ->nullable()
                ->after('bid_seq');

            // Índice para barrer bids vencidos rápido
            $table->index(['tenant_id', 'status', 'bid_expires_at'], 'idx_ro_bid_exp');
        });
    }

    public function down(): void
    {
        Schema::table('ride_offers', function (Blueprint $table) {
            $table->dropIndex('idx_ro_bid_exp');
            $table->dropColumn(['bid_expires_at', 'bid_seq']);
        });
    }
};
