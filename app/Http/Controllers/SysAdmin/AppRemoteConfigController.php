<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppRemoteConfigController extends Controller
{
    public function index()
    {
        $rows = DB::table('app_remote_config')
            ->whereIn('app', ['passenger','driver'])
            ->get()
            ->keyBy('app');

        // defaults por si no existe la fila
        $cfgPassenger = $rows->get('passenger') ?: (object)[
            'app' => 'passenger',
            'min_version_code' => 1,
            'latest_version_code' => 1,
            'force_update' => 0,
            'message' => 'Hay una actualización disponible.',
            'play_url' => 'https://play.google.com/store/apps/details?id=com.orbana.passenger',
        ];

        $cfgDriver = $rows->get('driver') ?: (object)[
            'app' => 'driver',
            'min_version_code' => 1,
            'latest_version_code' => 1,
            'force_update' => 0,
            'message' => 'Hay una actualización disponible.',
            'play_url' => 'https://play.google.com/store/apps/details?id=com.orbana.driver',
        ];

        return view('sysadmin.app_config.index', [
            'cfgPassenger' => $cfgPassenger,
            'cfgDriver' => $cfgDriver,
        ]);
    }

    public function update(Request $r)
    {
        $data = $r->validate([
            'app' => ['required', 'string', Rule::in(['passenger','driver'])],
            'min_version_code' => ['required', 'integer', 'min:1'],
            'latest_version_code' => ['nullable', 'integer', 'min:1'],
            'force_update' => ['nullable', 'boolean'],
            'message' => ['nullable', 'string', 'max:255'],
            'play_url' => ['nullable', 'string', 'max:255'],
        ]);

        $app = $data['app'];
        $min = (int)$data['min_version_code'];
        $latest = (int)($data['latest_version_code'] ?? $min);
        $force = (bool)($data['force_update'] ?? false);

        DB::table('app_remote_config')->updateOrInsert(
            ['app' => $app],
            [
                'min_version_code' => $min,
                'latest_version_code' => $latest,
                'force_update' => $force ? 1 : 0,
                'message' => $data['message'] ?? 'Hay una actualización disponible.',
                'play_url' => $data['play_url'] ?? null,
                'updated_at' => now(),
                'created_at' => now(), // updateOrInsert requiere ambos si no existe
            ]
        );

        // Limpia cache del endpoint público
        Cache::forget("appcfg:{$app}");

        return back()->with('success', "Config actualizada: {$app} (min={$min}, latest={$latest}, force=".($force?'1':'0').")");
    }
}
