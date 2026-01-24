<?php

namespace App\Services\Partner;

use Illuminate\Support\Facades\DB;

class PartnerMetricsService
{
    private int $pingFreshSec = 120;

    public function dashboard(int $tenantId, int $partnerId, string $range = 'today'): array
    {
        $tz = DB::table('tenants')->where('id', $tenantId)->value('timezone') ?: 'America/Mexico_City';
        $now = now($tz);

        // Rango por requested_at (recuerda: ustedes guardan timestamps en hora local del tenant)
        [$from, $to] = match ($range) {
            '7d'  => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '30d' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };

        $fromStr = $from->format('Y-m-d H:i:s');
        $toStr   = $to->format('Y-m-d H:i:s');

        // -----------------------------
        // VEHICLES
        // -----------------------------
        $vehicles = DB::table('vehicles')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN active=1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN verification_status="verified" THEN 1 ELSE 0 END) as verified')
            ->selectRaw('SUM(CASE WHEN verification_status="pending" THEN 1 ELSE 0 END) as pending')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->first();

        // -----------------------------
        // DRIVERS (online = ping fresco)
        // -----------------------------
        $freshAt = $now->copy()->subSeconds($this->pingFreshSec)->format('Y-m-d H:i:s');

        $drivers = DB::table('drivers')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN active=1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN last_ping_at IS NOT NULL AND last_ping_at >= ? THEN 1 ELSE 0 END) as online', [$freshAt])
            ->selectRaw('SUM(CASE WHEN status="idle" THEN 1 ELSE 0 END) as idle')
            ->selectRaw('SUM(CASE WHEN status="busy" THEN 1 ELSE 0 END) as busy')
            ->selectRaw('SUM(CASE WHEN status="on_ride" THEN 1 ELSE 0 END) as on_ride')
            ->selectRaw('SUM(CASE WHEN status="offline" THEN 1 ELSE 0 END) as offline')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->first();

        // -----------------------------
        // WALLET (partner_wallets)
        // -----------------------------
        $wallet = DB::table('partner_wallets')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->first();

        $walletBalance = $wallet ? (float)$wallet->balance : 0.0;
        $walletCurrency = $wallet ? (string)$wallet->currency : 'MXN';

        // Deuda / cargos no liquidados
        $charges = DB::table('partner_daily_charges')
            ->selectRaw('COALESCE(SUM(unpaid_amount),0) as unpaid_total')
            ->selectRaw('COALESCE(SUM(amount),0) as amount_total')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->whereNull('settled_at')
            ->first();

        // -----------------------------
        // RIDES (por driver/vehicle del partner)
        // -----------------------------
        $baseRides = DB::table('rides as r')
            ->leftJoin('vehicles as v', function ($j) use ($tenantId) {
                $j->on('v.id', '=', 'r.vehicle_id')
                  ->where('v.tenant_id', '=', $tenantId);
            })
            ->leftJoin('drivers as d', function ($j) use ($tenantId) {
                $j->on('d.id', '=', 'r.driver_id')
                  ->where('d.tenant_id', '=', $tenantId);
            })
            ->where('r.tenant_id', $tenantId)
            ->whereBetween('r.requested_at', [$fromStr, $toStr])
            ->where(function ($q) use ($partnerId) {
                $q->where('v.partner_id', $partnerId)
                  ->orWhere('d.partner_id', $partnerId);
            });

        $ridesByStatus = (clone $baseRides)
            ->selectRaw('LOWER(r.status) as st, COUNT(*) as c')
            ->groupBy('st')
            ->pluck('c', 'st')
            ->toArray();

        $ridesTotal = array_sum(array_map('intval', $ridesByStatus));

        // “Activos” (ajusta si tu enum maneja otros estados)
        $activeStates = ['accepted','arrived','onboard','on_ride','started','enroute'];
        $ridesActive = 0;
        foreach ($activeStates as $st) $ridesActive += (int)($ridesByStatus[$st] ?? 0);

        // Últimos rides (para listita)
        $latestRides = (clone $baseRides)
            ->select([
                'r.id','r.status','r.requested_at','r.accepted_at','r.finished_at','r.canceled_at',
                'r.origin_label','r.dest_label','r.total_amount','r.currency','r.payment_method',
                'd.name as driver_name',
                'v.economico as vehicle_economico','v.plate as vehicle_plate'
            ])
            ->orderByDesc('r.id')
            ->limit(8)
            ->get();

        // -----------------------------
        // ISSUES (vista básica)
        // -----------------------------
        $issues = DB::table('ride_issues as i')
            ->leftJoin('drivers as d', function ($j) use ($tenantId) {
                $j->on('d.id', '=', 'i.driver_id')->where('d.tenant_id', '=', $tenantId);
            })
            ->where('i.tenant_id', $tenantId)
            ->whereBetween('i.created_at', [$fromStr, $toStr])
            ->where('d.partner_id', $partnerId);

        $issuesOpen = (clone $issues)
            ->whereIn('i.status', ['open','pending','in_progress'])
            ->count();

        $latestIssues = (clone $issues)
            ->select('i.id','i.status','i.severity','i.category','i.title','i.created_at','i.ride_id','d.name as driver_name')
            ->orderByDesc('i.id')
            ->limit(6)
            ->get();

        return [
            'meta' => [
                'tz' => $tz,
                'now' => $now->format('Y-m-d H:i:s'),
                'from' => $fromStr,
                'to' => $toStr,
                'ping_fresh_sec' => $this->pingFreshSec,
            ],
            'kpi' => [
                'vehicles' => [
                    'total' => (int)($vehicles->total ?? 0),
                    'active' => (int)($vehicles->active ?? 0),
                    'verified' => (int)($vehicles->verified ?? 0),
                    'pending' => (int)($vehicles->pending ?? 0),
                ],
                'drivers' => [
                    'total' => (int)($drivers->total ?? 0),
                    'active' => (int)($drivers->active ?? 0),
                    'online' => (int)($drivers->online ?? 0),
                    'offline' => (int)($drivers->offline ?? 0),
                    'idle' => (int)($drivers->idle ?? 0),
                    'busy' => (int)($drivers->busy ?? 0),
                    'on_ride' => (int)($drivers->on_ride ?? 0),
                ],
                'wallet' => [
                    'balance' => (float)$walletBalance,
                    'currency' => $walletCurrency,
                    'unpaid_total' => (float)($charges->unpaid_total ?? 0),
                    'open_charges_total' => (float)($charges->amount_total ?? 0),
                ],
                'rides' => [
                    'total' => (int)$ridesTotal,
                    'active' => (int)$ridesActive,
                    'by_status' => $ridesByStatus,
                ],
                'issues' => [
                    'open' => (int)$issuesOpen,
                ],
            ],
            'latest' => [
                'rides' => $latestRides,
                'issues' => $latestIssues,
            ],
        ];
    }
}
