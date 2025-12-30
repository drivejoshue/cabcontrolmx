<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SectorController extends Controller
{
    private function tenantId(): int
    {
        $tid = Auth::user()->tenant_id ?? null;
        if (!$tid) abort(403, 'Usuario sin tenant asignado');
        return (int) $tid;
    }

    /** NUEVO: lee lat/lng/radio desde tenants para centrar mapas */
    private function tenantLoc(int $tenantId): ?array
    {
        $t = DB::table('tenants')
            ->select('latitud','longitud','coverage_radius_km','timezone','utc_offset_minutes')
            ->where('id', $tenantId)
            ->first();

        if (!$t || $t->latitud === null || $t->longitud === null) {
            return null;
        }

        return [
            'latitud'             => (float) $t->latitud,
            'longitud'            => (float) $t->longitud,
            'coverage_radius_km'  => (float) ($t->coverage_radius_km ?? 12),
            'timezone'            => $t->timezone ?? 'America/Mexico_City',
            'utc_offset_minutes'  => $t->utc_offset_minutes,
        ];
    }

    public function index(Request $request)
    {
        $tenantId = $this->tenantId();

        $sectores = DB::table('sectores')
            ->select('id','nombre','activo','created_at','updated_at')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.sectores.index', compact('sectores'));
    }

    public function create()
    {
        $tenantId  = $this->tenantId();
        $tenantLoc = $this->tenantLoc($tenantId);

        // La vista usará window.__TENANT_LOC__ = @json($tenantLoc)
        return view('admin.sectores.create', compact('tenantLoc'));
    }

    public function store(Request $request)
    {
        $tenantId = $this->tenantId();

        $data = $request->validate([
            'nombre' => 'required|string|max:120',
            'area'   => 'required|string',
            'activo' => 'nullable|boolean',
        ]);

        $geo = json_decode($data['area'], true);
        if (!$geo || !is_array($geo)) {
            return back()->withErrors(['area' => 'GeoJSON inválido'])->withInput();
        }

        try {
            $feature = $this->normalizeGeoFeature($geo);
        } catch (\Throwable $e) {
            return back()->withErrors(['area' => $e->getMessage()])->withInput();
        }

        $id = DB::table('sectores')->insertGetId([
            'tenant_id'  => $tenantId,
            'nombre'     => $data['nombre'],
            'area'       => json_encode($feature, JSON_UNESCAPED_UNICODE),
            'activo'     => (int)($data['activo'] ?? 1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Actualiza columna espacial (MySQL 8+)
        DB::update("
            UPDATE sectores
               SET area_geom = ST_GeomFromGeoJSON(JSON_EXTRACT(area, '$.geometry'))
             WHERE id = ? AND tenant_id = ?
        ", [$id, $tenantId]);

        return redirect()->route('sectores.show', $id)->with('ok','Sector creado correctamente.');
    }

    public function show(int $id)
    {
        $tenantId  = $this->tenantId();
        $tenantLoc = $this->tenantLoc($tenantId);

        $sector = DB::table('sectores')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$sector, 404);

        return view('admin.sectores.show', compact('sector','tenantLoc'));
    }

    public function edit(int $id)
    {
        $tenantId  = $this->tenantId();
        $tenantLoc = $this->tenantLoc($tenantId);

        $sector = DB::table('sectores')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$sector, 404);

        return view('admin.sectores.edit', compact('sector','tenantLoc'));
    }

    public function update(Request $request, int $id)
    {
        $tenantId = $this->tenantId();

        $data = $request->validate([
            'nombre' => 'required|string|max:120',
            'area'   => 'required|string',
            'activo' => 'nullable|boolean',
        ]);

        $geo = json_decode($data['area'], true);
        if (!$geo || !is_array($geo)) {
            return back()->withErrors(['area' => 'GeoJSON inválido'])->withInput();
        }

        try {
            $feature = $this->normalizeGeoFeature($geo);
        } catch (\Throwable $e) {
            return back()->withErrors(['area' => $e->getMessage()])->withInput();
        }

        DB::table('sectores')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
                'nombre'     => $data['nombre'],
                'area'       => json_encode($feature, JSON_UNESCAPED_UNICODE),
                'activo'     => (int)($data['activo'] ?? 1),
                'updated_at' => now(),
            ]);

        DB::update("
            UPDATE sectores
               SET area_geom = ST_GeomFromGeoJSON(JSON_EXTRACT(area, '$.geometry'))
             WHERE id = ? AND tenant_id = ?
        ", [$id, $tenantId]);

        return redirect()->route('sectores.show', $id)->with('ok','Sector actualizado.');
    }

    public function destroy(int $id)
    {
        $tenantId = $this->tenantId();

        DB::table('sectores')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->update([
                'activo'     => 0,
                'updated_at' => now(),
            ]);

        return redirect()->route('sectores.index')->with('ok','Sector desactivado (no se elimina físicamente).');
    }

    /** GeoJSON de sectores del tenant */
    public function geojson(Request $request)
    {
        $tenantId = $this->tenantId();

        $rows = DB::table('sectores')
            ->select('id','nombre','area')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('area')
            ->get();

        $features = [];

        foreach ($rows as $s) {
            $area = is_string($s->area) ? json_decode($s->area, true) : $s->area;
            $geom = $this->extractGeometry($area);
            if (!$geom) continue;

            $features[] = [
                'type'       => 'Feature',
                'geometry'   => $geom,
                'properties' => [
                    'id'     => $s->id,
                    'nombre' => $s->nombre,
                ],
            ];
        }

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    // ===================== Helpers =====================

    private function normalizeGeoFeature(array $geo): array
    {
        if (($geo['type'] ?? null) === 'Feature') {
            if (!isset($geo['geometry']['type'])) {
                throw new \InvalidArgumentException('Feature sin geometry');
            }
            return $geo;
        }

        if (in_array($geo['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            return [
                'type'       => 'Feature',
                'geometry'   => $geo,
                'properties' => new \stdClass(),
            ];
        }

        if (($geo['type'] ?? null) === 'FeatureCollection') {
            $first = $geo['features'][0] ?? null;
            if (!$first || ($first['type'] ?? null) !== 'Feature' || !isset($first['geometry'])) {
                throw new \InvalidArgumentException('FeatureCollection inválido');
            }
            return $first;
        }

        throw new \InvalidArgumentException('GeoJSON debe ser Feature o Polygon/MultiPolygon');
    }

    private function extractGeometry($area): ?array
    {
        if (!is_array($area)) return null;

        $type = $area['type'] ?? null;

        if ($type === 'Feature') {
            return $area['geometry'] ?? null;
        }

        if (in_array($type, ['Polygon','MultiPolygon'], true)) {
            return $area;
        }

        if ($type === 'FeatureCollection') {
            $first = $area['features'][0] ?? null;
            if ($first && ($first['type'] ?? null) === 'Feature' && isset($first['geometry'])) {
                return $first['geometry'];
            }
        }

        return null;
    }
}
