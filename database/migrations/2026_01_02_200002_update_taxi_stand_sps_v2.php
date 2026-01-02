<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        $this->recreateProcedure('sp_queue_leave_stand_v1', $this->sqlLeave());
        $this->recreateProcedure('sp_queue_join_stand_v1',  $this->sqlJoin());
        $this->recreateProcedure('sp_queue_on_accept_v1',   $this->sqlOnAccept());

        // Verificación fuerte: existen en ROUTINES
        $this->assertProcedureExists('sp_queue_leave_stand_v1');
        $this->assertProcedureExists('sp_queue_join_stand_v1');
        $this->assertProcedureExists('sp_queue_on_accept_v1');
    }

    public function down(): void
    {
        // Down conservador (opcional): solo drop
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_queue_leave_stand_v1;");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_queue_join_stand_v1;");
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_queue_on_accept_v1;");
    }

    /* ---------------- Core ---------------- */

    private function recreateProcedure(string $name, string $createSql): void
    {
        // IMPORTANTE: DROP y CREATE separados para evitar “multi statement issues”
        DB::unprepared("DROP PROCEDURE IF EXISTS {$name};");
        DB::unprepared($createSql);
    }

    private function assertProcedureExists(string $name): void
    {
        $db = DB::getDatabaseName();
        $row = DB::selectOne(
            "SELECT COUNT(*) AS c
             FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = ? AND ROUTINE_NAME = ? AND ROUTINE_TYPE = 'PROCEDURE'",
            [$db, $name]
        );

        if ((int)($row->c ?? 0) === 0) {
            throw new RuntimeException("SP {$name} no existe (falló migración).");
        }
    }

    /* ---------------- SQLs ---------------- */

    private function sqlJoin(): string
    {
        return <<<'SQL'
CREATE PROCEDURE sp_queue_join_stand_v1(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_stand_id  BIGINT UNSIGNED,
  IN p_driver_id BIGINT UNSIGNED
)
BEGIN
  DECLARE v_dest_ok BIGINT UNSIGNED DEFAULT NULL;

  DECLARE v_prev_stand BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_prev_pos   INT DEFAULT NULL;

  DECLARE v_maxpos INT DEFAULT 0;

  START TRANSACTION;

  /* 1) Validar/lock stand destino */
  SELECT id
    INTO v_dest_ok
  FROM taxi_stands
  WHERE id = p_stand_id
    AND tenant_id = p_tenant_id
    AND activo = 1
  FOR UPDATE;

  IF v_dest_ok IS NULL THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stand inválido/inactivo';
  END IF;

  /* 2) ¿Driver ya está activo (en_cola o saltado) en algún stand? */
  SELECT stand_id, position
    INTO v_prev_stand, v_prev_pos
  FROM taxi_stand_queue
  WHERE tenant_id = p_tenant_id
    AND driver_id = p_driver_id
    AND status IN ('en_cola','saltado')
  ORDER BY id DESC
  LIMIT 1
  FOR UPDATE;

  /* 2A) Idempotente: ya está en el mismo stand -> no hacer nada */
  IF v_prev_stand IS NOT NULL AND v_prev_stand = p_stand_id THEN
    COMMIT;
  ELSE

    /* 2B) Si estaba activo en otro stand -> salir y reindex del stand anterior */
    IF v_prev_stand IS NOT NULL AND v_prev_stand <> p_stand_id THEN

      UPDATE taxi_stand_queue
         SET status = 'salio'
       WHERE tenant_id = p_tenant_id
         AND stand_id  = v_prev_stand
         AND driver_id = p_driver_id
         AND status IN ('en_cola','saltado');

      IF v_prev_pos IS NOT NULL THEN
        UPDATE taxi_stand_queue
           SET position = position - 1
         WHERE tenant_id = p_tenant_id
           AND stand_id  = v_prev_stand
           AND status IN ('en_cola','saltado')
           AND position > v_prev_pos;
      END IF;
    END IF;

    /* 3) Calcular última posición en stand destino (activos) con lock */
    SELECT COALESCE(MAX(position),0)
      INTO v_maxpos
    FROM taxi_stand_queue
    WHERE tenant_id = p_tenant_id
      AND stand_id  = p_stand_id
      AND status IN ('en_cola','saltado')
    FOR UPDATE;

    /* 4) Insertar al final */
    INSERT INTO taxi_stand_queue
      (tenant_id, stand_id, driver_id, joined_at, position, status)
    VALUES
      (p_tenant_id, p_stand_id, p_driver_id, NOW(), v_maxpos + 1, 'en_cola');

    COMMIT;
  END IF;

END
SQL;
    }

    private function sqlLeave(): string
    {
        return <<<'SQL'
CREATE PROCEDURE sp_queue_leave_stand_v1(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_stand_id  BIGINT UNSIGNED,
  IN p_driver_id BIGINT UNSIGNED,
  IN p_status_to VARCHAR(32)
)
BEGIN
  DECLARE v_pos INT DEFAULT NULL;
  DECLARE v_to  VARCHAR(32);

  SET v_to = CASE
    WHEN p_status_to IN ('salio','asignado') THEN p_status_to
    ELSE 'salio'
  END;

  START TRANSACTION;

  SELECT position
    INTO v_pos
  FROM taxi_stand_queue
  WHERE tenant_id = p_tenant_id
    AND stand_id  = p_stand_id
    AND driver_id = p_driver_id
    AND status IN ('en_cola','saltado')
  ORDER BY id DESC
  LIMIT 1
  FOR UPDATE;

  IF v_pos IS NULL THEN
    COMMIT;
  ELSE

    UPDATE taxi_stand_queue
       SET status = v_to
     WHERE tenant_id = p_tenant_id
       AND stand_id  = p_stand_id
       AND driver_id = p_driver_id
       AND status IN ('en_cola','saltado');

    UPDATE taxi_stand_queue
       SET position = position - 1
     WHERE tenant_id = p_tenant_id
       AND stand_id  = p_stand_id
       AND status IN ('en_cola','saltado')
       AND position > v_pos;

    COMMIT;
  END IF;

END
SQL;
    }

    private function sqlOnAccept(): string
    {
        return <<<'SQL'
CREATE PROCEDURE sp_queue_on_accept_v1(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_driver_id BIGINT UNSIGNED
)
BEGIN
  DECLARE v_stand_id BIGINT UNSIGNED DEFAULT NULL;

  START TRANSACTION;

  SELECT stand_id
    INTO v_stand_id
  FROM taxi_stand_queue
  WHERE tenant_id = p_tenant_id
    AND driver_id = p_driver_id
    AND status IN ('en_cola','saltado')
  ORDER BY id DESC
  LIMIT 1
  FOR UPDATE;

  IF v_stand_id IS NOT NULL THEN
    CALL sp_queue_leave_stand_v1(p_tenant_id, v_stand_id, p_driver_id, 'asignado');
  END IF;

  COMMIT;
END
SQL;
    }
};
