<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Agregar timeout_stand al enum response
        DB::statement("
            ALTER TABLE ride_offers
            MODIFY response ENUM(
                'accepted',
                'rejected',
                'expired',
                'canceled',
                'released',
                'timeout_stand'
            ) NULL
        ");
    }

    public function down(): void
    {
        // Si hay rows con timeout_stand, las bajamos a released para poder revertir el enum sin romper
        DB::statement("
            UPDATE ride_offers
            SET response='released'
            WHERE response='timeout_stand'
        ");

        DB::statement("
            ALTER TABLE ride_offers
            MODIFY response ENUM(
                'accepted',
                'rejected',
                'expired',
                'canceled',
                'released'
            ) NULL
        ");
    }
};
