<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Drop
        DB::statement("DROP PROCEDURE IF EXISTS sp_accept_offer_v7");

        // 2) Create (PDO exec para evitar problemas con statements largos)
        $sql = <<<'SQL'
CREATE PROCEDURE sp_accept_offer_v7(IN p_offer_id BIGINT UNSIGNED)
accept_flow: BEGIN
  DECLARE v_tenant_id   BIGINT UNSIGNED;
  DECLARE v_ride_id     BIGINT UNSIGNED;
  DECLARE v_driver_id   BIGINT UNSIGNED;
  DECLARE v_vehicle_id  BIGINT UNSIGNED;
  DECLARE v_offer_status VARCHAR(32);
  DECLARE v_expires     DATETIME;

  DECLARE v_curr_ride_status VARCHAR(32);
  DECLARE v_curr_ride_driver BIGINT UNSIGNED;

  DECLARE v_prev_status VARCHAR(32);
  DECLARE v_origin_lat DOUBLE;
  DECLARE v_origin_lng DOUBLE;

  DECLARE v_has_active INT DEFAULT 0;

  DECLARE v_last_pos INT DEFAULT 0;
  DECLARE v_next_pos INT DEFAULT 1;

  DECLARE v_economico  VARCHAR(64);
  DECLARE v_plate      VARCHAR(64);
  DECLARE v_dlat DOUBLE;
  DECLARE v_dlng DOUBLE;

  DECLARE v_stand_id BIGINT UNSIGNED;
  DECLARE v_stand_name VARCHAR(128);
  DECLARE v_radius_km DOUBLE;
  DECLARE v_stand_dist_km DOUBLE;

  DECLARE _nf INT DEFAULT 0;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET _nf = 1;

  START TRANSACTION;

  SET _nf = 0;
  SELECT ro.tenant_id, ro.ride_id, ro.driver_id, ro.vehicle_id, ro.status, ro.expires_at
    INTO v_tenant_id, v_ride_id, v_driver_id, v_vehicle_id, v_offer_status, v_expires
  FROM ride_offers ro
  WHERE ro.id = p_offer_id
  FOR UPDATE;

  IF _nf = 1 OR v_ride_id IS NULL OR v_driver_id IS NULL THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Offer inexistente/ inválida';
  END IF;

  IF v_offer_status = 'accepted' THEN
    COMMIT;
    SELECT 'activated' AS mode, v_tenant_id AS tenant_id, v_ride_id AS ride_id, p_offer_id AS offer_id,
           v_driver_id AS driver_id, NULL AS queued_position;
    LEAVE accept_flow;
  END IF;

  IF v_offer_status = 'queued' THEN
    COMMIT;
    SELECT 'queued' AS mode, v_tenant_id AS tenant_id, v_ride_id AS ride_id, p_offer_id AS offer_id,
           v_driver_id AS driver_id, NULL AS queued_position;
    LEAVE accept_flow;
  END IF;

  IF v_offer_status <> 'offered' THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Offer no está en offered';
  END IF;

  IF v_expires IS NOT NULL AND v_expires < NOW() THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Offer expirada';
  END IF;

  SET _nf = 0;
  SELECT r.status, r.driver_id, r.origin_lat, r.origin_lng
    INTO v_curr_ride_status, v_curr_ride_driver, v_origin_lat, v_origin_lng
  FROM rides r
  WHERE r.id = v_ride_id AND r.tenant_id = v_tenant_id
  FOR UPDATE;

  IF _nf = 1 THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride inexistente';
  END IF;

  SET v_prev_status = v_curr_ride_status;

  SELECT EXISTS(
    SELECT 1
    FROM rides r
    WHERE r.tenant_id = v_tenant_id
      AND r.driver_id = v_driver_id
      AND r.id <> v_ride_id
      AND r.status IN ('accepted','en_route','arrived','on_board')
    LIMIT 1
  ) INTO v_has_active;

  IF v_has_active = 1 THEN

    SET _nf = 0;
    SELECT COALESCE(queued_position, 0)
      INTO v_last_pos
    FROM ride_offers
    WHERE tenant_id = v_tenant_id
      AND driver_id = v_driver_id
      AND status    = 'queued'
    ORDER BY queued_position DESC, id DESC
    LIMIT 1
    FOR UPDATE;

    IF _nf = 1 THEN SET v_last_pos = 0; END IF;
    SET v_next_pos = v_last_pos + 1;

    UPDATE rides
       SET driver_id  = COALESCE(driver_id, v_driver_id),
           vehicle_id = COALESCE(v_vehicle_id, vehicle_id),
           status     = 'queued',
           updated_at = NOW()
     WHERE id        = v_ride_id
       AND tenant_id = v_tenant_id
       AND status IN ('requested','offered','scheduled','queued')
       AND (driver_id IS NULL OR driver_id = v_driver_id);

    IF ROW_COUNT() = 0 THEN
      ROLLBACK;
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no encolable (estado/driver mismatch)';
    END IF;

    UPDATE ride_offers
       SET status          = 'queued',
           response        = 'accepted',
           responded_at    = NOW(),
           queued_at       = NOW(),
           queued_position = v_next_pos,
           queued_reason   = 'has_active',
           updated_at      = NOW()
     WHERE id = p_offer_id
       AND status = 'offered';

    UPDATE ride_offers
       SET responded_at = COALESCE(responded_at, NOW()),
           response     = COALESCE(response, 'released'),
           status       = 'released',
           updated_at   = NOW()
     WHERE ride_id   = v_ride_id
       AND tenant_id = v_tenant_id
       AND id       <> p_offer_id
       AND status IN ('offered','pending_passenger','queued');

    INSERT INTO ride_status_history
      (tenant_id, ride_id, prev_status, new_status, meta, created_at)
    VALUES
      (v_tenant_id, v_ride_id, v_prev_status, 'queued',
       JSON_OBJECT(
         'offer_id', p_offer_id,
         'driver_id', v_driver_id,
         'vehicle_id', v_vehicle_id,
         'queued_position', v_next_pos,
         'queued_reason', 'has_active'
       ),
       NOW());

    COMMIT;

    SELECT 'queued' AS mode, v_tenant_id AS tenant_id, v_ride_id AS ride_id, p_offer_id AS offer_id,
           v_driver_id AS driver_id, v_next_pos AS queued_position;

    LEAVE accept_flow;
  END IF;

  UPDATE rides
     SET driver_id   = COALESCE(driver_id, v_driver_id),
         vehicle_id  = COALESCE(v_vehicle_id, vehicle_id),
         status      = CASE
                         WHEN v_curr_ride_status IN ('requested','offered','scheduled','queued')
                           THEN 'accepted'
                         ELSE v_curr_ride_status
                       END,
         accepted_at = COALESCE(accepted_at, NOW()),
         updated_at  = NOW()
   WHERE id        = v_ride_id
     AND tenant_id = v_tenant_id
     AND (driver_id IS NULL OR driver_id = v_driver_id);

  IF ROW_COUNT() = 0 THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no activable (race/estado/driver mismatch)';
  END IF;

  UPDATE ride_offers
     SET status       = 'accepted',
         response     = 'accepted',
         responded_at = NOW(),
         updated_at   = NOW()
   WHERE id = p_offer_id
     AND status IN ('offered','queued');

  UPDATE ride_offers
     SET responded_at = COALESCE(responded_at, NOW()),
         response     = COALESCE(response, 'released'),
         status       = 'released',
         updated_at   = NOW()
   WHERE ride_id   = v_ride_id
     AND tenant_id = v_tenant_id
     AND id       <> p_offer_id
     AND status IN ('offered','pending_passenger');

  SELECT v.economico, v.plate
    INTO v_economico, v_plate
  FROM vehicles v
  WHERE v.id = COALESCE(v_vehicle_id, (SELECT vehicle_id FROM rides WHERE id=v_ride_id LIMIT 1))
  LIMIT 1;

  SELECT d.last_lat, d.last_lng
    INTO v_dlat, v_dlng
  FROM drivers d
  WHERE d.id = v_driver_id
  LIMIT 1;

  SET v_stand_id = NULL;
  SET v_stand_name = NULL;
  SET _nf = 0;

  SELECT q.stand_id, ts.nombre
    INTO v_stand_id, v_stand_name
  FROM taxi_stand_queue q
  JOIN taxi_stands ts
    ON ts.id = q.stand_id
   AND ts.tenant_id = q.tenant_id
  WHERE q.tenant_id = v_tenant_id
    AND q.driver_id = v_driver_id
    AND q.status IN ('en_cola','saltado')
  ORDER BY q.id DESC
  LIMIT 1
  FOR UPDATE;

  IF _nf = 1 THEN
    SET v_stand_id = NULL;
  END IF;

  IF v_stand_id IS NOT NULL THEN
    CALL sp_queue_leave_stand_v1(v_tenant_id, v_stand_id, v_driver_id, 'asignado');
  END IF;

  INSERT INTO ride_status_history
    (tenant_id, ride_id, prev_status, new_status, meta, created_at)
  VALUES
    (v_tenant_id, v_ride_id, v_prev_status, 'accepted',
     JSON_OBJECT(
       'offer_id', p_offer_id,
       'driver_id', v_driver_id,
       'vehicle_id', v_vehicle_id,
       'economico', v_economico,
       'plate', v_plate,
       'driver_last_lat', v_dlat,
       'driver_last_lng', v_dlng,
       'stand_id_on_accept', v_stand_id,
       'stand_name_on_accept', v_stand_name,
       'stand_dist_km', v_stand_dist_km
     ),
     NOW());

  COMMIT;

  SELECT 'activated' AS mode, v_tenant_id AS tenant_id, v_ride_id AS ride_id, p_offer_id AS offer_id,
         v_driver_id AS driver_id, NULL AS queued_position;

END
SQL;

        // En XAMPP a veces statement() falla con SQL largo; exec() es más estable.
        DB::connection()->getPdo()->exec($sql);
    }

    public function down(): void
    {
        // No hacemos rollback del SP automáticamente.
    }
};
