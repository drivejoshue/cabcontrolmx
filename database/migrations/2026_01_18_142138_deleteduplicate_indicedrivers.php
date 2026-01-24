<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $db = DB::getDatabaseName();

        $hasIndex = function(string $table, string $index) use ($db): bool {
            return DB::table('information_schema.statistics')
                ->where('table_schema', $db)
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        };

        // 1) drivers: quitar índice duplicado (status)
        if ($hasIndex('drivers', 'drivers_status_idx')) {
            DB::statement("ALTER TABLE `drivers` DROP INDEX `drivers_status_idx`");
        }

        // 2) (Opcional) si quieres normalizar otros redundantes, hazlo aquí,
        // pero SOLO después de validar con EXPLAIN tus queries/SPs.
    }

    public function down(): void
    {
        // No lo re-creo por defecto para no reintroducir redundancia.
        // Si lo quieres reversible, aquí podrías re-agregarlo.
    }
};
