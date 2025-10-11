<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    public function accept($offer)
    {
        DB::statement('CALL sp_accept_offer_v3(?)', [(int)$offer]);
        return response()->json(['ok'=>true]);
    }

    public function reject($offer, Request $req)
    {
        // (opcional) validar que esta oferta pertenece al driver autenticado
        DB::statement('CALL sp_reject_offer_v2(?)', [(int)$offer]);
        return response()->json(['ok'=>true]);
    }
}
