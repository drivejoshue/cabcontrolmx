<?php

// ============================
// 2) CONTROLLER (PUBLIC)
// app/Http/Controllers/Api/PublicAppConfigController.php
// ============================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PublicAppConfigController extends Controller
{
    public function show(Request $r)
    {
        $data = $r->validate([
            'app' => ['nullable', 'string', Rule::in(['passenger', 'driver'])],
        ]);

        $app = $data['app'] ?? 'passenger';

        // Cache corto para no pegar DB en cada arranque de app
        $payload = Cache::remember("appcfg:{$app}", 60, function () use ($app) {
            $row = DB::table('app_remote_config')->where('app', $app)->first();

            $min = (int)($row->min_version_code ?? 1);
            $latest = (int)($row->latest_version_code ?? $min);

            return [
                'ok' => true,
                'app' => $app,
                'min_version_code' => $min,
                'latest_version_code' => $latest,
                'force_update' => (bool)($row->force_update ?? false),
                'message' => $row->message ?: 'Hay una actualización disponible.',
                'play_url' => $row->play_url ?: null,
                'ts' => now()->toIso8601String(),
            ];
        });

        return response()->json($payload);
    }
}
