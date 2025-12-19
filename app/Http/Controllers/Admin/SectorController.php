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
        return view('admin.sectores.create');
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
            $feature = $this->normalizeGeoFeature($geo); // guardamos Feature completo
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

        DB::update("
  UPDATE sectores
  SET area_geom = ST_GeomFromText(
                    ST_AsText(
                      ST_GeomFromGeoJSON(JSON_EXTRACT(area, '$.geometry'))
                    ), 4326
                  )
  WHERE id = ? AND tenant_id = ?
", [$id, $tenantId]);

        return redirect()->route('sectores.show', $id)->with('ok','Sector creado correctamente.');
    }

    public function show(int $id)
    {
        $tenantId = $this->tenantId();

        $sector = DB::table('sectores')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$sector, 404);

        return view('admin.sectores.show', compact('sector'));
    }

    public function edit(int $id)
    {
        $tenantId = $this->tenantId();

        $sector = DB::table('sectores')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->first();

        abort_if(!$sector, 404);

        return view('admin.sectores.edit', compact('sector'));
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
  SET area_geom = ST_GeomFromText(
                    ST_AsText(
                      ST_GeomFromGeoJSON(JSON_EXTRACT(area, '$.geometry'))
                    ), 4326
                  )
  WHERE id = ? AND tenant_id = ?
", [$id, $tenantId]);

        return redirect()->route('sectores.show', $id)->with('ok','Sector actualizado.');
    }

    /** Desactivar (NO borrar físico) */
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

    /**
     * Devuelve FeatureCollection de TODOS los sectores del tenant (solo lectura)
     * con geometry extraída del campo 'area' (que guardamos como Feature).
     */
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

            // extrae geometry si está guardado como Feature; acepta Polygon/MultiPolygon también
            $geom = $this->extractGeometry($area);
            if (!$geom) {
                continue; // ignora registros corruptos
            }

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

    /** Acepta geometry (Polygon/MultiPolygon), Feature o FeatureCollection y devuelve un Feature */
    private function normalizeGeoFeature(array $geo): array
    {
        // Si ya es Feature y tiene geometry válida => úsalo tal cual
        if (($geo['type'] ?? null) === 'Feature') {
            if (!isset($geo['geometry']['type'])) {
                throw new \InvalidArgumentException('Feature sin geometry');
            }
            return $geo;
        }

        // Si llega Polygon/MultiPolygon => envolver en Feature
        if (in_array($geo['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            return [
                'type'       => 'Feature',
                'geometry'   => $geo,
                'properties' => new \stdClass(), // json_encode lo maneja perfecto
            ];
        }

        // Si llega FeatureCollection, tomar el primer Feature válido
        if (($geo['type'] ?? null) === 'FeatureCollection') {
            $first = $geo['features'][0] ?? null;
            if (!$first || ($first['type'] ?? null) !== 'Feature' || !isset($first['geometry'])) {
                throw new \InvalidArgumentException('FeatureCollection inválido');
            }
            return $first;
        }

        throw new \InvalidArgumentException('GeoJSON debe ser Feature o Polygon/MultiPolygon');
    }

    /** Devuelve solo la geometry a partir de Feature/FeatureCollection/geometry */
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
