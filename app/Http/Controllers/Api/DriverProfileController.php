<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DriverProfileController extends Controller
{
    /**
     * Devuelve el perfil del driver autenticado
     * (datos básicos + info de cobro: CLABE, banco, etc.).
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Asumimos que el User tiene relación hasOne Driver
        /** @var Driver $driver */
        $driver = Driver::where('user_id', $user->id)->firstOrFail();

        $fotoUrl = null;
        if ($driver->foto_path) {
            // Ajusta disk según tu config (public, s3, etc.)
            $fotoUrl = Storage::disk('public')->url($driver->foto_path);
        }

        return response()->json([
            'id'            => $driver->id,
            'name'          => $driver->name,
            'phone'         => $driver->phone,
            'email'         => $driver->email,
            'status'        => $driver->status,
            'active'        => (bool) $driver->active,
            'foto_url'      => $fotoUrl,

            // Mini bio
            'profile_bio'   => $driver->profile_bio,

            // Datos de transferencia
            'payout' => [
                'bank'           => $driver->payout_bank,
                'account_name'   => $driver->payout_account_name,
                'account_number' => $driver->payout_account_number,
                'clabe'          => $driver->payout_clabe,
                'notes'          => $driver->payout_notes,
            ],
        ]);
    }

    /**
     * Actualiza datos editables del perfil del driver
     * (bio y datos de transferencia).
     * La foto de perfil la podemos manejar en otro endpoint si quieres.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        /** @var Driver $driver */
        $driver = Driver::where('user_id', $user->id)->firstOrFail();

        $data = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'phone'               => 'sometimes|string|max:50',
            'profile_bio'         => 'sometimes|nullable|string|max:255',

            'payout_bank'         => 'sometimes|nullable|string|max:80',
            'payout_account_name' => 'sometimes|nullable|string|max:120',
            'payout_account_number' => 'sometimes|nullable|string|max:60',
            'payout_clabe'        => 'sometimes|nullable|string|max:20',
            'payout_notes'        => 'sometimes|nullable|string|max:255',
        ]);

        // Campos básicos
        if (array_key_exists('name', $data)) {
            $driver->name = $data['name'];
        }
        if (array_key_exists('phone', $data)) {
            $driver->phone = $data['phone'];
        }
        if (array_key_exists('profile_bio', $data)) {
            $driver->profile_bio = $data['profile_bio'];
        }

        // Payout
        foreach ([
            'payout_bank',
            'payout_account_name',
            'payout_account_number',
            'payout_clabe',
            'payout_notes',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $driver->{$field} = $data[$field];
            }
        }

        $driver->save();

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
        ]);
    }
}
