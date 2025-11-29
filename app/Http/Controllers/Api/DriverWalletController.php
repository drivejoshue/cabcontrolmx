<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverWallet;
use App\Models\DriverWalletMovement;
use Illuminate\Http\Request;

class DriverWalletController extends Controller
{
    /**
     * Devuelve el estado del wallet del driver.
     * Si no existe, lo crea en 0 para el tenant actual.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        /** @var Driver $driver */
        $driver = Driver::where('user_id', $user->id)->firstOrFail();

        // Suponemos que el driver tiene tenant_id en la tabla drivers
        $tenantId = $driver->tenant_id;

        /** @var DriverWallet $wallet */
        $wallet = DriverWallet::firstOrCreate(
            ['driver_id' => $driver->id],
            [
                'tenant_id'   => $tenantId,
                'balance'     => 0.00,
                'status'      => 'active',
                'min_balance' => 0.00,
            ]
        );

        return response()->json([
            'driver_id'   => $wallet->driver_id,
            'tenant_id'   => $wallet->tenant_id,
            'balance'     => (float) $wallet->balance,
            'status'      => $wallet->status,
            'min_balance' => (float) $wallet->min_balance,
        ]);
    }

    /**
     * Lista de movimientos del wallet del driver (paginado).
     */
    public function movements(Request $request)
    {
        $user = $request->user();

        /** @var Driver $driver */
        $driver = Driver::where('user_id', $user->id)->firstOrFail();

        $query = DriverWalletMovement::where('driver_id', $driver->id)
            ->orderByDesc('id');

        $perPage = min(100, (int) $request->input('per_page', 20));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
