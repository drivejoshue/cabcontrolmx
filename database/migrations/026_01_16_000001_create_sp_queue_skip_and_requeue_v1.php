<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateSpQueueSkipAndRequeueV1 extends Migration
{
    public function up()
    {
        DB::unprepared("
            DROP PROCEDURE IF EXISTS sp_queue_skip_and_requeue_v1;

            CREATE PROCEDURE sp_queue_skip_and_requeue_v1 (
                IN p_tenant_id BIGINT UNSIGNED,
                IN p_stand_id  BIGINT UNSIGNED,
                IN p_driver_id BIGINT UNSIGNED
            )
            proc: BEGIN
                DECLARE v_max_pos INT;

                IF p_driver_id IS NULL OR p_stand_id IS NULL THEN
                    LEAVE proc;
                END IF;

                SELECT position
                  INTO v_max_pos
                FROM taxi_stand_queue
                WHERE tenant_id = p_tenant_id
                  AND stand_id  = p_stand_id
                  AND driver_id = p_driver_id
                  AND status IN ('en_cola','asignado')
                LIMIT 1;

                IF v_max_pos IS NULL THEN
                    LEAVE proc;
                END IF;

                UPDATE taxi_stand_queue
                   SET status = 'saltado'
                 WHERE tenant_id = p_tenant_id
                   AND stand_id  = p_stand_id
                   AND driver_id = p_driver_id
                   AND status IN ('en_cola','asignado');

                SELECT COALESCE(MAX(position),0)
                  INTO v_max_pos
                FROM taxi_stand_queue
                WHERE tenant_id = p_tenant_id
                  AND stand_id  = p_stand_id;

                INSERT INTO taxi_stand_queue (
                    tenant_id,
                    stand_id,
                    driver_id,
                    joined_at,
                    position,
                    status
                )
                VALUES (
                    p_tenant_id,
                    p_stand_id,
                    p_driver_id,
                    NOW(),
                    v_max_pos + 1,
                    'en_cola'
                );
            END;
        ");
    }

    public function down()
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_queue_skip_and_requeue_v1;");
    }
}
