<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_offer_wave_prio_v3");

        DB::unprepared(<<<'SQL'
CREATE PROCEDURE sp_offer_wave_prio_v3(
  IN p_tenant_id BIGINT UNSIGNED,
  IN p_ride_id   BIGINT UNSIGNED,
  IN p_radius_km DOUBLE,
  IN p_limit_n   INT,
  IN p_expires_sec INT
)
wave_flow: BEGIN
  DECLARE v_lat DOUBLE;
  DECLARE v_lng DOUBLE;
  DECLARE v_ride_status VARCHAR(32);

  DECLARE v_stand_id BIGINT UNSIGNED DEFAULT NULL;

  DECLARE v_limit_n INT DEFAULT 7;
  DECLARE v_expires_sec INT DEFAULT 30;
  DECLARE v_radius_km DOUBLE DEFAULT 5.00;
  DECLARE v_stand_radius_km DOUBLE DEFAULT 3.00;

  DECLARE v_fresh_sec INT DEFAULT 120;
  DECLARE v_max_inbox INT DEFAULT 10;

  DECLARE v_driver_id BIGINT UNSIGNED;
  DECLARE v_count INT DEFAULT 0;
  DECLARE v_need INT DEFAULT 0;
  DECLARE done INT DEFAULT 0;

  /* one-by-one base */
  DECLARE v_top_driver_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_offer_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_offer_expires DATETIME DEFAULT NULL;

  /* Cursor sobre tmp_wave_candidates */
  DECLARE cur CURSOR FOR
    SELECT driver_id
    FROM tmp_wave_candidates
    ORDER BY
      priority_grp ASC,
      CASE WHEN queue_pos IS NULL THEN 999999 ELSE queue_pos END ASC,
      CASE WHEN last_sent_at IS NULL THEN 0 ELSE 1 END ASC,
      last_sent_at ASC,
      distance_km ASC;

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  /* settings */
  SELECT
    COALESCE(ds.stand_radius_km, 3.00),
    COALESCE(p_radius_km, ds.auto_dispatch_radius_km, 5.00),
    COALESCE(p_expires_sec, ds.offer_expires_sec, 60),
    COALESCE(p_limit_n, ds.wave_size_n, 3)
  INTO v_stand_radius_km, v_radius_km, v_expires_sec, v_limit_n
  FROM (SELECT 1) x
  LEFT JOIN dispatch_settings ds
    ON ds.tenant_id = p_tenant_id
  LIMIT 1;

  IF v_limit_n IS NULL OR v_limit_n < 1 THEN SET v_limit_n = 1; END IF;
  IF v_limit_n > 50 THEN SET v_limit_n = 50; END IF;
  IF v_expires_sec IS NULL OR v_expires_sec < 10 THEN SET v_expires_sec = 10; END IF;
  IF v_radius_km IS NULL OR v_radius_km <= 0 THEN SET v_radius_km = 5.00; END IF;

  /* ride ofertable */
  SELECT r.origin_lat, r.origin_lng, r.status
    INTO v_lat, v_lng, v_ride_status
  FROM (SELECT 1) x
  LEFT JOIN rides r
    ON r.id = p_ride_id
   AND r.tenant_id = p_tenant_id
   AND r.driver_id IS NULL
   AND r.status IN ('requested','offered','scheduled')
  LIMIT 1;

  IF v_lat IS NULL OR v_lng IS NULL OR v_ride_status IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Ride no ofertable (tenant/status/driver_id/origen)';
  END IF;

  /* lastloc */
  DROP TEMPORARY TABLE IF EXISTS tmp_driver_lastloc;
  CREATE TEMPORARY TABLE tmp_driver_lastloc (
    driver_id        BIGINT UNSIGNED PRIMARY KEY,
    last_reported_at DATETIME NOT NULL,
    lat              DOUBLE NOT NULL,
    lng              DOUBLE NOT NULL
  ) ENGINE=MEMORY;

  INSERT INTO tmp_driver_lastloc (driver_id, last_reported_at, lat, lng)
  SELECT dl.driver_id, dl.reported_at, dl.lat, dl.lng
  FROM driver_locations dl
  JOIN (
    SELECT driver_id, MAX(id) AS last_id
    FROM driver_locations
    WHERE tenant_id = p_tenant_id
    GROUP BY driver_id
  ) x
    ON x.driver_id = dl.driver_id AND x.last_id = dl.id
  WHERE dl.tenant_id = p_tenant_id;

  /* stand más cercano dentro de radio */
  SELECT (
    SELECT ts.id
    FROM taxi_stands ts
    WHERE ts.tenant_id = p_tenant_id
      AND ts.activo = 1
      AND haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) <= v_stand_radius_km
    ORDER BY haversine_km(ts.latitud, ts.longitud, v_lat, v_lng) ASC, ts.id ASC
    LIMIT 1
  ) INTO v_stand_id;

  /* =========================
   * BASE ONE-BY-ONE
   * ========================= */
  IF v_stand_id IS NOT NULL THEN
    SET v_top_driver_id = NULL;
    SET v_offer_id = NULL;
    SET v_offer_expires = NULL;

    /* top driver en cola elegible */
    SELECT q.driver_id
      INTO v_top_driver_id
    FROM taxi_stand_queue q
    JOIN drivers d
      ON d.id = q.driver_id
     AND d.tenant_id = p_tenant_id
     AND d.status = 'idle'
    JOIN tmp_driver_lastloc ll
      ON ll.driver_id = d.id
     AND ll.last_reported_at >= DATE_SUB(NOW(), INTERVAL v_fresh_sec SECOND)
    JOIN driver_shifts s
      ON s.driver_id = q.driver_id
     AND s.tenant_id = p_tenant_id
     AND s.status = 'abierto'
     AND s.vehicle_id IS NOT NULL
    WHERE q.tenant_id = p_tenant_id
      AND q.stand_id  = v_stand_id
      AND q.status    = 'en_cola'
      AND NOT EXISTS (
        SELECT 1
        FROM ride_offers ro
        WHERE ro.tenant_id = p_tenant_id
          AND ro.ride_id   = p_ride_id
          AND ro.driver_id = q.driver_id
          AND ro.status IN ('offered','accepted','queued','pending_passenger')
      )
      AND (
        SELECT COUNT(*)
        FROM ride_offers rox
        WHERE rox.tenant_id = p_tenant_id
          AND rox.driver_id = q.driver_id
          AND rox.status IN ('offered','pending_passenger','queued')
          AND rox.expires_at > NOW()
      ) < v_max_inbox
    ORDER BY q.position ASC
    LIMIT 1;

    /* si hay top driver, crear SOLO 1 offer y salir */
    IF v_top_driver_id IS NOT NULL THEN
      CALL sp_create_offer_v2(p_tenant_id, p_ride_id, v_top_driver_id, v_expires_sec);

      /* requested -> offered */
      IF v_ride_status = 'requested' THEN
        UPDATE rides
           SET status = 'offered',
               updated_at = NOW()
         WHERE id = p_ride_id
           AND tenant_id = p_tenant_id
           AND status = 'requested'
           AND driver_id IS NULL;
      END IF;

      DROP TEMPORARY TABLE IF EXISTS tmp_driver_lastloc;
      LEAVE wave_flow;
    END IF;
    /* si no hay top driver, cae a calle */
  END IF;

  /* =========================
   * CALLE (tmp_wave_candidates + cursor)
   * ========================= */
  DROP TEMPORARY TABLE IF EXISTS tmp_wave_candidates;
  CREATE TEMPORARY TABLE tmp_wave_candidates (
    driver_id     BIGINT UNSIGNED PRIMARY KEY,
    priority_grp  TINYINT NOT NULL,
    queue_pos     INT NULL,
    last_sent_at  DATETIME NULL,
    distance_km   DOUBLE NOT NULL
  ) ENGINE=MEMORY;

  /* Grupo 2A: cercanos idle */
  INSERT IGNORE INTO tmp_wave_candidates (driver_id, priority_grp, queue_pos, last_sent_at, distance_km)
  SELECT
    d.id,
    2,
    NULL,
    (SELECT MAX(ro0.sent_at)
       FROM ride_offers ro0
      WHERE ro0.tenant_id = p_tenant_id
        AND ro0.driver_id = d.id
    ) AS last_sent_at,
    haversine_km(ll.lat, ll.lng, v_lat, v_lng) AS distance_km
  FROM drivers d
  JOIN tmp_driver_lastloc ll
    ON ll.driver_id = d.id
   AND ll.last_reported_at >= DATE_SUB(NOW(), INTERVAL v_fresh_sec SECOND)
  JOIN driver_shifts s
    ON s.driver_id = d.id
   AND s.tenant_id = p_tenant_id
   AND s.status = 'abierto'
   AND s.vehicle_id IS NOT NULL
  WHERE d.tenant_id = p_tenant_id
    AND d.status = 'idle'
    AND haversine_km(ll.lat, ll.lng, v_lat, v_lng) <= v_radius_km
    AND NOT EXISTS (
      SELECT 1
      FROM ride_offers ro
      WHERE ro.tenant_id = p_tenant_id
        AND ro.ride_id   = p_ride_id
        AND ro.driver_id = d.id
        AND ro.status IN ('offered','accepted','queued','pending_passenger')
    )
    AND NOT EXISTS (
      SELECT 1
      FROM ride_offers ro2
      WHERE ro2.tenant_id = p_tenant_id
        AND ro2.ride_id   = p_ride_id
        AND ro2.driver_id = d.id
        AND ro2.sent_at  >= DATE_SUB(NOW(), INTERVAL v_expires_sec SECOND)
    )
    AND (
      SELECT COUNT(*)
      FROM ride_offers rox
      WHERE rox.tenant_id = p_tenant_id
        AND rox.driver_id = d.id
        AND rox.status IN ('offered','pending_passenger','queued')
        AND rox.expires_at > NOW()
    ) < v_max_inbox
  ORDER BY last_sent_at ASC, distance_km ASC
  LIMIT v_limit_n;

  /* fill busy/on_ride si faltan */
  SET v_need = v_limit_n - (SELECT COUNT(*) FROM tmp_wave_candidates);
  IF v_need > 0 THEN
    INSERT IGNORE INTO tmp_wave_candidates (driver_id, priority_grp, queue_pos, last_sent_at, distance_km)
    SELECT
      d.id,
      3,
      NULL,
      (SELECT MAX(ro0.sent_at)
         FROM ride_offers ro0
        WHERE ro0.tenant_id = p_tenant_id
          AND ro0.driver_id = d.id
      ) AS last_sent_at,
      haversine_km(ll.lat, ll.lng, v_lat, v_lng) AS distance_km
    FROM drivers d
    JOIN tmp_driver_lastloc ll
      ON ll.driver_id = d.id
     AND ll.last_reported_at >= DATE_SUB(NOW(), INTERVAL v_fresh_sec SECOND)
    JOIN driver_shifts s
      ON s.driver_id = d.id
     AND s.tenant_id = p_tenant_id
     AND s.status = 'abierto'
     AND s.vehicle_id IS NOT NULL
    WHERE d.tenant_id = p_tenant_id
      AND d.status IN ('busy','on_ride')
      AND haversine_km(ll.lat, ll.lng, v_lat, v_lng) <= v_radius_km
      AND NOT EXISTS (
        SELECT 1
        FROM ride_offers ro
        WHERE ro.tenant_id = p_tenant_id
          AND ro.ride_id   = p_ride_id
          AND ro.driver_id = d.id
          AND ro.status IN ('offered','accepted','queued','pending_passenger')
      )
      AND NOT EXISTS (
        SELECT 1
        FROM ride_offers ro2
        WHERE ro2.tenant_id = p_tenant_id
          AND ro2.ride_id   = p_ride_id
          AND ro2.driver_id = d.id
          AND ro2.sent_at  >= DATE_SUB(NOW(), INTERVAL v_expires_sec SECOND)
      )
      AND (
        SELECT COUNT(*)
        FROM ride_offers rox
        WHERE rox.tenant_id = p_tenant_id
          AND rox.driver_id = d.id
          AND rox.status IN ('offered','pending_passenger','queued')
          AND rox.expires_at > NOW()
      ) < v_max_inbox
    ORDER BY last_sent_at ASC, distance_km ASC
    LIMIT v_need;
  END IF;

  /* Emitir offers */
  SET v_count = 0;
  SET done = 0;
  OPEN cur;

  read_loop: LOOP
    FETCH cur INTO v_driver_id;
    IF done = 1 THEN LEAVE read_loop; END IF;

    offer_try: BEGIN
      DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;
      CALL sp_create_offer_v2(p_tenant_id, p_ride_id, v_driver_id, v_expires_sec);
    END offer_try;

    SET v_count = v_count + 1;
    IF v_count >= v_limit_n THEN LEAVE read_loop; END IF;
  END LOOP;

  CLOSE cur;

  /* requested -> offered */
  IF v_ride_status = 'requested' THEN
    UPDATE rides
       SET status = 'offered',
           updated_at = NOW()
     WHERE id = p_ride_id
       AND tenant_id = p_tenant_id
       AND status = 'requested'
       AND driver_id IS NULL;
  END IF;

  DROP TEMPORARY TABLE IF EXISTS tmp_driver_lastloc;
  DROP TEMPORARY TABLE IF EXISTS tmp_wave_candidates;
END

SQL
        );
    }

    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_offer_wave_prio_v3");
    }
};
