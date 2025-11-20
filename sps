DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_accept_offer_v2`(
  IN p_offer_id BIGINT UNSIGNED
)
BEGIN
  DECLARE v_tenant_id  BIGINT UNSIGNED;
  DECLARE v_ride_id    BIGINT UNSIGNED;
  DECLARE v_driver_id  BIGINT UNSIGNED;
  DECLARE v_vehicle_id BIGINT UNSIGNED;
  DECLARE v_status     VARCHAR(20);
  DECLARE v_expires    DATETIME;
  DECLARE v_updated_rows INT DEFAULT 0;

  /* 1) Cargar oferta */
  SELECT ro.tenant_id, ro.ride_id, ro.driver_id, ro.vehicle_id, ro.status, ro.expires_at
    INTO v_tenant_id, v_ride_id, v_driver_id, v_vehicle_id, v_status, v_expires
  FROM ride_offers ro
  WHERE ro.id = p_offer_id
  LIMIT 1;

  IF v_ride_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Oferta inexistente';
  END IF;

  IF v_status <> 'offered' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La oferta no está en estado offered';
  END IF;

  IF v_expires IS NOT NULL AND v_expires < NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La oferta está expirada';
  END IF;

  /* 2) Asignar driver al ride (si sigue ofertable) */
  UPDATE rides
     SET driver_id   = v_driver_id,
         vehicle_id  = COALESCE(v_vehicle_id, vehicle_id),
         status      = 'accepted',
         accepted_at = COALESCE(accepted_at, NOW())
   WHERE id = v_ride_id
     AND tenant_id = v_tenant_id
     AND driver_id IS NULL
     AND status IN ('requested','offered');

  SET v_updated_rows = ROW_COUNT();
  IF v_updated_rows = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no ofertable o ya tiene driver';
  END IF;

  /* 3) Marcar esta oferta aceptada */
  UPDATE ride_offers
     SET responded_at = NOW(),
         response    = 'accepted',
         status      = 'accepted'
   WHERE id = p_offer_id;

  /* 4) Rechazar el resto */
  UPDATE ride_offers
     SET responded_at = COALESCE(responded_at, NOW()),
         response     = COALESCE(response, 'rejected'),
         status       = CASE WHEN status='offered' THEN 'rejected' ELSE status END
   WHERE ride_id   = v_ride_id
     AND id       <> p_offer_id
     AND tenant_id = v_tenant_id;

  /* 5) Salida */
  SELECT * FROM ride_offers WHERE id = p_offer_id;
  SELECT * FROM rides       WHERE id = v_ride_id;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_accept_offer_v3`(IN p_offer_id BIGINT UNSIGNED)
BEGIN
  DECLARE v_tenant_id  BIGINT UNSIGNED;
  DECLARE v_ride_id    BIGINT UNSIGNED;
  DECLARE v_driver_id  BIGINT UNSIGNED;
  DECLARE v_vehicle_id BIGINT UNSIGNED;
  DECLARE v_status     VARCHAR(20);
  DECLARE v_expires    DATETIME;
  DECLARE v_prev_status VARCHAR(20);

  DECLARE v_economico  VARCHAR(64);
  DECLARE v_plate      VARCHAR(64);
  DECLARE v_dlat DOUBLE; DECLARE v_dlng DOUBLE;

  DECLARE v_lat DOUBLE;  DECLARE v_lng DOUBLE;
  DECLARE v_stand_id BIGINT UNSIGNED;
  DECLARE v_stand_name VARCHAR(128);
  DECLARE v_radius_km DOUBLE;
  DECLARE v_stand_dist_km DOUBLE;

  SELECT ro.tenant_id, ro.ride_id, ro.driver_id, ro.vehicle_id, ro.status, ro.expires_at
    INTO v_tenant_id, v_ride_id, v_driver_id, v_vehicle_id, v_status, v_expires
  FROM ride_offers ro
  WHERE ro.id = p_offer_id
  LIMIT 1;

  IF v_ride_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Oferta inexistente';
  END IF;
  IF v_status <> 'offered' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Oferta no está en offered';
  END IF;
  IF v_expires IS NOT NULL AND v_expires < NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Oferta expirada';
  END IF;

  SELECT r.status, r.origin_lat, r.origin_lng
    INTO v_prev_status, v_lat, v_lng
  FROM rides r
  WHERE r.id=v_ride_id AND r.tenant_id=v_tenant_id
  LIMIT 1;

  UPDATE rides
     SET driver_id  = v_driver_id,
         vehicle_id = COALESCE(v_vehicle_id, vehicle_id),
         status     = 'accepted'
   WHERE id = v_ride_id
     AND tenant_id = v_tenant_id
     AND driver_id IS NULL
     AND status IN ('requested','offered','queued');   -- CAMBIO

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no ofertable o ya asignado';
  END IF;

  SELECT v.economico, v.plate
    INTO v_economico, v_plate
  FROM vehicles v WHERE v.id=COALESCE(v_vehicle_id,(SELECT vehicle_id FROM rides WHERE id=v_ride_id)) LIMIT 1;

  SELECT d.last_lat, d.last_lng
    INTO v_dlat, v_dlng
  FROM drivers d WHERE d.id=v_driver_id LIMIT 1;

  SET v_stand_id = NULL; SET v_stand_name = NULL; SET v_stand_dist_km = NULL;

  IF v_lat IS NOT NULL AND v_lng IS NOT NULL THEN
    SELECT COALESCE(ds.stand_radius_km, ds.auto_dispatch_radius_km, 3.00)
      INTO v_radius_km
    FROM dispatch_settings ds
    WHERE ds.tenant_id = v_tenant_id
    LIMIT 1;

    SELECT ts.id, ts.nombre,
           haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) AS dist_km
      INTO v_stand_id, v_stand_name, v_stand_dist_km
    FROM taxi_stands ts
    WHERE ts.tenant_id=v_tenant_id AND ts.activo=1
      AND haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) <= v_radius_km
    ORDER BY dist_km ASC, ts.id ASC
    LIMIT 1;

    IF v_stand_id IS NOT NULL THEN
      CALL sp_queue_leave_stand_v1(v_tenant_id, v_stand_id, v_driver_id, 'asignado');
    END IF;
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

  UPDATE ride_offers
     SET responded_at = NOW(),
         response    = 'accepted',
         status      = 'accepted'
   WHERE id = p_offer_id;

  UPDATE ride_offers
     SET responded_at = COALESCE(responded_at, NOW()),
         response     = COALESCE(response, 'rejected'),
         status       = CASE WHEN status='offered' THEN 'rejected' ELSE status END
   WHERE ride_id   = v_ride_id
     AND id       <> p_offer_id
     AND tenant_id = v_tenant_id;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_accept_offer_v4`(IN `p_offer_id` BIGINT UNSIGNED)
proc: BEGIN
  DECLARE v_tenant_id  BIGINT UNSIGNED;
  DECLARE v_ride_id    BIGINT UNSIGNED;
  DECLARE v_driver_id  BIGINT UNSIGNED;
  DECLARE v_vehicle_id BIGINT UNSIGNED;
  DECLARE v_status     VARCHAR(20);
  DECLARE v_expires    DATETIME;
  DECLARE v_prev_status VARCHAR(20);

  DECLARE v_economico  VARCHAR(64);
  DECLARE v_plate      VARCHAR(64);
  DECLARE v_dlat DOUBLE; DECLARE v_dlng DOUBLE;
  DECLARE v_lat DOUBLE;  DECLARE v_lng DOUBLE;

  DECLARE v_has_active TINYINT DEFAULT 0;

  DECLARE v_stand_id BIGINT UNSIGNED;
  DECLARE v_stand_name VARCHAR(128);
  DECLARE v_radius_km DOUBLE;
  DECLARE v_stand_dist_km DOUBLE;

  DECLARE _not_found INT DEFAULT 0;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET _not_found = 1;

  START TRANSACTION;

  /* 1) Cargar oferta */
  SET _not_found = 0;
  SELECT ro.tenant_id, ro.ride_id, ro.driver_id, ro.vehicle_id, ro.status, ro.expires_at
    INTO v_tenant_id, v_ride_id, v_driver_id, v_vehicle_id, v_status, v_expires
  FROM ride_offers ro
  WHERE ro.id = p_offer_id
  FOR UPDATE;

  IF _not_found = 1 THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Oferta inexistente';
  END IF;
  IF v_status <> 'offered' THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Oferta no está en offered';
  END IF;
  IF v_expires IS NOT NULL AND v_expires < NOW() THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Oferta expirada';
  END IF;

  /* 2) Estado previo del ride */
  SET _not_found = 0;
  SELECT r.status, r.origin_lat, r.origin_lng
    INTO v_prev_status, v_lat, v_lng
  FROM rides r
  WHERE r.id=v_ride_id AND r.tenant_id=v_tenant_id
  FOR UPDATE;

  IF _not_found = 1 THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride inexistente';
  END IF;

  /* 3) ¿Driver tiene ride activa? */
  SELECT EXISTS(
    SELECT 1 FROM rides
     WHERE tenant_id=v_tenant_id
       AND driver_id=v_driver_id
       AND status IN ('accepted','en_route','arrived','on_board')
     LIMIT 1
  ) INTO v_has_active;

  /* 4A) Con ride activa → encolar */
  IF v_has_active = 1 THEN
    UPDATE rides
       SET status='queued'
     WHERE id = v_ride_id
       AND tenant_id = v_tenant_id
       AND status IN ('requested','offered');

    UPDATE ride_offers
       SET responded_at = NOW(),
           response     = 'accepted',
           status       = 'queued'
     WHERE id = p_offer_id;

    INSERT INTO ride_status_history
      (tenant_id, ride_id, prev_status, new_status, meta, created_at)
    VALUES
      (v_tenant_id, v_ride_id, v_prev_status, 'queued',
       JSON_OBJECT('offer_id', p_offer_id, 'driver_id', v_driver_id, 'vehicle_id', v_vehicle_id),
       NOW());

    COMMIT;
    SELECT 'queued' AS mode, NULL AS ride_id;
    LEAVE proc;
  END IF;

  /* 4B) Sin ride activa → activar de inmediato (acepted) */
  UPDATE rides
     SET driver_id  = v_driver_id,
         vehicle_id = COALESCE(v_vehicle_id, vehicle_id),
         status     = 'accepted'
   WHERE id = v_ride_id
     AND tenant_id = v_tenant_id
     AND driver_id IS NULL
     AND status IN ('requested','offered','queued');

  IF ROW_COUNT() = 0 THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no ofertable o ya asignado';
  END IF;

  /* Datos de vehículo/driver para meta */
  SET _not_found = 0;
  SELECT v.economico, v.plate
    INTO v_economico, v_plate
  FROM vehicles v WHERE v.id=COALESCE(v_vehicle_id,(SELECT vehicle_id FROM rides WHERE id=v_ride_id)) LIMIT 1;

  SET _not_found = 0;
  SELECT d.last_lat, d.last_lng
    INTO v_dlat, v_dlng
  FROM drivers d WHERE d.id=v_driver_id LIMIT 1;

  /* Posible salida de base (stand) */
  SET v_stand_id = NULL; SET v_stand_name = NULL; SET v_stand_dist_km = NULL;
  IF v_lat IS NOT NULL AND v_lng IS NOT NULL THEN
    SET _not_found = 0;
    SELECT COALESCE(ds.stand_radius_km, ds.auto_dispatch_radius_km, 3.00)
      INTO v_radius_km
    FROM dispatch_settings ds
    WHERE ds.tenant_id = v_tenant_id
    LIMIT 1;

    SET _not_found = 0;
    SELECT ts.id, ts.nombre,
           haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) AS dist_km
      INTO v_stand_id, v_stand_name, v_stand_dist_km
    FROM taxi_stands ts
    WHERE ts.tenant_id=v_tenant_id AND ts.activo=1
      AND haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) <= v_radius_km
    ORDER BY dist_km ASC, ts.id ASC
    LIMIT 1
    FOR UPDATE;

    IF v_stand_id IS NOT NULL THEN
      CALL sp_queue_leave_stand_v1(v_tenant_id, v_stand_id, v_driver_id, 'asignado');
    END IF;
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

  UPDATE ride_offers
     SET responded_at = NOW(),
         response    = 'accepted',
         status      = 'accepted'
   WHERE id = p_offer_id;

  UPDATE ride_offers
     SET responded_at = COALESCE(responded_at, NOW()),
         response     = COALESCE(response, 'rejected'),
         status       = CASE WHEN status='offered' THEN 'rejected' ELSE status END
   WHERE ride_id   = v_ride_id
     AND id       <> p_offer_id
     AND tenant_id = v_tenant_id;

  COMMIT;
  SELECT 'activated' AS mode, v_ride_id AS ride_id;
  LEAVE proc;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_accept_offer_v5`(IN p_offer_id BIGINT UNSIGNED)
BEGIN
  DECLARE v_tenant_id   BIGINT UNSIGNED;
  DECLARE v_ride_id     BIGINT UNSIGNED;
  DECLARE v_driver_id   BIGINT UNSIGNED;
  DECLARE v_has_active  INT DEFAULT 0;
  DECLARE v_next_pos    INT DEFAULT 0;
  DECLARE v_curr_status VARCHAR(32);

  /* Bloque etiquetado para poder LEAVE */
  accept_flow: BEGIN

    START TRANSACTION;

    /* --- Bloquear la oferta --- */
    SELECT ro.tenant_id, ro.ride_id, ro.driver_id
      INTO v_tenant_id, v_ride_id, v_driver_id
    FROM ride_offers ro
    WHERE ro.id = p_offer_id
    FOR UPDATE;

    IF v_ride_id IS NULL OR v_driver_id IS NULL THEN
      ROLLBACK;
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Offer inválida';
    END IF;

    /* --- Bloquear el ride --- */
    SELECT status INTO v_curr_status
    FROM rides
    WHERE id = v_ride_id
    FOR UPDATE;

    /* --- ¿driver ya tiene ride activa (distinta)? --- */
    SELECT COUNT(*) INTO v_has_active
    FROM rides r
    WHERE r.driver_id = v_driver_id
      AND r.tenant_id = v_tenant_id
      AND r.id <> v_ride_id
      AND r.status IN ('accepted','en_route','enroute','arrived','on_board','assigned');

    IF v_has_active > 0 THEN
      /* → Encolar */
      SELECT COALESCE(MAX(queued_position), 0) + 1
        INTO v_next_pos
      FROM ride_offers
      WHERE driver_id = v_driver_id AND status = 'queued';

      UPDATE ride_offers
         SET status          = 'queued',
             responded_at    = NOW(),
             updated_at      = NOW(),
             queued_at       = NOW(),
             queued_position = v_next_pos,
             queued_reason   = 'has_active'
       WHERE id = p_offer_id
         AND driver_id = v_driver_id
         AND status IN ('offered','accepted'); -- por si venía toggled

      COMMIT;
      SELECT 'queued' AS mode, v_ride_id AS ride_id;
      LEAVE accept_flow;
    END IF;

    /* --- Activar: fijar driver_id y estado si corresponde --- */
    UPDATE ride_offers
       SET status       = 'accepted',
           response     = 'accepted',
           responded_at = NOW(),
           updated_at   = NOW()
     WHERE id = p_offer_id
       AND driver_id = v_driver_id
       AND status IN ('offered','queued'); -- permitir aceptar desde queue

    /* asegurar driver_id en rides y mover a accepted si estaba "abierto" */
    UPDATE rides
       SET driver_id = COALESCE(driver_id, v_driver_id),
           status    = CASE
                         WHEN v_curr_status IN ('requested','pending','open','offered','queued') THEN 'accepted'
                         ELSE status
                       END,
           accepted_at = COALESCE(accepted_at, NOW()),
           updated_at  = NOW()
     WHERE id = v_ride_id;

    /* liberar/rechazar otras offers del mismo ride (mismo tenant) */
    UPDATE ride_offers
       SET responded_at = COALESCE(responded_at, NOW()),
           response     = COALESCE(response, 'rejected'),
           status       = CASE WHEN status='offered' THEN 'rejected' ELSE status END,
           updated_at   = NOW()
     WHERE ride_id   = v_ride_id
       AND tenant_id = v_tenant_id
       AND id       <> p_offer_id;

    COMMIT;
    SELECT 'activated' AS mode, v_ride_id AS ride_id;

  END accept_flow;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_assign_direct_v1`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED,
  IN p_driver_id BIGINT UNSIGNED
)
BEGIN
  DECLARE v_vehicle_id BIGINT UNSIGNED;
  DECLARE v_driver_status VARCHAR(10);

  IF NOT EXISTS (
    SELECT 1 FROM rides r
     WHERE r.id = p_ride_id
       AND r.tenant_id = p_tenant_id
       AND r.driver_id IS NULL
       AND r.status IN ('requested','offered','queued')  -- CAMBIO
     LIMIT 1
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no ofertable o ya asignado';
  END IF;

  SELECT d.status
    INTO v_driver_status
  FROM drivers d
  WHERE d.id = p_driver_id
    AND d.tenant_id = p_tenant_id
    AND d.last_lat IS NOT NULL
    AND d.last_lng IS NOT NULL
  LIMIT 1;

  IF v_driver_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Driver inexistente o sin coordenadas';
  END IF;
  IF v_driver_status <> 'idle' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Driver no está idle';
  END IF;

  SELECT s.vehicle_id
    INTO v_vehicle_id
  FROM driver_shifts s
  WHERE s.driver_id = p_driver_id
    AND s.tenant_id = p_tenant_id
    AND s.status = 'abierto'
  ORDER BY s.id DESC
  LIMIT 1;

  IF v_vehicle_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Driver sin shift abierto/vehículo';
  END IF;

  UPDATE rides
     SET driver_id  = p_driver_id,
         vehicle_id = COALESCE(v_vehicle_id, vehicle_id),
         status     = 'accepted'
   WHERE id = p_ride_id
     AND tenant_id = p_tenant_id
     AND driver_id IS NULL
     AND status IN ('requested','offered','queued');   -- CAMBIO

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se pudo asignar (carrera de estado)';
  END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_autodispatch_tick_v2`(IN `p_tenant_id` BIGINT UNSIGNED)
BEGIN
  DECLARE v_offer_expires INT DEFAULT 60;
  DECLARE v_wave_n        INT DEFAULT 3;
  DECLARE v_lead_min      INT DEFAULT 5;

  SELECT COALESCE(offer_expires_sec,60),
         COALESCE(wave_size_n,3),
         COALESCE(lead_time_min,5)
    INTO v_offer_expires, v_wave_n, v_lead_min
  FROM dispatch_settings
  WHERE tenant_id = p_tenant_id
  LIMIT 1;

  DROP TEMPORARY TABLE IF EXISTS tmp_autod_rides;
  CREATE TEMPORARY TABLE tmp_autod_rides (
    ride_id BIGINT UNSIGNED PRIMARY KEY
  ) ENGINE=MEMORY;

  /* Programados en ventana */
  INSERT IGNORE INTO tmp_autod_rides(ride_id)
  SELECT r.id
  FROM rides r
  WHERE r.tenant_id = p_tenant_id
    AND r.driver_id IS NULL
    AND r.status IN ('requested','offered','queued')   -- CAMBIO
    AND r.scheduled_for IS NOT NULL
    AND r.scheduled_for BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL v_lead_min MINUTE)
  LIMIT 50;

  /* Inmediatos (<=10 min) */
  INSERT IGNORE INTO tmp_autod_rides(ride_id)
  SELECT r.id
  FROM rides r
  WHERE r.tenant_id = p_tenant_id
    AND r.driver_id IS NULL
    AND r.status IN ('requested','offered','queued')   -- CAMBIO
    AND r.scheduled_for IS NULL
    AND TIMESTAMPDIFF(MINUTE, r.created_at, NOW()) <= 10
  LIMIT 50;

  CALL sp_expire_offers_v2(p_tenant_id);

  BEGIN
    DECLARE v_ride_id BIGINT UNSIGNED;
    DECLARE done INT DEFAULT 0;

    DECLARE cur_rides CURSOR FOR
      SELECT ride_id FROM tmp_autod_rides ORDER BY ride_id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur_rides;
    read_loop: LOOP
      FETCH cur_rides INTO v_ride_id;
      IF done = 1 THEN LEAVE read_loop; END IF;

      IF (SELECT COUNT(*) FROM ride_offers WHERE ride_id=v_ride_id AND status='accepted') = 0
         AND (SELECT driver_id FROM rides WHERE id=v_ride_id) IS NULL THEN
        CALL sp_offer_wave_base_prio_v2(p_tenant_id, v_ride_id, v_wave_n, v_offer_expires);
      END IF;
    END LOOP;
    CLOSE cur_rides;
  END;

  DROP TEMPORARY TABLE IF EXISTS tmp_autod_rides;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cancel_ride_v1`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED
)
BEGIN
  DECLARE v_status VARCHAR(20);

  SELECT r.status INTO v_status
  FROM rides r
  WHERE r.id = p_ride_id AND r.tenant_id = p_tenant_id
  LIMIT 1;

  IF v_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride inexistente o de otro tenant';
  END IF;

  IF v_status NOT IN ('requested','offered','queued','accepted') THEN  -- CAMBIO
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no cancelable en su estado actual';
  END IF;

  UPDATE rides
     SET status = 'canceled'
   WHERE id = p_ride_id AND tenant_id = p_tenant_id;

  UPDATE ride_offers
     SET responded_at = COALESCE(responded_at, NOW()),
         response     = 'canceled',
         status       = 'canceled'
   WHERE tenant_id = p_tenant_id
     AND ride_id   = p_ride_id
     AND status IN ('offered','accepted');
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cancel_ride_v2`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED,
  IN p_canceled_by VARCHAR(32),
  IN p_reason VARCHAR(255)
)
BEGIN
  DECLARE v_status VARCHAR(20); DECLARE v_by_lc VARCHAR(32); DECLARE v_by_norm VARCHAR(32);
  DECLARE v_economico VARCHAR(64); DECLARE v_plate VARCHAR(64);

  SELECT r.status INTO v_status FROM rides r WHERE r.id=p_ride_id AND r.tenant_id=p_tenant_id LIMIT 1;
  IF v_status IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ride inexistente o de otro tenant'; END IF;

  IF v_status NOT IN ('requested','offered','queued','accepted','en_route','arrived','on_board') THEN  -- CAMBIO
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ride no cancelable en su estado actual';
  END IF;

  SET v_by_lc = LOWER(TRIM(COALESCE(p_canceled_by,'')));
  SET v_by_norm = CASE
    WHEN v_by_lc IN ('dispatch','ops','operator') THEN 'dispatch'
    WHEN v_by_lc IN ('driver','conductor') THEN 'driver'
    WHEN v_by_lc IN ('passenger','cliente','rider') THEN 'passenger'
    WHEN v_by_lc IN ('system','sistema') THEN 'system'
    ELSE 'dispatch'
  END;

  SELECT v.economico, v.plate INTO v_economico, v_plate
  FROM vehicles v JOIN rides r ON r.vehicle_id=v.id
  WHERE r.id=p_ride_id AND r.tenant_id=p_tenant_id LIMIT 1;

  UPDATE rides
     SET status='canceled',
         canceled_at=NOW(),
         canceled_by=v_by_norm,
         cancel_reason=NULLIF(TRIM(COALESCE(p_reason,'')),'')
   WHERE id=p_ride_id AND tenant_id=p_tenant_id;

  INSERT INTO ride_status_history(tenant_id, ride_id, prev_status, new_status, meta, created_at)
  VALUES (p_tenant_id, p_ride_id, v_status, 'canceled',
          JSON_OBJECT('canceled_by', v_by_norm, 'reason', NULLIF(TRIM(COALESCE(p_reason,'')),''), 
                      'economico', v_economico, 'plate', v_plate),
          NOW());

  UPDATE ride_offers
     SET responded_at=COALESCE(responded_at,NOW()), response='canceled', status='canceled'
   WHERE tenant_id=p_tenant_id AND ride_id=p_ride_id AND status IN ('offered','accepted');
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_complete_ride_v1`(
  IN p_tenant_id   BIGINT UNSIGNED,
  IN p_ride_id     BIGINT UNSIGNED,
  IN p_distance_m  INT,
  IN p_duration_s  INT
)
BEGIN
  DECLARE v_prev_status VARCHAR(20);
  DECLARE v_economico VARCHAR(64); DECLARE v_plate VARCHAR(64);

  SELECT status INTO v_prev_status
  FROM rides
  WHERE id=p_ride_id AND tenant_id=p_tenant_id AND driver_id IS NOT NULL AND status='accepted'
  LIMIT 1;
  IF v_prev_status IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ride no finalizable (tenant/driver/status)'; END IF;

  IF p_distance_m IS NOT NULL THEN
    UPDATE rides SET distance_m=p_distance_m WHERE id=p_ride_id AND tenant_id=p_tenant_id;
  END IF;
  IF p_duration_s IS NOT NULL THEN
    UPDATE rides SET duration_s=p_duration_s WHERE id=p_ride_id AND tenant_id=p_tenant_id;
  END IF;

  SELECT v.economico, v.plate INTO v_economico, v_plate
  FROM vehicles v JOIN rides r ON r.vehicle_id=v.id
  WHERE r.id=p_ride_id AND r.tenant_id=p_tenant_id LIMIT 1;

  UPDATE rides SET status='finished', finished_at=NOW()
   WHERE id=p_ride_id AND tenant_id=p_tenant_id;

  INSERT INTO ride_status_history(tenant_id, ride_id, prev_status, new_status, meta, created_at)
  VALUES (p_tenant_id, p_ride_id, v_prev_status, 'finished',
          JSON_OBJECT('distance_m', p_distance_m, 'duration_s', p_duration_s,
                      'economico', v_economico, 'plate', v_plate),
          NOW());
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_offer`(IN `p_tenant_id` BIGINT UNSIGNED, IN `p_ride_id` BIGINT UNSIGNED, IN `p_driver_id` BIGINT UNSIGNED, IN `p_expires_sec` INT)
BEGIN
  DECLARE v_origin_lat DOUBLE;
  DECLARE v_origin_lng DOUBLE;
  DECLARE v_ride_status VARCHAR(20);
  DECLARE v_vehicle_id BIGINT UNSIGNED;
  DECLARE v_driver_status VARCHAR(10);
  DECLARE v_distance_m INT;
  DECLARE v_eta_seconds INT;
  DECLARE v_round_no INT;
  DECLARE v_offer_id BIGINT UNSIGNED;

  /* 1) Validar ride del tenant, ofertable y sin driver */
  SELECT r.origin_lat, r.origin_lng, r.status
    INTO v_origin_lat, v_origin_lng, v_ride_status
  FROM rides r
  WHERE r.id = p_ride_id
    AND r.tenant_id = p_tenant_id
    AND r.driver_id IS NULL
    AND r.status IN ('requested','offered')   -- estados reales en tu enum
  LIMIT 1;

  IF v_origin_lat IS NULL OR v_origin_lng IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no ofertable (tenant/estado/driver_id/origen inválidos)';
  END IF;

  /* 2) Validar driver del tenant + estado + última posición */
  SELECT d.status
    INTO v_driver_status
  FROM drivers d
  WHERE d.id = p_driver_id
    AND d.tenant_id = p_tenant_id
    AND d.last_lat IS NOT NULL
    AND d.last_lng IS NOT NULL
  LIMIT 1;

  IF v_driver_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Driver inexistente o sin coordenadas';
  END IF;
  IF v_driver_status <> 'idle' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Driver no está idle';
  END IF;

  /* 3) Shift abierto y vehicle_id desde shift */
  SELECT s.vehicle_id
    INTO v_vehicle_id
  FROM driver_shifts s
  WHERE s.driver_id = p_driver_id
    AND s.tenant_id = p_tenant_id
    AND s.status = 'abierto'                  -- así está en tu tabla
  ORDER BY s.id DESC
  LIMIT 1;

  IF v_vehicle_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Driver sin shift abierto (o sin vehículo activo)';
  END IF;

  /* 4) round_no = max+1 del mismo ride */
  SELECT COALESCE(MAX(ro.round_no),0) + 1
    INTO v_round_no
  FROM ride_offers ro
  WHERE ro.ride_id = p_ride_id;

  /* 5) Distancia/ETA driver -> origen (aprox 25 km/h => ~6.94 m/s) */
  SET v_distance_m = ROUND(
    haversine_km(
      v_origin_lat, v_origin_lng,
      (SELECT d.last_lat FROM drivers d WHERE d.id=p_driver_id LIMIT 1),
      (SELECT d.last_lng FROM drivers d WHERE d.id=p_driver_id LIMIT 1)
    ) * 1000
  );
  SET v_eta_seconds = CASE WHEN v_distance_m IS NULL THEN NULL ELSE ROUND(v_distance_m / 6.94) END;

  /* 6) Insertar oferta */
  INSERT INTO ride_offers
    (tenant_id, ride_id, driver_id, vehicle_id,
     sent_at, responded_at, status, response,
     eta_seconds, expires_at, distance_m, round_no, created_at)
  VALUES
    (p_tenant_id, p_ride_id, p_driver_id, v_vehicle_id,
     NOW(), NULL, 'offered', NULL,
     v_eta_seconds, DATE_ADD(
  NOW(),
  INTERVAL COALESCE(
    (SELECT offer_expires_sec
       FROM dispatch_settings
      WHERE tenant_id = p_tenant_id
      LIMIT 1),
    300
  ) SECOND
)
,
     v_distance_m, v_round_no, NOW());

  SET v_offer_id = LAST_INSERT_ID();

  /* 7) Si el ride estaba 'requested', pásalo a 'offered' (optimista) */
  IF v_ride_status = 'requested' THEN
    UPDATE rides
      SET status = 'offered'
    WHERE id = p_ride_id
      AND status = 'requested'
      AND tenant_id = p_tenant_id;
  END IF;

  /* 8) Devolver oferta creada */
  SELECT * FROM ride_offers WHERE id = v_offer_id;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_offer_v2`(IN `p_tenant_id` BIGINT UNSIGNED, IN `p_ride_id` BIGINT UNSIGNED, IN `p_driver_id` BIGINT UNSIGNED, IN `p_expires_sec` INT)
BEGIN
  DECLARE v_origin_lat DOUBLE;
  DECLARE v_origin_lng DOUBLE;
  DECLARE v_ride_status VARCHAR(20);
  DECLARE v_vehicle_id BIGINT UNSIGNED;
  DECLARE v_driver_status VARCHAR(10);
  DECLARE v_distance_m INT;
  DECLARE v_eta_seconds INT;
  DECLARE v_round_no INT;
  DECLARE v_existing BIGINT UNSIGNED;

  /* 0) Renovación si ya hay offered viva */
  SELECT id INTO v_existing
  FROM ride_offers
  WHERE tenant_id = p_tenant_id
    AND ride_id   = p_ride_id
    AND driver_id = p_driver_id
    AND status    = 'offered'
  ORDER BY id DESC
  LIMIT 1;

  IF v_existing IS NOT NULL THEN
    UPDATE ride_offers
       SET sent_at    = NOW(),
           expires_at = DATE_ADD(
  NOW(),
  INTERVAL COALESCE(
    (SELECT offer_expires_sec
       FROM dispatch_settings
      WHERE tenant_id = p_tenant_id
      LIMIT 1),
    300
  ) SECOND
)

     WHERE id = v_existing;

    SET v_round_no = LAST_INSERT_ID(v_existing);
  ELSE
    /* 1) Ride ofertable y sin driver */
    SELECT r.origin_lat, r.origin_lng, r.status
      INTO v_origin_lat, v_origin_lng, v_ride_status
    FROM rides r
    WHERE r.id = p_ride_id
      AND r.tenant_id = p_tenant_id
      AND r.driver_id IS NULL
      AND r.status IN ('requested','offered','queued')  -- CAMBIO
    LIMIT 1;

    IF v_origin_lat IS NULL OR v_origin_lng IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no ofertable (tenant/estado/driver_id/origen)';
    END IF;

    /* 2) Driver válido e idle con coords */
    SELECT d.status
      INTO v_driver_status
    FROM drivers d
    WHERE d.id = p_driver_id
      AND d.tenant_id = p_tenant_id
      AND d.last_lat IS NOT NULL
      AND d.last_lng IS NOT NULL
    LIMIT 1;

    IF v_driver_status IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Driver inexistente o sin coords';
    END IF;
    IF v_driver_status <> 'idle' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Driver no está idle';
    END IF;

    /* 3) Shift abierto y vehículo */
    SELECT s.vehicle_id INTO v_vehicle_id
    FROM driver_shifts s
    WHERE s.driver_id = p_driver_id
      AND s.tenant_id = p_tenant_id
      AND s.status = 'abierto'
    ORDER BY s.id DESC
    LIMIT 1;

    IF v_vehicle_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Driver sin shift abierto/vehículo';
    END IF;

    /* 4) Round, dist/ETA */
    SELECT COALESCE(MAX(ro.round_no),0) + 1
      INTO v_round_no
    FROM ride_offers ro
    WHERE ro.ride_id = p_ride_id;

    SET v_distance_m = ROUND(
      haversine_km(
        v_origin_lat, v_origin_lng,
        (SELECT d.last_lat FROM drivers d WHERE d.id=p_driver_id LIMIT 1),
        (SELECT d.last_lng FROM drivers d WHERE d.id=p_driver_id LIMIT 1)
      ) * 1000
    );
    SET v_eta_seconds = CASE WHEN v_distance_m IS NULL THEN NULL ELSE ROUND(v_distance_m / 6.94) END;

    /* 5) Insertar oferta */
    INSERT INTO ride_offers
      (tenant_id, ride_id, driver_id, vehicle_id,
       sent_at, responded_at, status, response,
       eta_seconds, expires_at, distance_m, round_no, created_at)
    VALUES
      (p_tenant_id, p_ride_id, p_driver_id, v_vehicle_id,
       NOW(), NULL, 'offered', NULL,
       v_eta_seconds, DATE_ADD(
  NOW(),
  INTERVAL COALESCE(
    p_expires_sec,
    (SELECT offer_expires_sec FROM dispatch_settings WHERE tenant_id = p_tenant_id LIMIT 1),
    30
  ) SECOND
),
       v_distance_m, v_round_no, NOW());
  END IF;

  /* 6) requested -> offered (si aplica) */
  IF v_ride_status = 'requested' THEN
    UPDATE rides
       SET status = 'offered'
     WHERE id = p_ride_id
       AND status = 'requested'
       AND tenant_id = p_tenant_id;
  END IF;

END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_expire_offers_v2`(
  IN p_tenant_id BIGINT UNSIGNED
)
BEGIN
  UPDATE ride_offers
     SET status='expired',
         response=COALESCE(response, 'expired'),
         responded_at=COALESCE(responded_at, NOW())
   WHERE tenant_id=p_tenant_id
     AND status='offered'
     AND expires_at IS NOT NULL
     AND expires_at < NOW();
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_nearby_drivers`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_origin_lat DOUBLE,
  IN p_origin_lng DOUBLE,
  IN p_radius_km DOUBLE
)
BEGIN
  /*
    - drivers.status: 'offline'|'idle'|'busy'
    - driver_shifts.status: 'abierto'|'cerrado' (tu esquema)
    - El vehículo activo viene de driver_shifts.vehicle_id, no de drivers
  */
  SELECT
    d.id          AS driver_id,
    s.vehicle_id  AS vehicle_id,
    d.status,
    d.last_lat,
    d.last_lng,
    ROUND(haversine_km(p_origin_lat, p_origin_lng, d.last_lat, d.last_lng), 3) AS distance_km
  FROM drivers d
  INNER JOIN driver_shifts s
          ON s.driver_id = d.id
         AND s.tenant_id = p_tenant_id
         AND s.status = 'abierto'         -- según tu dump
  WHERE d.tenant_id = p_tenant_id
    AND d.status = 'idle'
    AND d.last_lat IS NOT NULL
    AND d.last_lng IS NOT NULL
    AND haversine_km(p_origin_lat, p_origin_lng, d.last_lat, d.last_lng) <= p_radius_km
  ORDER BY distance_km ASC
  LIMIT 100;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_offer_wave_base_prio_v1`(
  IN p_tenant_id        BIGINT UNSIGNED,
  IN p_ride_id          BIGINT UNSIGNED,
  IN p_stand_radius_km  DOUBLE,
  IN p_radius_km        DOUBLE,
  IN p_limit_n          INT,
  IN p_expires_sec      INT
)
BEGIN
  DECLARE v_lat DOUBLE; DECLARE v_lng DOUBLE;
  DECLARE v_stand_id BIGINT UNSIGNED;
  DECLARE v_count INT DEFAULT 0;
  DECLARE v_driver_id BIGINT UNSIGNED;

  SELECT origin_lat, origin_lng INTO v_lat, v_lng
  FROM rides
  WHERE id=p_ride_id AND tenant_id=p_tenant_id AND driver_id IS NULL
    AND status IN ('requested','offered')
  LIMIT 1;

  IF v_lat IS NULL OR v_lng IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ride no ofertable (tenant/estado/origen)';
  END IF;

  SELECT ts.id INTO v_stand_id
  FROM taxi_stands ts
  WHERE ts.tenant_id=p_tenant_id AND ts.activo=1
    AND haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) <= p_stand_radius_km
  ORDER BY haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) ASC, ts.id ASC
  LIMIT 1;

  DROP TEMPORARY TABLE IF EXISTS tmp_wave_candidates;
  CREATE TEMPORARY TABLE tmp_wave_candidates (
    driver_id    BIGINT UNSIGNED PRIMARY KEY,
    priority_grp TINYINT,
    order_key    DOUBLE,
    distance_km  DOUBLE
  ) ENGINE=MEMORY;

  IF v_stand_id IS NOT NULL THEN
    INSERT IGNORE INTO tmp_wave_candidates (driver_id, priority_grp, order_key, distance_km)
    SELECT q.driver_id, 1, q.position,
           haversine_km(d.last_lat, d.last_lng, v_lat, v_lng)
    FROM taxi_stand_queue q
    JOIN drivers d ON d.id=q.driver_id AND d.tenant_id=p_tenant_id AND d.status='idle'
    JOIN driver_shifts s ON s.driver_id=q.driver_id AND s.tenant_id=p_tenant_id
                        AND s.status='abierto' AND s.vehicle_id IS NOT NULL
    WHERE q.tenant_id=p_tenant_id AND q.stand_id=v_stand_id AND q.status='en_cola'
      AND NOT EXISTS (
        SELECT 1 FROM ride_offers ro
        WHERE ro.tenant_id=p_tenant_id AND ro.ride_id=p_ride_id
          AND ro.driver_id=q.driver_id
          AND ro.status IN ('offered','accepted')
      )
    ORDER BY q.position ASC
    LIMIT p_limit_n;
  END IF;

  INSERT IGNORE INTO tmp_wave_candidates (driver_id, priority_grp, order_key, distance_km)
  SELECT d.id, 2,
         haversine_km(d.last_lat, d.last_lng, v_lat, v_lng),
         haversine_km(d.last_lat, d.last_lng, v_lat, v_lng)
  FROM drivers d
  JOIN driver_shifts s ON s.driver_id=d.id AND s.tenant_id=p_tenant_id
                      AND s.status='abierto' AND s.vehicle_id IS NOT NULL
  WHERE d.tenant_id=p_tenant_id AND d.status='idle'
    AND haversine_km(d.last_lat, d.last_lng, v_lat, v_lng) <= p_radius_km
    AND NOT EXISTS (SELECT 1 FROM tmp_wave_candidates t WHERE t.driver_id=d.id)
    AND NOT EXISTS (
      SELECT 1 FROM ride_offers ro
      WHERE ro.tenant_id=p_tenant_id AND ro.ride_id=p_ride_id
        AND ro.driver_id=d.id
        AND ro.status IN ('offered','accepted')
    )
  ORDER BY order_key ASC
  LIMIT p_limit_n;

  SET v_count = 0;
  loop_emit: LOOP
    SELECT t.driver_id INTO v_driver_id
    FROM tmp_wave_candidates t
    ORDER BY t.priority_grp ASC, t.order_key ASC
    LIMIT 1 OFFSET v_count;

    IF v_driver_id IS NULL THEN LEAVE loop_emit; END IF;

    CALL sp_create_offer_v2(p_tenant_id, p_ride_id, v_driver_id, p_expires_sec);

    SET v_count = v_count + 1;
    IF v_count >= p_limit_n THEN LEAVE loop_emit; END IF;
  END LOOP;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_offer_wave_base_prio_v2`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED,
  IN p_limit_n   INT,
  IN p_expires_sec INT
)
BEGIN
  DECLARE v_lat DOUBLE; DECLARE v_lng DOUBLE;
  DECLARE v_stand_id BIGINT UNSIGNED;
  DECLARE v_count INT DEFAULT 0;
  DECLARE v_driver_id BIGINT UNSIGNED;
  DECLARE v_stand_radius_km DOUBLE;
  DECLARE v_radius_km DOUBLE;

  SELECT COALESCE(ds.stand_radius_km, 3.00),
         COALESCE(ds.auto_dispatch_radius_km, 5.00)
  INTO v_stand_radius_km, v_radius_km
  FROM dispatch_settings ds
  WHERE ds.tenant_id=p_tenant_id;

  /* ride ofertable */
  SELECT origin_lat, origin_lng INTO v_lat, v_lng
  FROM rides
  WHERE id=p_ride_id AND tenant_id=p_tenant_id AND driver_id IS NULL
    AND status IN ('requested','offered','queued')   -- CAMBIO
  LIMIT 1;

  IF v_lat IS NULL OR v_lng IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ride no ofertable (tenant/estado/origen)';
  END IF;

  SELECT ts.id INTO v_stand_id
  FROM taxi_stands ts
  WHERE ts.tenant_id=p_tenant_id AND ts.activo=1
    AND haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) <= v_stand_radius_km
  ORDER BY haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) ASC, ts.id ASC
  LIMIT 1;

  DROP TEMPORARY TABLE IF EXISTS tmp_wave_candidates;
  CREATE TEMPORARY TABLE tmp_wave_candidates (
    driver_id     BIGINT UNSIGNED PRIMARY KEY,
    priority_grp  TINYINT,
    queue_pos     INT NULL,
    distance_km   DOUBLE NOT NULL
  ) ENGINE=MEMORY;

  IF v_stand_id IS NOT NULL THEN
    INSERT IGNORE INTO tmp_wave_candidates (driver_id, priority_grp, queue_pos, distance_km)
    SELECT q.driver_id, 1, q.position,
           haversine_km(d.last_lat, d.last_lng, v_lat, v_lng)
    FROM taxi_stand_queue q
    JOIN drivers d ON d.id=q.driver_id AND d.tenant_id=p_tenant_id AND d.status='idle'
    JOIN driver_shifts s ON s.driver_id=q.driver_id AND s.tenant_id=p_tenant_id
                        AND s.status='abierto' AND s.vehicle_id IS NOT NULL
    WHERE q.tenant_id=p_tenant_id
      AND q.stand_id=v_stand_id
      AND q.status='en_cola'
      AND NOT EXISTS (
        SELECT 1 FROM ride_offers ro
        WHERE ro.tenant_id=p_tenant_id AND ro.ride_id=p_ride_id
          AND ro.driver_id=q.driver_id
          AND ro.status IN ('offered','accepted')
      )
    ORDER BY q.position ASC
    LIMIT p_limit_n;
  END IF;

  INSERT IGNORE INTO tmp_wave_candidates (driver_id, priority_grp, queue_pos, distance_km)
  SELECT d.id, 2, NULL,
         haversine_km(d.last_lat, d.last_lng, v_lat, v_lng)
  FROM drivers d
  JOIN driver_shifts s ON s.driver_id=d.id AND s.tenant_id=p_tenant_id
                      AND s.status='abierto' AND s.vehicle_id IS NOT NULL
  WHERE d.tenant_id=p_tenant_id AND d.status='idle'
    AND haversine_km(d.last_lat, d.last_lng, v_lat, v_lng) <= v_radius_km
    AND NOT EXISTS (SELECT 1 FROM tmp_wave_candidates t WHERE t.driver_id=d.id)
    AND NOT EXISTS (
      SELECT 1 FROM ride_offers ro
      WHERE ro.tenant_id=p_tenant_id AND ro.ride_id=p_ride_id
        AND ro.driver_id=d.id
        AND ro.status IN ('offered','accepted')
    )
  ORDER BY distance_km ASC
  LIMIT p_limit_n;

  SET v_count = 0;
  wave_loop: LOOP
    SELECT t.driver_id
      INTO v_driver_id
    FROM tmp_wave_candidates t
    ORDER BY
      t.priority_grp ASC,
      CASE WHEN t.queue_pos IS NULL THEN 999999 ELSE t.queue_pos END ASC,
      t.distance_km ASC
    LIMIT 1 OFFSET v_count;

    IF v_driver_id IS NULL THEN
      LEAVE wave_loop;
    END IF;

    CALL sp_create_offer_v2(p_tenant_id, p_ride_id, v_driver_id, p_expires_sec);

    SET v_count = v_count + 1;
    IF v_count >= p_limit_n THEN
      LEAVE wave_loop;
    END IF;
  END LOOP;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_offer_wave_v1`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED,
  IN p_radius_km DOUBLE,
  IN p_limit_n   INT,
  IN p_expires_sec INT
)
BEGIN
  DECLARE v_origin_lat DOUBLE;
  DECLARE v_origin_lng DOUBLE;
  DECLARE v_driver_id  BIGINT UNSIGNED;
  DECLARE done INT DEFAULT 0;

  DECLARE cur_drivers CURSOR FOR
    SELECT d.id
    FROM drivers d
    JOIN driver_shifts s
      ON s.driver_id = d.id
     AND s.tenant_id = p_tenant_id
     AND s.status = 'abierto'
    WHERE d.tenant_id = p_tenant_id
      AND d.status = 'idle'
      AND d.last_lat IS NOT NULL
      AND d.last_lng IS NOT NULL
      AND haversine_km(v_origin_lat, v_origin_lng, d.last_lat, d.last_lng) <= p_radius_km
    ORDER BY haversine_km(v_origin_lat, v_origin_lng, d.last_lat, d.last_lng) ASC
    LIMIT p_limit_n;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  /* ride ofertable */
  SELECT r.origin_lat, r.origin_lng
    INTO v_origin_lat, v_origin_lng
  FROM rides r
  WHERE r.id = p_ride_id
    AND r.tenant_id = p_tenant_id
    AND r.driver_id IS NULL
    AND r.status IN ('requested','offered','queued')   -- CAMBIO
  LIMIT 1;

  IF v_origin_lat IS NULL OR v_origin_lng IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no ofertable (tenant/estado/driver_id/origen)';
  END IF;

  OPEN cur_drivers;
  read_loop: LOOP
    FETCH cur_drivers INTO v_driver_id;
    IF done = 1 THEN LEAVE read_loop; END IF;

    IF NOT EXISTS (
      SELECT 1
      FROM ride_offers ro
      WHERE ro.tenant_id = p_tenant_id
        AND ro.ride_id   = p_ride_id
        AND ro.driver_id = v_driver_id
        AND ro.status IN ('offered','accepted')
      LIMIT 1
    ) THEN
      CALL sp_create_offer_v2(p_tenant_id, p_ride_id, v_driver_id, p_expires_sec);
    END IF;
  END LOOP;
  CLOSE cur_drivers;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_offer_wave_v2`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED,
  IN p_driver_ids_csv TEXT,
  IN p_expires_sec INT
)
BEGIN
  DECLARE v_origin_lat DOUBLE;
  DECLARE v_origin_lng DOUBLE;
  DECLARE v_token      VARCHAR(50);
  DECLARE v_csv        TEXT;
  DECLARE v_pos        INT;
  DECLARE v_driver_id  BIGINT UNSIGNED;

  /* ride ofertable */
  SELECT r.origin_lat, r.origin_lng
    INTO v_origin_lat, v_origin_lng
  FROM rides r
  WHERE r.id = p_ride_id
    AND r.tenant_id = p_tenant_id
    AND r.driver_id IS NULL
    AND r.status IN ('requested','offered','queued')   -- CAMBIO
  LIMIT 1;

  IF v_origin_lat IS NULL OR v_origin_lng IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride no ofertable (tenant/estado/driver_id/origen)';
  END IF;

  SET v_csv = TRIM(p_driver_ids_csv);

  WHILE v_csv IS NOT NULL AND v_csv <> '' DO
    SET v_pos = INSTR(v_csv, ',');
    IF v_pos = 0 THEN
      SET v_token = TRIM(v_csv);
      SET v_csv   = '';
    ELSE
      SET v_token = TRIM(SUBSTRING(v_csv, 1, v_pos - 1));
      SET v_csv   = TRIM(SUBSTRING(v_csv, v_pos + 1));
    END IF;

    IF v_token <> '' THEN
      SET v_driver_id = CAST(v_token AS UNSIGNED);

      IF NOT EXISTS (
        SELECT 1 FROM ride_offers ro
        WHERE ro.tenant_id = p_tenant_id
          AND ro.ride_id   = p_ride_id
          AND ro.driver_id = v_driver_id
          AND ro.status IN ('offered','accepted')
        LIMIT 1
      ) THEN
        IF EXISTS (
          SELECT 1
          FROM drivers d
          JOIN driver_shifts s
            ON s.driver_id = d.id
           AND s.tenant_id = p_tenant_id
           AND s.status = 'abierto'
          WHERE d.id = v_driver_id
            AND d.tenant_id = p_tenant_id
            AND d.status = 'idle'
            AND d.last_lat IS NOT NULL
            AND d.last_lng IS NOT NULL
          LIMIT 1
        ) THEN
          CALL sp_create_offer_v2(p_tenant_id, p_ride_id, v_driver_id, p_expires_sec);
        END IF;
      END IF;
    END IF;
  END WHILE;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_queue_join_stand_v1`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_stand_id  BIGINT UNSIGNED,
  IN p_driver_id BIGINT UNSIGNED
)
BEGIN
  DECLARE v_maxpos INT;

  /* normaliza si ya estaba en cola: lo deja en_cola al final */
  UPDATE taxi_stand_queue
     SET status='salio'
   WHERE tenant_id=p_tenant_id
     AND stand_id=p_stand_id
     AND driver_id=p_driver_id
     AND status='en_cola';

  SELECT COALESCE(MAX(position),0) INTO v_maxpos
  FROM taxi_stand_queue
  WHERE tenant_id=p_tenant_id AND stand_id=p_stand_id AND status='en_cola';

  INSERT INTO taxi_stand_queue (tenant_id, stand_id, driver_id, joined_at, position, status)
  VALUES (p_tenant_id, p_stand_id, p_driver_id, NOW(), v_maxpos+1, 'en_cola');
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_queue_leave_stand_v1`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_stand_id  BIGINT UNSIGNED,
  IN p_driver_id BIGINT UNSIGNED,
  IN p_status_to VARCHAR(16)  -- 'salio' | 'asignado'
)
BEGIN
  DECLARE v_pos INT;

  SELECT position INTO v_pos
  FROM taxi_stand_queue
  WHERE tenant_id=p_tenant_id AND stand_id=p_stand_id
    AND driver_id=p_driver_id AND status='en_cola'
  ORDER BY id DESC LIMIT 1;

  IF v_pos IS NOT NULL THEN
    UPDATE taxi_stand_queue
       SET status = CASE WHEN p_status_to IN ('salio','asignado') THEN p_status_to ELSE 'salio' END
     WHERE tenant_id=p_tenant_id AND stand_id=p_stand_id
       AND driver_id=p_driver_id AND status='en_cola';

    UPDATE taxi_stand_queue
       SET position = position - 1
     WHERE tenant_id=p_tenant_id AND stand_id=p_stand_id
       AND status='en_cola'
       AND position > v_pos;
  END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_queue_on_accept_v1`(
  IN p_tenant_id        BIGINT UNSIGNED,
  IN p_ride_id          BIGINT UNSIGNED,
  IN p_driver_id        BIGINT UNSIGNED,
  IN p_stand_radius_km  DOUBLE  -- ej: 3.0
)
BEGIN
  DECLARE v_lat DOUBLE; DECLARE v_lng DOUBLE; DECLARE v_stand_id BIGINT UNSIGNED;

  SELECT origin_lat, origin_lng INTO v_lat, v_lng
  FROM rides WHERE id=p_ride_id AND tenant_id=p_tenant_id LIMIT 1;

  IF v_lat IS NULL OR v_lng IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ride inválido';
  END IF;

  SELECT ts.id INTO v_stand_id
  FROM taxi_stands ts
  WHERE ts.tenant_id=p_tenant_id AND ts.activo=1
    AND haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) <= p_stand_radius_km
  ORDER BY haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) ASC, ts.id ASC
  LIMIT 1;

  IF v_stand_id IS NOT NULL THEN
    CALL sp_queue_leave_stand_v1(p_tenant_id, v_stand_id, p_driver_id, 'asignado');
  END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_queue_rebalance_v1`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_stand_id  BIGINT UNSIGNED
)
BEGIN
  DROP TEMPORARY TABLE IF EXISTS tmp_reb;
  CREATE TEMPORARY TABLE tmp_reb (driver_id BIGINT UNSIGNED PRIMARY KEY, new_pos INT) ENGINE=MEMORY;

  INSERT INTO tmp_reb (driver_id, new_pos)
  SELECT driver_id, ROW_NUMBER() OVER (ORDER BY joined_at ASC)
  FROM taxi_stand_queue
  WHERE tenant_id=p_tenant_id AND stand_id=p_stand_id AND status='en_cola';

  UPDATE taxi_stand_queue q
  JOIN tmp_reb r ON r.driver_id=q.driver_id
  SET q.position=r.new_pos
  WHERE q.tenant_id=p_tenant_id AND q.stand_id=p_stand_id AND q.status='en_cola';
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_quote_ride_v1`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED,
  IN p_when_ts   DATETIME       -- timestamp de referencia; NULL=NOW() (para detectar noche)
)
BEGIN
  DECLARE v_dist_m INT;
  DECLARE v_dur_s  INT;
  DECLARE v_km     DOUBLE;
  DECLARE v_min    DOUBLE;

  DECLARE v_base DECIMAL(10,2);
  DECLARE v_per_km DECIMAL(10,4);
  DECLARE v_per_min DECIMAL(10,4);
  DECLARE v_night_multiplier DECIMAL(8,4);
  DECLARE v_round_to INT;
  DECLARE v_min_total DECIMAL(10,2);

  DECLARE v_subtotal DECIMAL(12,4);
  DECLARE v_mult DECIMAL(8,4);
  DECLARE v_quoted DECIMAL(10,2);
  DECLARE v_when DATETIME;

  /* 0) Normalizar fecha */
  SET v_when = COALESCE(p_when_ts, NOW());

  /* 1) Ride válido + métricas presentes */
  SELECT r.distance_m, r.duration_s
    INTO v_dist_m, v_dur_s
  FROM rides r
  WHERE r.id = p_ride_id
    AND r.tenant_id = p_tenant_id
  LIMIT 1;

  IF v_dist_m IS NULL OR v_dur_s IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride sin distancia/tiempo: fija ruta antes de cotizar';
  END IF;

  /* 2) Leer política más reciente del tenant */
  SELECT base_fee, per_km, per_min, night_multiplier, round_to, min_total
    INTO v_base, v_per_km, v_per_min, v_night_multiplier, v_round_to, v_min_total
  FROM tenant_fare_policies
  WHERE tenant_id = p_tenant_id
  ORDER BY id DESC
  LIMIT 1;

  IF v_base IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay política de tarifa activa para el tenant';
  END IF;

  /* 3) Calcular */
  SET v_km  = v_dist_m / 1000.0;
  SET v_min = v_dur_s / 60.0;

  SET v_subtotal = v_base + (v_km * v_per_km) + (v_min * v_per_min);
  SET v_subtotal = GREATEST(v_subtotal, v_min_total);

  /* Noche (22:00–05:59); ajusta si usas otra ventana */
  SET v_mult = CASE WHEN (HOUR(v_when) >= 22 OR HOUR(v_when) < 6) THEN COALESCE(v_night_multiplier, 1.0) ELSE 1.0 END;

  /* Redondeo a decimales (default 2 si viniera NULL) */
  SET v_quoted = ROUND(v_subtotal * v_mult, COALESCE(v_round_to, 2));

  /* 4) Persistir en rides: quoted_amount + snapshot JSON */
  UPDATE rides
     SET quoted_amount = v_quoted,
         fare_snapshot = JSON_OBJECT(
           'calc_at',        DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'),
           'policy',         JSON_OBJECT(
                               'base_fee', v_base,
                               'per_km', v_per_km,
                               'per_min', v_per_min,
                               'night_multiplier', v_night_multiplier,
                               'round_to', v_round_to,
                               'min_total', v_min_total
                             ),
           'input',          JSON_OBJECT(
                               'distance_m', v_dist_m,
                               'duration_s', v_dur_s,
                               'when', DATE_FORMAT(v_when, '%Y-%m-%d %H:%i:%s')
                             ),
           'calc',           JSON_OBJECT(
                               'km', v_km,
                               'min', v_min,
                               'subtotal', v_subtotal,
                               'multiplier', v_mult
                             ),
           'quoted_amount',  v_quoted
         )
   WHERE id = p_ride_id
     AND tenant_id = p_tenant_id;

  /* SIN SELECT final */
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_quote_ride_v2`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED,
  IN p_when_ts   DATETIME   -- NULL = NOW()
)
BEGIN
  DECLARE v_dist_m INT; DECLARE v_dur_s INT;
  DECLARE v_km DOUBLE; DECLARE v_min DOUBLE;
  DECLARE v_base DECIMAL(10,2);
  DECLARE v_per_km DECIMAL(10,4); DECLARE v_per_min DECIMAL(10,4);
  DECLARE v_night_mult DECIMAL(8,4);
  DECLARE v_round_mode ENUM('decimals','step');
  DECLARE v_round_decimals TINYINT; DECLARE v_round_step DECIMAL(10,2);
  DECLARE v_min_total DECIMAL(10,2);
  DECLARE v_subtotal DECIMAL(12,4); DECLARE v_mult DECIMAL(8,4);
  DECLARE v_quoted DECIMAL(12,2);
  DECLARE v_when DATETIME;
  DECLARE v_nstart TINYINT; DECLARE v_nend TINYINT;
  DECLARE v_partial BOOL DEFAULT FALSE;

  SET v_when = COALESCE(p_when_ts, NOW());

  /* 1) Ride */
  SELECT distance_m, duration_s INTO v_dist_m, v_dur_s
  FROM rides WHERE id=p_ride_id AND tenant_id=p_tenant_id LIMIT 1;
  IF v_dist_m IS NULL AND v_dur_s IS NULL THEN
    SET v_partial = TRUE; -- cotizaremos base/min_total
  END IF;

  /* 2) Política más reciente */
  SELECT base_fee, per_km, per_min, night_multiplier,
         COALESCE(night_start_hour,22), COALESCE(night_end_hour,6),
         COALESCE(round_mode,'step'), COALESCE(round_decimals,0), COALESCE(round_step,1.00),
         min_total
    INTO v_base, v_per_km, v_per_min, v_night_mult,
         v_nstart, v_nend,
         v_round_mode, v_round_decimals, v_round_step,
         v_min_total
  FROM tenant_fare_policies
  WHERE tenant_id = p_tenant_id
  ORDER BY id DESC LIMIT 1;

  IF v_base IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay política de tarifa activa para el tenant';
  END IF;

  /* 3) Calcular */
  IF v_partial = FALSE THEN
    SET v_km  = v_dist_m / 1000.0;
    SET v_min = v_dur_s / 60.0;
    SET v_subtotal = v_base + (v_km * v_per_km) + (v_min * v_per_min);
  ELSE
    SET v_km  = NULL; SET v_min = NULL;
    SET v_subtotal = v_base;  -- sin destino: cobramos base/min
  END IF;

  SET v_subtotal = GREATEST(v_subtotal, v_min_total);

  /* Ventana nocturna [start..23] ∪ [0..end-1] */
  SET v_mult =
    CASE
      WHEN (v_nstart IS NOT NULL AND v_nend IS NOT NULL) AND
           (HOUR(v_when) >= v_nstart OR HOUR(v_when) < v_nend)
      THEN COALESCE(v_night_mult,1.0)
      ELSE 1.0
    END;

  /* Redondeo */
  IF v_round_mode = 'decimals' THEN
    SET v_quoted = ROUND(v_subtotal * v_mult, COALESCE(v_round_decimals,0));
  ELSE
    SET v_quoted = round_to_step(v_subtotal * v_mult, COALESCE(v_round_step,1.00));
  END IF;

  /* 4) Persistir */
  UPDATE rides
     SET quoted_amount = v_quoted,
         fare_snapshot = JSON_OBJECT(
           'calc_at', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s'),
           'policy', JSON_OBJECT(
             'base_fee', v_base, 'per_km', v_per_km, 'per_min', v_per_min,
             'night_multiplier', v_night_mult,
             'night_start_hour', v_nstart, 'night_end_hour', v_nend,
             'round_mode', v_round_mode, 'round_decimals', v_round_decimals, 'round_step', v_round_step,
             'min_total', v_min_total
           ),
           'input', JSON_OBJECT(
             'distance_m', v_dist_m, 'duration_s', v_dur_s,
             'when', DATE_FORMAT(v_when, '%Y-%m-%d %H:%i:%s')
           ),
           'calc', JSON_OBJECT(
             'km', v_km, 'min', v_min,
             'subtotal', v_subtotal, 'multiplier', v_mult,
             'partial', v_partial
           ),
           'quoted_amount', v_quoted
         )
   WHERE id=p_ride_id AND tenant_id=p_tenant_id;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reject_offer_v2`(
  IN p_offer_id BIGINT UNSIGNED
)
BEGIN
  DECLARE v_ride_id BIGINT UNSIGNED;
  DECLARE v_tenant_id BIGINT UNSIGNED;
  DECLARE v_status VARCHAR(20);
  DECLARE v_expires DATETIME;

  -- Cargar oferta
  SELECT ro.ride_id, ro.tenant_id, ro.status, ro.expires_at
    INTO v_ride_id, v_tenant_id, v_status, v_expires
  FROM ride_offers ro
  WHERE ro.id = p_offer_id
  LIMIT 1;

  IF v_ride_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Oferta inexistente';
  END IF;

  IF v_status <> 'offered' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo ofertas en estado offered pueden rechazarse';
  END IF;

  IF v_expires IS NOT NULL AND v_expires < NOW() THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La oferta ya está expirada';
  END IF;

  -- Marcar rechazada
  UPDATE ride_offers
     SET responded_at = NOW(),
         response    = 'rejected',
         status      = 'rejected'
   WHERE id = p_offer_id;

  -- SIN SELECT final
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_release_ride_v1`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED
)
BEGIN
  DECLARE v_offer_id BIGINT UNSIGNED; DECLARE v_driver_id BIGINT UNSIGNED;
  DECLARE v_prev_status VARCHAR(20); DECLARE v_economico VARCHAR(64); DECLARE v_plate VARCHAR(64);

  SELECT status INTO v_prev_status
  FROM rides WHERE id=p_ride_id AND tenant_id=p_tenant_id AND driver_id IS NOT NULL LIMIT 1;

  IF v_prev_status IS NULL OR v_prev_status <> 'accepted' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ride no liberable (tenant/driver/status)';
  END IF;

  SELECT ro.id, ro.driver_id INTO v_offer_id, v_driver_id
  FROM ride_offers ro WHERE ro.tenant_id=p_tenant_id AND ro.ride_id=p_ride_id AND ro.status='accepted'
  ORDER BY ro.id DESC LIMIT 1;

  IF v_offer_id IS NOT NULL THEN
    UPDATE ride_offers SET responded_at=NOW(), response='released', status='released'
     WHERE id=v_offer_id AND tenant_id=p_tenant_id;
  END IF;

  UPDATE ride_offers
     SET responded_at=COALESCE(responded_at,NOW()),
         response=COALESCE(response,'expired'),
         status=CASE WHEN status='offered' THEN 'expired' ELSE status END
   WHERE tenant_id=p_tenant_id AND ride_id=p_ride_id AND status='offered';

  SELECT v.economico, v.plate INTO v_economico, v_plate
  FROM vehicles v JOIN rides r ON r.vehicle_id=v.id
  WHERE r.id=p_ride_id AND r.tenant_id=p_tenant_id LIMIT 1;

  UPDATE rides SET driver_id=NULL, vehicle_id=NULL, status='requested'
   WHERE id=p_ride_id AND tenant_id=p_tenant_id AND status='accepted';

  IF ROW_COUNT()=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se pudo liberar (carrera de estado)'; END IF;

  INSERT INTO ride_status_history(tenant_id, ride_id, prev_status, new_status, meta, created_at)
  VALUES (p_tenant_id, p_ride_id, v_prev_status, 'requested',
          JSON_OBJECT('released_offer_id', v_offer_id, 'prev_driver_id', v_driver_id,
                      'economico', v_economico, 'plate', v_plate),
          NOW());
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_ride_finish_v1`(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED
)
proc: BEGIN
  DECLARE v_driver_id BIGINT UNSIGNED;
  DECLARE v_prev_status VARCHAR(20);
  DECLARE v_next_offer_id BIGINT UNSIGNED;
  DECLARE v_next_ride_id  BIGINT UNSIGNED;

  DECLARE _not_found INT DEFAULT 0;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET _not_found = 1;

  START TRANSACTION;

  /* 1) Cargar ride */
  SET _not_found = 0;
  SELECT driver_id, status INTO v_driver_id, v_prev_status
  FROM rides
  WHERE id=p_ride_id AND tenant_id=p_tenant_id
  FOR UPDATE;

  IF _not_found = 1 THEN
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ride no existe';
  END IF;

  IF v_driver_id IS NULL THEN
    /* Termina sin promoción */
    UPDATE rides
       SET status='finished',
           finished_at=COALESCE(finished_at, NOW()),
           updated_at=NOW()
     WHERE id=p_ride_id AND tenant_id=p_tenant_id;

    INSERT INTO ride_status_history
      (tenant_id, ride_id, prev_status, new_status, created_at, updated_at)
    VALUES (p_tenant_id, p_ride_id, v_prev_status, 'finished', NOW(), NOW());

    COMMIT;
    SELECT 'finished' AS mode, NULL AS next_ride_id;
    LEAVE proc;
  END IF;

  /* 2) Terminar actual */
  UPDATE rides
     SET status='finished',
         finished_at=COALESCE(finished_at, NOW()),
         updated_at=NOW()
   WHERE id=p_ride_id AND tenant_id=p_tenant_id;

  INSERT INTO ride_status_history
    (tenant_id, ride_id, prev_status, new_status, created_at, updated_at)
  VALUES (p_tenant_id, p_ride_id, v_prev_status, 'finished', NOW(), NOW());

  /* 3) Buscar siguiente queued del mismo driver (orden estable) */
  SET _not_found = 0;
  SELECT o.id, o.ride_id
    INTO v_next_offer_id, v_next_ride_id
  FROM ride_offers o
  JOIN rides r ON r.id=o.ride_id AND r.tenant_id=o.tenant_id
  WHERE o.tenant_id=p_tenant_id
    AND o.driver_id = v_driver_id
    AND o.status    = 'queued'
  ORDER BY COALESCE(o.queued_position, 9999) ASC,
           COALESCE(o.queued_at, o.created_at) ASC,
           o.id ASC
  LIMIT 1
  FOR UPDATE;

  IF _not_found = 1 OR v_next_offer_id IS NULL THEN
    COMMIT;
    SELECT 'finished' AS mode, NULL AS next_ride_id;
    LEAVE proc;
  END IF;

  /* 4) Activar siguiente ride */
  UPDATE rides
     SET driver_id  = v_driver_id,
         status     = 'accepted',
         accepted_at= COALESCE(accepted_at, NOW()),
         updated_at = NOW()
   WHERE id=v_next_ride_id
     AND tenant_id=p_tenant_id
     AND driver_id IS NULL
     AND status IN ('requested','offered','queued');

  IF ROW_COUNT() = 0 THEN
    /* No se pudo activar (race); deja queued */
    COMMIT;
    SELECT 'finished' AS mode, NULL AS next_ride_id;
    LEAVE proc;
  END IF;

  UPDATE ride_offers
     SET status='accepted',
         response='accepted',
         responded_at=NOW(),
         updated_at=NOW()
   WHERE id=v_next_offer_id;

  /* Marcar el resto de offers del ride como atendidas */
  UPDATE ride_offers
     SET responded_at = COALESCE(responded_at, NOW()),
         response     = COALESCE(response, 'rejected'),
         status       = CASE WHEN status='offered' THEN 'rejected' ELSE status END,
         updated_at   = NOW()
   WHERE ride_id   = v_next_ride_id
     AND tenant_id = p_tenant_id
     AND id       <> v_next_offer_id;

  INSERT INTO ride_status_history
    (tenant_id, ride_id, prev_status, new_status, created_at, updated_at, meta)
  VALUES
    (p_tenant_id, v_next_ride_id, 'queued', 'accepted', NOW(), NOW(),
     JSON_OBJECT('promoted_from_ride', p_ride_id));

  COMMIT;
  SELECT 'promoted' AS mode, v_next_ride_id AS next_ride_id;
  LEAVE proc;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_set_route_v1`(
  IN p_tenant_id   BIGINT UNSIGNED,
  IN p_ride_id     BIGINT UNSIGNED,
  IN p_distance_m  INT,
  IN p_duration_s  INT,
  IN p_polyline    TEXT
)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM rides WHERE id=p_ride_id AND tenant_id=p_tenant_id LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ride inexistente o de otro tenant';
  END IF;

  UPDATE rides
     SET distance_m = p_distance_m,
         duration_s = p_duration_s,
         route_polyline = p_polyline
   WHERE id = p_ride_id AND tenant_id = p_tenant_id;
END$$
DELIMITER ;
