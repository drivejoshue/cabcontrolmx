<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaxiStandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TaxiStandController extends Controller
{
    /**
     * Determinar tenant con estas reglas:
     *  - Si hay header X-Tenant-ID o ?tenant_id, se usa ese (para Dispatch/sysadmin).
     *  - Si no, se usa el tenant del usuario autenticado.
     *  - Si no hay ninguno → 403.
     */
    private function resolveTenantId(Request $req): int
    {
        $fromHeader = $req->header('X-Tenant-ID') ?? $req->query('tenant_id');

        if (!empty($fromHeader)) {
            $tid = (int) $fromHeader;
            Log::debug('TAXI_STAND_TENANT_RESOLVED_FROM_HEADER', [
                'tenant_id'     => $tid,
                'header_tenant' => $req->header('X-Tenant-ID'),
                'query_tenant'  => $req->query('tenant_id'),
            ]);
            return $tid;
        }

        $user = Auth::user();
        if (!$user || !$user->tenant_id) {
            abort(403, 'Sin tenant asignado');
        }

        return (int) $user->tenant_id;
    }

    /**
     * Resolver driver_id desde el token (user->drivers.user_id)
     */
    private function resolveDriverId(): int
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'No autenticado');
        }

        $driverId = \DB::table('drivers')
            ->where('user_id', $user->id)
            ->value('id');

        if (!$driverId) {
            abort(403, 'Driver no asociado al token');
        }

        return (int) $driverId;
    }

    /**
     * GET /api/taxistands
     * Lista de bases + queue_count (para badge).
     */
public function index(Request $request)
{
    $tenantId = $this->resolveTenantId($request);

    $stands = TaxiStandService::listForDriver($tenantId);

    // 👇 devolvemos directamente el arreglo, SIN wrapper { ok, stands }
    return response()->json($stands);
}

    /**
     * POST /api/stands/join  { stand_id: X }
     */
    public function join(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        $driverId = $this->resolveDriverId();

        $data = $request->validate([
            'stand_id' => 'required|integer',
        ]);

        $res = TaxiStandService::joinStandById($tenantId, $driverId, (int) $data['stand_id']);

        return response()->json($res);
    }


    
public function joinByCode(Request $request)
{
    $tenantId = $this->resolveTenantId($request);
    $driverId = $this->resolveDriverId();

    $data = $request->validate([
        'code' => 'required|string',
    ]);

    $raw = trim($data['code']);
    $code = $this->extractCodeFromQr($raw);

    // Buscar la base por qr_secret O por codigo
    $stand = DB::table('taxi_stands')
        ->where('tenant_id', $tenantId)
        ->where('activo', 1)
        ->where(function ($q) use ($code) {
            $q->where('qr_secret', $code)
              ->orWhere('codigo', $code);
        })
        ->first();

    if (!$stand) {
        return response()->json([
            'ok'      => false,
            'message' => 'Código de base no válido',
        ], 404);
    }

    $res = TaxiStandService::joinStandById($tenantId, $driverId, (int) $stand->id);

    return response()->json($res, $res['ok'] ? 200 : 422);
}

/**
 * Permite que el QR sea solo el token, o una URL con ?code= / ?qr= / ?s=
 */
private function extractCodeFromQr(string $raw): string
{
    $raw = trim($raw);

    if (str_contains($raw, '://')) {
        $parts = parse_url($raw);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
            foreach (['code', 'qr', 's'] as $key) {
                if (!empty($qs[$key])) {
                    return trim($qs[$key]);
                }
            }
        }
    }

    return $raw;
}

public function leave(Request $request)
{
    $tenantId = $this->resolveTenantId($request);
    $driverId = $this->resolveDriverId(); // ← CAMBIA ESTO

      Log::debug('TAXI_STAND_LEAVE_CALLED', [
        'tenant_id' => $tenantId,
        'driver_id' => $driverId,
        'user_id' => $request->user()->id
    ]); 

    $data = $request->validate([
        'stand_id'  => 'required|integer',
        'status_to' => 'nullable|string|in:salio,descanso',
    ]);

    $statusTo = $data['status_to'] ?? 'salio';

    $res = TaxiStandService::leaveStand($tenantId, $driverId, $data['stand_id'], $statusTo);

    return response()->json($res, $res['ok'] ? 200 : 422);
}


    /**
     * GET /api/stands/status  [stand_id?]
     *
     * Aquí se hace:
     *  - autoLeaveIfFar (force-leave si ya se alejó)
     *  - cálculo de my_position, ahead_count, queue_count
     *  - queue[] con economico y plate desde vehicles
     */
    public function status(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        $driverId = $this->resolveDriverId();

        $standId = $request->query('stand_id');
        $standId = $standId ? (int) $standId : null;

        $res = TaxiStandService::status($tenantId, $driverId, $standId);

        return response()->json($res);
    }
}
