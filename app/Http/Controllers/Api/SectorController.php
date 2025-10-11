<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Illuminate\Http\Request;

class SectorController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id ?? null;

        $rows = Sector::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('activo', 1)
            ->get(['id','nombre','area']);

        $features = [];

        foreach ($rows as $s) {
            $geo = $s->area;

            // Acepta string JSON o array/objeto
            if (is_string($geo)) {
                $geo = json_decode($geo, true);
            } elseif (is_object($geo)) {
                $geo = json_decode(json_encode($geo), true);
            }

            if (!$geo || !is_array($geo)) {
                continue; // inválido
            }

            // Acepta Feature, Polygon o MultiPolygon
            $type = $geo['type'] ?? null;

            if (in_array($type, ['Polygon','MultiPolygon'], true)) {
                $features[] = [
                    'type'       => 'Feature',
                    'geometry'   => $geo,
                    'properties' => [
                        'id'     => $s->id,
                        'nombre' => $s->nombre,
                    ],
                ];
            } elseif ($type === 'Feature' && !empty($geo['geometry'])) {
                $features[] = [
                    'type'       => 'Feature',
                    'geometry'   => $geo['geometry'],
                    'properties' => array_merge([
                        'id'     => $s->id,
                        'nombre' => $s->nombre,
                    ], $geo['properties'] ?? []),
                ];
            }
        }

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
