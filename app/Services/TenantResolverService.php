<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantResolverService
{
    public function resolveForPickupPoint(float $lat, float $lng): ?Tenant
    {
        // 1) Candidatos por cobertura (tu lógica, pero en SQL para no traer todo)
        $candidates = Tenant::query()
            ->where('allow_marketplace', 1)
            ->where('public_active', 1)
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->select([
                'tenants.*',
                DB::raw("COALESCE(coverage_radius_km, 30.0) AS effective_radius_km"),
                DB::raw("ST_Distance_Sphere(POINT(longitud, latitud), POINT(?, ?)) / 1000.0 AS center_distance_km"),
            ])
            ->addBinding([$lng, $lat], 'select')
            ->havingRaw("center_distance_km <= effective_radius_km")
            ->orderBy('center_distance_km')
            ->get();

        if ($candidates->isEmpty()) return null;

        $tenantIds = $candidates->pluck('id')->all();

        // 2) Elegir tenant por "pool real" cerca del pickup
        $bestTenantId = $this->pickBestTenantBySupply($tenantIds, $lat, $lng);

        if ($bestTenantId) {
            return $candidates->firstWhere('id', $bestTenantId) ?? $candidates->first();
        }

        // fallback: el más cercano al centro (como hoy)
        return $candidates->first();
    }

    private function pickBestTenantBySupply(array $tenantIds, float $lat, float $lng): ?int
    {
        if (empty($tenantIds)) return null;

        // Ajustables (ideal en config/sysadmin)
        $FRESH_SEC        = 120;
        $MIN_GROUP        = 3;
        $PENALTY_M        = 800;   // castigo por driver faltante
        $TOP_PER_TENANT   = 3;
        $MAX_DRIVERS_SCAN = 80;

        // SQL: 1) última ubicación por driver (POR tenant)
        //      2) drivers elegibles (idle + shift abierto + fresh + sin ride/offer)
        //      3) rank por tenant (ROW_NUMBER)
        //      4) agregación por tenant (cnt y avg top3)
        //      5) score = avg_top3_m + (missing * PENALTY_M)

        $in = implode(',', array_fill(0, count($tenantIds), '?'));

        $sql = "
WITH last_loc AS (
    SELECT dl1.tenant_id, dl1.driver_id, MAX(dl1.id) AS last_id
    FROM driver_locations dl1
    WHERE dl1.tenant_id IN ($in)
    GROUP BY dl1.tenant_id, dl1.driver_id
),
loc AS (
    SELECT dl.tenant_id, dl.driver_id, dl.lat, dl.lng, dl.reported_at
    FROM driver_locations dl
    JOIN last_loc ll
      ON ll.tenant_id = dl.tenant_id
     AND ll.driver_id = dl.driver_id
     AND ll.last_id   = dl.id
    WHERE dl.reported_at >= (NOW() - INTERVAL ? SECOND)
),
active_rides AS (
    SELECT r.driver_id
    FROM rides r
    WHERE r.tenant_id IN ($in)
      AND r.driver_id IS NOT NULL
      AND UPPER(r.status) IN ('ON_BOARD','ONBOARD','BOARDING','EN_ROUTE','ARRIVED','ACCEPTED','ASSIGNED','REQUESTED','SCHEDULED')
    GROUP BY r.driver_id
),
active_offers AS (
    SELECT o.driver_id
    FROM ride_offers o
    WHERE o.tenant_id IN ($in)
      AND LOWER(o.status) = 'offered'
      AND (o.expires_at IS NULL OR o.expires_at > NOW())
    GROUP BY o.driver_id
),
eligible AS (
    SELECT
      d.tenant_id,
      d.id AS driver_id,
      ST_Distance_Sphere(POINT(loc.lng, loc.lat), POINT(?, ?)) AS dist_m
    FROM drivers d
    JOIN driver_shifts ds
      ON ds.driver_id = d.id
     AND ds.tenant_id = d.tenant_id
     AND ds.ended_at IS NULL
    JOIN loc
      ON loc.driver_id = d.id
     AND loc.tenant_id = d.tenant_id
    LEFT JOIN active_rides ar ON ar.driver_id = d.id
    LEFT JOIN active_offers ao ON ao.driver_id = d.id
    WHERE d.tenant_id IN ($in)
      AND LOWER(COALESCE(d.status,'offline')) = 'idle'
      AND ar.driver_id IS NULL
      AND ao.driver_id IS NULL
),
ranked AS (
    SELECT
      e.*,
      ROW_NUMBER() OVER (PARTITION BY e.tenant_id ORDER BY e.dist_m ASC) AS rn
    FROM eligible e
),
agg AS (
    SELECT
      tenant_id,
      COUNT(*) AS cnt,
      AVG(CASE WHEN rn <= ? THEN dist_m END) AS avg_top_m
    FROM ranked
    WHERE rn <= ?
    GROUP BY tenant_id
)
SELECT
  tenant_id,
  (avg_top_m + (GREATEST(0, ? - cnt) * ?)) AS score
FROM agg
ORDER BY score ASC
LIMIT 1
";

        $bindings = [];
        // tenantIds para last_loc
        $bindings = array_merge($bindings, $tenantIds);
        // fresh seconds
        $bindings[] = $FRESH_SEC;
        // tenantIds para active_rides
        $bindings = array_merge($bindings, $tenantIds);
        // tenantIds para active_offers
        $bindings = array_merge($bindings, $tenantIds);
        // pickup point lng/lat para distance
        $bindings[] = $lng;
        $bindings[] = $lat;
        // tenantIds para eligible drivers WHERE
        $bindings = array_merge($bindings, $tenantIds);
        // top per tenant (AVG)
        $bindings[] = $TOP_PER_TENANT;
        // ranked filter rn <= ?
        $bindings[] = $MAX_DRIVERS_SCAN; // aquí lo usamos como "cap" de ranked por tenant si quieres; si no, pon TOP_PER_TENANT
        // min group + penalty
        $bindings[] = $MIN_GROUP;
        $bindings[] = $PENALTY_M;

        // Ajuste: en agg puse rn <= ? (MAX_DRIVERS_SCAN) y AVG usa rn<=TOP_PER_TENANT.
        // Si prefieres, cambia ranked WHERE rn <= TOP_PER_TENANT y quitas MAX_DRIVERS_SCAN.

        $row = DB::selectOne($sql, $bindings);

        return $row ? (int)$row->tenant_id : null;
    }
}
