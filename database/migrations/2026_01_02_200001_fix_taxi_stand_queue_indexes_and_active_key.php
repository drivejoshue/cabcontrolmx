<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // 1) Quitar UNIQUEs que rompen el historial (si existen)
        $this->dropIndexIfExists('taxi_stand_queue', 'uq_stand_driver_active');
        $this->dropIndexIfExists('taxi_stand_queue', 'ux_queue_unique_active');

        // 2) Crear columna generada active_key (solo para activos)
        // MariaDB: ADD COLUMN IF NOT EXISTS no siempre está disponible, así que revisamos antes.
        if (!$this->columnExists('taxi_stand_queue', 'active_key')) {
            DB::statement(<<<SQL
ALTER TABLE taxi_stand_queue
  ADD COLUMN active_key TINYINT
    AS (CASE WHEN status IN ('en_cola','saltado') THEN 1 ELSE NULL END) STORED
SQL);
        }

        // 3) Enforce: 1 activo por (tenant_id, driver_id)
        $this->addIndexIfNotExists(
            'taxi_stand_queue',
            'uq_driver_one_active',
            "ALTER TABLE taxi_stand_queue ADD UNIQUE KEY uq_driver_one_active (tenant_id, driver_id, active_key)"
        );

        // 4) Verificación mínima
        $this->assertIndexExists('taxi_stand_queue', 'uq_driver_one_active');
    }

    public function down(): void
    {
        // Down conservador: remover regla y columna (si existen)
        $this->dropIndexIfExists('taxi_stand_queue', 'uq_driver_one_active');

        if ($this->columnExists('taxi_stand_queue', 'active_key')) {
            DB::statement("ALTER TABLE taxi_stand_queue DROP COLUMN active_key");
        }

        // NO re-creamos los UNIQUE viejos porque estaban mal para histórico.
    }

    /* ---------------- Helpers ---------------- */

    private function columnExists(string $table, string $column): bool
    {
        $db = DB::getDatabaseName();

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$db, $table, $column]
        );

        return (int)($row->c ?? 0) > 0;
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $db = DB::getDatabaseName();

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$db, $table, $indexName]
        );

        if ((int)($row->c ?? 0) > 0) {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$indexName}");
        }
    }

    private function addIndexIfNotExists(string $table, string $indexName, string $sql): void
    {
        $db = DB::getDatabaseName();

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$db, $table, $indexName]
        );

        if ((int)($row->c ?? 0) === 0) {
            DB::statement($sql);
        }
    }

    private function assertIndexExists(string $table, string $indexName): void
    {
        $db = DB::getDatabaseName();

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$db, $table, $indexName]
        );

        if ((int)($row->c ?? 0) === 0) {
            throw new RuntimeException("Index {$indexName} no existe en {$table} (falló migración).");
        }
    }
};
