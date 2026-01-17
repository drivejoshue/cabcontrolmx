<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RideShareController extends Controller
{
    /**
     * GET /ride_share/{token}
     */
    public function show(Request $req, string $token)
    {
        $share = $this->getActiveOrViewableShare($token);
        if (!$share) {
            return response()->view('public.ride_share.invalid', [], 404);
        }

        // Auditoría ligera
        DB::table('ride_shares')->where('id', $share->id)->update([
            'last_viewed_at' => now(),
            'views_count'    => DB::raw('views_count + 1'),
            'updated_at'     => now(),
        ]);

        // Snapshot inicial para pintar el mapa “ya”
        $snapshot = $this->buildSnapshot((int)$share->tenant_id, (int)$share->ride_id);

        // Si el ride ya cerró, auto-end el share para que muera
       $ended = $this->autoEndIfRideClosed($share, $snapshot);
        if ($ended) {
            $share->status = 'ended';
        }


        return view('public.ride_share.show', [
            'token'    => $token,
            'share'    => $share,
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * GET /ride_share/{token}/state
     */
   public function state(Request $req, string $token)
{
    $share = $this->getActiveOrViewableShare($token);
    if (!$share) {
        return response()->json(['ok' => false, 'code' => 'NOT_FOUND'], 404);
    }

    // ✅ si ya no está activo, ya no es “stream”
    if (($share->status ?? null) !== 'active') {
        return response()->json([
            'ok' => false,
            'code' => strtoupper((string)$share->status), // ENDED/EXPIRED/REVOKED...
        ], 410);
    }

    // Expiración dura
    if ($share->expires_at && now()->greaterThan($share->expires_at)) {
        DB::table('ride_shares')->where('id', $share->id)->update([
            'status'     => 'expired',
            'updated_at' => now(),
        ]);
        return response()->json(['ok' => false, 'code' => 'EXPIRED'], 410);
    }

    $snapshot = $this->buildSnapshot((int)$share->tenant_id, (int)$share->ride_id);

    $ended = $this->autoEndIfRideClosed($share, $snapshot);

    // ✅ si ya terminó el ride, corta el stream
    if ($ended) {
        return response()->json([
            'ok'    => false,
            'code'  => 'ENDED',
            'ride'  => $snapshot['ride'] ?? null,
            'ts'    => now()->format('Y-m-d H:i:s'),
        ], 410);
    }

    return response()->json([
        'ok'      => true,
        'ended'   => false,
        'share'   => [
            'status'     => 'active',
            'expires_at' => $share->expires_at ? (string)$share->expires_at : null,
        ],
        'ride'    => $snapshot['ride'] ?? null,
        'driver'  => $snapshot['driver'] ?? null,
        'vehicle' => $snapshot['vehicle'] ?? null,
        'location'=> $snapshot['location'] ?? null,
        'ts'      => now()->format('Y-m-d H:i:s'),
    ]);
}


    private function getActiveOrViewableShare(string $token): ?object
    {
        $row = DB::table('ride_shares')
            ->where('token', $token)
            ->first();

        if (!$row) return null;

        // Permitimos ver ended/revoked pero la UI mostrará “cerrado”
        return $row;
    }

  private function buildSnapshot(int $tenantId, int $rideId): array
{
    $ride = DB::table('rides')
        ->where('tenant_id', $tenantId)
        ->where('id', $rideId)
        ->first([
            'id','tenant_id','status','driver_id',
            'origin_label','origin_lat','origin_lng',
            'dest_label','dest_lat','dest_lng',
            'canceled_by','cancel_reason',
            'created_at','updated_at',
        ]);

    if (!$ride) return ['ride' => null];

    $status = strtolower((string)$ride->status);
    $isClosed = in_array($status, ['finished','canceled','completed','ended'], true);

    $driver = null;
    $vehicle = null;
    $location = null;

    if (!empty($ride->driver_id)) {
        $driver = DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $ride->driver_id)
            ->first(['id','name','foto_path','last_seen_at','last_bearing','last_speed','last_lat','last_lng']);

        $assign = DB::table('driver_vehicle_assignments')
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $ride->driver_id)
            ->whereNull('end_at')
            ->orderByDesc('id')
            ->first(['vehicle_id']);

        if ($assign && !empty($assign->vehicle_id)) {
            $vehicle = DB::table('vehicles')
                ->where('tenant_id', $tenantId)
                ->where('id', $assign->vehicle_id)
                ->first(['id','economico','plate','brand','model','color','year','photo_url','foto_path']);
        }

        // ✅ SOLO si el ride NO está cerrado
        if (!$isClosed) {
            $dl = DB::table('driver_locations')
                ->where('tenant_id', $tenantId)
                ->where('driver_id', $ride->driver_id)
                ->orderByDesc('id')
                ->first(['lat','lng','speed_kmh','bearing','reported_at','created_at']);

            if ($dl) {
                $location = [
                    'lat'         => (float)$dl->lat,
                    'lng'         => (float)$dl->lng,
                    'speed_kmh'   => $dl->speed_kmh !== null ? (float)$dl->speed_kmh : null,
                    'bearing'     => $dl->bearing !== null ? (float)$dl->bearing : null,
                    'reported_at' => $dl->reported_at ? (string)$dl->reported_at : null,
                ];
            }
        }
    }
}
   


    private function autoEndIfRideClosed(object $share, array $snapshot): bool
    {
        $ride = $snapshot['ride'] ?? null;
        if (!$ride) return false;

        $status = strtolower((string)($ride['status'] ?? ''));

        $isClosed = in_array($status, ['finished','canceled'], true);
        if (!$isClosed) return false;

        if ($share->status === 'active') {
            DB::table('ride_shares')->where('id', $share->id)->update([
                'status'     => 'ended',
                'ended_at'   => now(),
                'updated_at' => now(),
            ]);
        }

        return true;
    }

    private function maskPlate(string $plate): string
    {
        $p = trim($plate);
        if ($p === '') return '';
        $len = mb_strlen($p);
        if ($len <= 3) return str_repeat('*', $len);
        return str_repeat('*', max(0, $len - 3)) . mb_substr($p, -3);
    }

    private function maskPersonName(string $name): string
    {
        $n = trim(preg_replace('/\s+/', ' ', $name));
        if ($n === '') return '';
        $parts = explode(' ', $n);
        // "Juan P."
        $first = $parts[0] ?? '';
        $last  = $parts[1] ?? '';
        return $last !== '' ? ($first . ' ' . mb_substr($last, 0, 1) . '.') : $first;
    }
}
