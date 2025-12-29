<?php

// app/Support/PassengerGuard.php
namespace App\Support;

use App\Models\Passenger;
use Illuminate\Http\JsonResponse;

class PassengerGuard
{
  public static function findActiveByUid(string $uid): array
  {
    $p = Passenger::where('firebase_uid', $uid)->first();

    if (! $p) {
      return [null, response()->json(['ok'=>false,'msg'=>'Pasajero no encontrado.'], 404)];
    }

    if ($p->is_disabled) {
      return [null, response()->json([
        'ok'=>false,
        'code'=>'passenger_disabled',
        'msg'=>'La cuenta está deshabilitada.',
      ], 403)];
    }

    return [$p, null];
  }
}
