<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientsReportController extends Controller
{
    /**
     * BI tiers:
     * - Gold: alta frecuencia reciente
     * - Silver: frecuencia media
     * - Bronze: resto
     *
     * Ajusta umbrales si deseas.
     */
    private function tierCaseSql(): string
    {
        // finished_90 y spent_90 se calculan en SELECT
        return "
            CASE
              WHEN finished_90 >= 20 OR spent_90 >= 5000 THEN 'gold'
              WHEN finished_90 >= 6  OR spent_90 >= 1200 THEN 'silver'
              ELSE 'bronze'
            END
        ";
    }

    /**
     * Normaliza un teléfono a solo dígitos (para keys tipo ph-##########)
     */
    private function onlyDigits(?string $phone): string
    {
        $p = (string)($phone ?? '');
        $p = preg_replace('/\D+/', '', $p);
        return $p ?: '';
    }

    public function index(Request $request)
    {
        $tenantId = (int)(auth()->user()->tenant_id ?? 0);
        if (!$tenantId) abort(403, 'Usuario sin tenant asignado');

        // Filtros BI
        $q     = trim((string)$request->input('q', ''));
        $from  = $request->input('from'); // YYYY-MM-DD
        $to    = $request->input('to');   // YYYY-MM-DD
        $tier  = $request->input('tier'); // bronze|silver|gold
        $sort  = $request->input('sort', 'spent_90'); // spent_90|finished_90|last_ride_at|lifetime_spent
        $dir   = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Base: solo rides del tenant actual (la regla clave)
        $base = DB::table('rides as r')
            ->where('r.tenant_id', $tenantId);

        // Rango de fechas (se aplica sobre requested_at/created_at; ajusta si prefieres finished_at)
        if ($from) {
            $base->whereRaw("COALESCE(r.requested_at, r.created_at) >= ?", [$from . ' 00:00:00']);
        }
        if ($to) {
            $base->whereRaw("COALESCE(r.requested_at, r.created_at) <= ?", [$to . ' 23:59:59']);
        }

        // Query BI (agrupa por una “clave de cliente” derivada de rides, NO del tenant de passenger)
        // passenger_ref:
        // - id-<passenger_id> si existe passenger_id
        // - ph-<digits> si no existe passenger_id (fallback por phone)
        $tierCase = $this->tierCaseSql();

        $rows = DB::query()
            ->fromSub(
                $base->clone()
                    ->leftJoin('passengers as p', 'r.passenger_id', '=', 'p.id')
                    ->selectRaw("
                        CASE
                          WHEN r.passenger_id IS NOT NULL THEN CONCAT('id-', r.passenger_id)
                          ELSE CONCAT('ph-', REGEXP_REPLACE(COALESCE(r.passenger_phone,''), '[^0-9]', ''))
                        END AS passenger_ref
                    ")
                    ->selectRaw("
                        MAX(r.passenger_id) as passenger_id,
                        COALESCE(MAX(p.name), MAX(r.passenger_name)) as passenger_name,
                        COALESCE(MAX(p.phone), MAX(r.passenger_phone)) as passenger_phone,
                        MAX(p.email) as passenger_email,
                        MAX(p.avatar_url) as passenger_avatar_url,
                        MAX(p.is_corporate) as is_corporate
                    ")
                    ->selectRaw("COUNT(r.id) as total_rides")
                    ->selectRaw("SUM(CASE WHEN r.status = 'finished' THEN 1 ELSE 0 END) as finished_rides")
                    ->selectRaw("SUM(CASE WHEN r.status = 'canceled' THEN 1 ELSE 0 END) as canceled_rides")
                    ->selectRaw("MAX(COALESCE(r.finished_at, r.requested_at, r.created_at)) as last_ride_at")
                    ->selectRaw("MIN(COALESCE(r.requested_at, r.created_at)) as first_ride_at")
                    ->selectRaw("
                        SUM(
                          CASE WHEN r.status='finished'
                          THEN COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0)
                          ELSE 0 END
                        ) as lifetime_spent
                    ")
                    ->selectRaw("
                        SUM(
                          CASE WHEN r.status='finished'
                           AND COALESCE(r.finished_at, r.requested_at, r.created_at) >= (NOW() - INTERVAL 90 DAY)
                          THEN 1 ELSE 0 END
                        ) as finished_90
                    ")
                    ->selectRaw("
                        SUM(
                          CASE WHEN r.status='finished'
                           AND COALESCE(r.finished_at, r.requested_at, r.created_at) >= (NOW() - INTERVAL 90 DAY)
                          THEN COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0)
                          ELSE 0 END
                        ) as spent_90
                    ")
                    ->selectRaw("
                        ROUND(AVG(
                          CASE WHEN r.status='finished'
                          THEN COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, NULL)
                          ELSE NULL END
                        ), 2) as avg_ticket
                    ")
                    ->groupBy('passenger_ref'),
                'x'
            )
            ->selectRaw("x.*, {$tierCase} as tier");

        // Búsqueda por texto (nombre/teléfono/email)
        if ($q !== '') {
            $rows->where(function ($w) use ($q) {
                $w->where('passenger_name', 'like', "%{$q}%")
                  ->orWhere('passenger_phone', 'like', "%{$q}%")
                  ->orWhere('passenger_email', 'like', "%{$q}%")
                  ->orWhere('passenger_ref', 'like', "%{$q}%");
            });
        }

        if (in_array($tier, ['bronze','silver','gold'], true)) {
            $rows->whereRaw("({$tierCase}) = ?", [$tier]);
        }

        // Orden
        $allowedSort = ['spent_90','finished_90','last_ride_at','lifetime_spent','avg_ticket','total_rides'];
        if (!in_array($sort, $allowedSort, true)) $sort = 'spent_90';

        $rows->orderBy($sort, $dir);

        $clients = $rows->paginate(25)->withQueryString();

        // KPIs superiores (opcional, pero BI lo agradece)
        $kpis = DB::table('rides as r')
            ->where('r.tenant_id', $tenantId)
            ->when($from, fn($qq) => $qq->whereRaw("COALESCE(r.requested_at, r.created_at) >= ?", [$from.' 00:00:00']))
            ->when($to,   fn($qq) => $qq->whereRaw("COALESCE(r.requested_at, r.created_at) <= ?", [$to.' 23:59:59']))
            ->selectRaw("COUNT(*) as rides_total")
            ->selectRaw("SUM(CASE WHEN r.status='finished' THEN 1 ELSE 0 END) as rides_finished")
            ->selectRaw("SUM(CASE WHEN r.status='canceled' THEN 1 ELSE 0 END) as rides_canceled")
            ->selectRaw("
                SUM(CASE WHEN r.status='finished'
                THEN COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0) ELSE 0 END) as spent_total
            ")
            ->first();

        return view('admin.reports.clients.index', compact('clients','kpis','tenantId'));
    }

    public function show(Request $request, string $ref)
    {
        $tenantId = (int)(auth()->user()->tenant_id ?? 0);
        if (!$tenantId) abort(403, 'Usuario sin tenant asignado');

        // ref esperado: id-<id> o ph-<digits>
        $mode = null;
        $id = null;
        $phoneDigits = null;

        if (preg_match('/^id-(\d+)$/', $ref, $m)) {
            $mode = 'id';
            $id = (int)$m[1];
        } elseif (preg_match('/^ph-(\d+)$/', $ref, $m)) {
            $mode = 'phone';
            $phoneDigits = $m[1];
        } else {
            abort(404, 'Cliente no válido');
        }

        // Base rides del tenant
        $ridesBase = DB::table('rides as r')
            ->where('r.tenant_id', $tenantId);

        if ($mode === 'id') {
            $ridesBase->where('r.passenger_id', $id);
        } else {
            // match por phone normalizado (solo dígitos)
            $ridesBase->whereRaw("REGEXP_REPLACE(COALESCE(r.passenger_phone,''), '[^0-9]', '') = ?", [$phoneDigits]);
        }

        // Header del cliente (enriquecido con passengers si existe)
        $client = (clone $ridesBase)
            ->leftJoin('passengers as p', 'r.passenger_id', '=', 'p.id')
            ->selectRaw("
                MAX(r.passenger_id) as passenger_id,
                COALESCE(MAX(p.name), MAX(r.passenger_name)) as passenger_name,
                COALESCE(MAX(p.phone), MAX(r.passenger_phone)) as passenger_phone,
                MAX(p.email) as passenger_email,
                MAX(p.avatar_url) as passenger_avatar_url,
                MAX(p.is_corporate) as is_corporate,
                COUNT(r.id) as total_rides,
                SUM(CASE WHEN r.status='finished' THEN 1 ELSE 0 END) as finished_rides,
                SUM(CASE WHEN r.status='canceled' THEN 1 ELSE 0 END) as canceled_rides,
                SUM(CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0) ELSE 0 END) as lifetime_spent,
                MAX(COALESCE(r.finished_at, r.requested_at, r.created_at)) as last_ride_at
            ")
            ->first();

        if (!$client) abort(404, 'Sin datos para este cliente');

        // Tier (mismo criterio que index, pero recalculado aquí)
        $tier = DB::query()
            ->fromSub(
                (clone $ridesBase)
                    ->selectRaw("
                        SUM(
                          CASE WHEN r.status='finished'
                           AND COALESCE(r.finished_at, r.requested_at, r.created_at) >= (NOW() - INTERVAL 90 DAY)
                          THEN 1 ELSE 0 END
                        ) as finished_90
                    ")
                    ->selectRaw("
                        SUM(
                          CASE WHEN r.status='finished'
                           AND COALESCE(r.finished_at, r.requested_at, r.created_at) >= (NOW() - INTERVAL 90 DAY)
                          THEN COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0)
                          ELSE 0 END
                        ) as spent_90
                    "),
                't'
            )
            ->selectRaw($this->tierCaseSql() . " as tier")
            ->first();

        $tier = $tier?->tier ?? 'bronze';

        // Rides detallados (con driver/vehicle)
        $rides = (clone $ridesBase)
            ->leftJoin('drivers as d', 'r.driver_id', '=', 'd.id')
            ->leftJoin('vehicles as v', 'r.vehicle_id', '=', 'v.id')
            ->select([
                'r.id','r.status','r.payment_method','r.requested_channel',
                'r.origin_label','r.dest_label',
                'r.distance_m','r.duration_s',
                'r.quoted_amount','r.total_amount','r.agreed_amount','r.currency',
                'r.requested_at','r.accepted_at','r.onboard_at','r.finished_at','r.canceled_at',
                'r.driver_id','r.vehicle_id',
                'd.name as driver_name',
                'v.economico as vehicle_economico',
                'v.plate as vehicle_plate',
                'v.brand as vehicle_brand',
                'v.model as vehicle_model',
            ])
            ->orderByRaw("COALESCE(r.finished_at, r.requested_at, r.created_at) DESC")
            ->paginate(20)
            ->withQueryString();

        // Consumo por taxi (vehicle) y por conductor (driver)
        $byVehicle = (clone $ridesBase)
            ->whereNotNull('r.vehicle_id')
            ->leftJoin('vehicles as v', 'r.vehicle_id', '=', 'v.id')
            ->selectRaw("
                r.vehicle_id,
                MAX(v.economico) as economico,
                MAX(v.plate) as plate,
                MAX(v.brand) as brand,
                MAX(v.model) as model,
                COUNT(*) as rides_total,
                SUM(CASE WHEN r.status='finished' THEN 1 ELSE 0 END) as rides_finished,
                SUM(CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0) ELSE 0 END) as spent
            ")
            ->groupBy('r.vehicle_id')
            ->orderByDesc('spent')
            ->limit(10)
            ->get();

        $byDriver = (clone $ridesBase)
            ->whereNotNull('r.driver_id')
            ->leftJoin('drivers as d', 'r.driver_id', '=', 'd.id')
            ->selectRaw("
                r.driver_id,
                MAX(d.name) as name,
                COUNT(*) as rides_total,
                SUM(CASE WHEN r.status='finished' THEN 1 ELSE 0 END) as rides_finished,
                SUM(CASE WHEN r.status='finished' THEN COALESCE(r.agreed_amount, r.total_amount, r.quoted_amount, 0) ELSE 0 END) as spent
            ")
            ->groupBy('r.driver_id')
            ->orderByDesc('spent')
            ->limit(10)
            ->get();

        return view('admin.reports.clients.show', compact(
            'tenantId','ref','client','tier','rides','byVehicle','byDriver'
        ));
    }
}
