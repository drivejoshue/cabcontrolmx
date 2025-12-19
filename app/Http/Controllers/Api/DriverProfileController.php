<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class DriverProfileController extends Controller
{
    /**
     * GET /api/driver/profile
     * Devuelve el perfil + stats para DriverProfileShowResponse
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Driver ligado al usuario
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json(['message' => 'Driver no encontrado'], 404);
        }

        $tenantId = $user->tenant_id ?? $driver->tenant_id;
        $tz       = config('app.timezone'); // si ya tienes timezone por tenant, cámbialo aquí

        // --- Foto ---
        $fotoUrl = $driver->foto_path
            ? Storage::disk('public')->url($driver->foto_path)
            : null;

        // --- Stats de rating desde la vista driver_ratings_summary ---
        $summary = DB::table('driver_ratings_summary')
            ->where('driver_id', $driver->id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();

        $ratingAvg   = $summary->average_rating  ?? null;
        $ratingCount = $summary->total_ratings   ?? 0;

        // --- Stats de viajes desde rides ---
        $completedTrips = DB::table('rides')
            ->where('driver_id', $driver->id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'finished')
            ->count();

        $today = now($tz)->toDateString();

        $todayTrips = DB::table('rides')
            ->where('driver_id', $driver->id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'finished')
            ->whereDate('finished_at', $today)
            ->count();

        $todayEarnings = DB::table('rides')
            ->where('driver_id', $driver->id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'finished')
            ->whereDate('finished_at', $today)
            ->sum('total_amount');

        $monthStart = now($tz)->startOfMonth();
        $monthEnd   = now($tz)->endOfMonth();

        $monthEarnings = DB::table('rides')
            ->where('driver_id', $driver->id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'finished')
            ->whereBetween('finished_at', [$monthStart, $monthEnd])
            ->sum('total_amount');

        // --- RESPUESTA ---
        return response()->json([
            'id'          => $driver->id,
            'name'        => $driver->name ?? $user->name,
            'phone'       => $driver->phone,
            'email'       => $user->email,
            'status'      => $driver->status,
            'active'      => (bool) $driver->active,
            'foto_url'    => $fotoUrl,
            'profile_bio' => $driver->profile_bio,

            'stats' => [
                'rating_avg'      => $ratingAvg,
                'rating_count'    => (int) $ratingCount,
                'completed_trips' => (int) $completedTrips,
                'today_trips'     => (int) $todayTrips,
                'today_earnings'  => (float) $todayEarnings,
                'month_earnings'  => (float) $monthEarnings,
                'currency'        => 'MXN',
            ],
        ]);
    }

    /**
     * POST /api/driver/profile
     * Actualiza teléfono / bio. Respuesta tipo OkResponse
     */
    public function update(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        $driver = Driver::where('user_id', $user->id)->first();
        if (!$driver) {
            return response()->json(['ok' => false, 'message' => 'Driver no encontrado'], 404);
        }

        $data = $request->validate([
            'phone'       => ['nullable', 'string', 'max:50'],
            'profile_bio' => ['nullable', 'string', 'max:500'],
        ]);

        if (array_key_exists('phone', $data)) {
            $driver->phone = $data['phone'];
        }
        if (array_key_exists('profile_bio', $data)) {
            $driver->profile_bio = $data['profile_bio'];
        }

        $driver->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Perfil actualizado correctamente',
        ]);
    }

    /**
     * POST /api/driver/profile/photo
     * Sube selfie del chofer
     */
    public function updatePhoto(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        $driver = Driver::where('user_id', $user->id)->first();
        if (!$driver) {
            return response()->json(['ok' => false, 'message' => 'Driver no encontrado'], 404);
        }

        // Sólo validamos; el archivo real se lee con file()
        $request->validate([
            'foto' => ['required', 'image', 'max:4096'], // ~4MB
        ]);

        /** @var UploadedFile|null $file */
        $file = $request->file('foto');
        if (!$file) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se recibió la imagen',
            ], 422);
        }

        // Borrar foto anterior si existe
        if ($driver->foto_path) {
            Storage::disk('public')->delete($driver->foto_path);
        }

        // Guardar nueva
        $path = $file->store('drivers', 'public');
        $driver->foto_path = $path;
        $driver->save();

        return response()->json([
            'ok'       => true,
            'message'  => 'Foto actualizada',
            'foto_url' => Storage::disk('public')->url($path),
        ]);
    }
}
