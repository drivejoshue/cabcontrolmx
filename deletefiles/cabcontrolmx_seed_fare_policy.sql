-- CabcontrolMX seed: tenant_fare_policies (ejemplo)
-- Fecha: 2025-10-10

USE cabcontrolmx;

INSERT INTO tenant_fare_policies
(tenant_id, base_fee, per_km, per_min, min_total, night_multiplier,
 night_start_hour, night_end_hour, round_mode, round_decimals, round_step, created_at)
VALUES
(1, 35.00, 8.50, 1.80, 40.00, 1.25,
 22, 6, 'step', 0, 1.00, NOW());
