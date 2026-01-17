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

private function buildSnapshot($share, $ride, $driver = null, $vehicle = null, $dl = null): array
{
    try {
        // Si por cualquier razón no hay ride/share, devuelve snapshot “ended”
        if (!$share || !$ride) {
            return [
                'ok' => false,
                'ended' => true,
                'code' => 'ENDED',
                'ride' => null,
                'driver' => null,
                'vehicle' => null,
                'location' => null,
                'ts' => now()->toIso8601String(),
            ];
        }

        $stLower = strtolower((string)($ride->status ?? ''));
        $ended = in_array($stLower, ['finished','canceled'], true);

        // Construye location (tu bloque actual)
        $location = null;
        if ($dl) {
            $location = [
                'lat' => $dl->lat,
                'lng' => $dl->lng,
                'bearing' => $dl->bearing,
                'reported_at' => $dl->reported_at ? (string)$dl->reported_at : null,
            ];
        }

        // IMPORTANTE: devuelve SIEMPRE
        return [
            'ok' => !$ended,          // si prefieres ok=true aun cuando ended, cámbialo
            'ended' => $ended,
            'code' => $ended ? $stLower : null,
            'ride' => [
                'id' => $ride->id,
                'status' => $ride->status,
                'origin' => $ride->origin ?? null,
                'destination' => $ride->destination ?? null,
            ],
            'driver' => $driver ? [
                'id' => $driver->id,
                'name' => $driver->name,
            ] : null,
            'vehicle' => $vehicle ? [
                'brand' => $vehicle->brand ?? null,
                'model' => $vehicle->model ?? null,
                'color' => $vehicle->color ?? null,
                'plate' => $vehicle->plate ?? null,
            ] : null,
            'location' => $location,
            'ts' => now()->toIso8601String(),
        ];

    } catch (\Throwable $e) {
        // Nunca dejes que reviente la vista pública
        report($e);

        return [
            'ok' => false,
            'ended' => true,
            'code' => 'ERROR',
            'ride' => null,
            'driver' => null,
            'vehicle' => null,
            'location' => null,
            'ts' => now()->toIso8601String(),
        ];
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
