<?php
// app/Http/Controllers/Admin/TenantQrPointController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantQrPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantQrPointController extends Controller
{
    /**
     * Ajusta este método a tu forma real de resolver tenant actual.
     * Ejemplos comunes:
     * - auth()->user()->tenant_id
     * - app('tenant')->id
     * - session('tenant_id')
     */
    private function tenantId(): int
    {
        $tid = (int) (auth()->user()->tenant_id ?? 0);
        abort_if($tid <= 0, 403, 'Tenant no resuelto.');
        return $tid;
    }

    public function index(Request $r)
    {
        $tenantId = $this->tenantId();

        $q = TenantQrPoint::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id');

        if ($s = trim((string)$r->get('q'))) {
            $q->where(function($w) use ($s){
                $w->where('name','like',"%{$s}%")
                  ->orWhere('address_text','like',"%{$s}%")
                  ->orWhere('code','like',"%{$s}%");
            });
        }

        $items = $q->paginate(20)->withQueryString();

        return view('admin.qr_points.index', [
            'items' => $items,
            'q' => $r->get('q',''),
        ]);
    }

    public function create()
    {
        return view('admin.qr_points.create');
    }

    public function store(Request $r)
    {
        $tenantId = $this->tenantId();

        $data = $r->validate([
            'name'         => ['required','string','max:120'],
            'address_text' => ['nullable','string','max:255'],
            'lat'          => ['required','numeric','between:-90,90'],
            'lng'          => ['required','numeric','between:-180,180'],
            'active'       => ['nullable','boolean'],
        ]);

        $data['tenant_id'] = $tenantId;
        $data['active'] = (bool)($data['active'] ?? true);
        $data['code'] = $this->generateUniqueCode();

        TenantQrPoint::create($data);

        return redirect()
            ->route('admin.qr-points.index')
            ->with('success', 'QR Point creado correctamente.');
    }

    public function edit(TenantQrPoint $qrPoint)
    {
        $tenantId = $this->tenantId();
        abort_if($qrPoint->tenant_id !== $tenantId, 404);

        return view('admin.qr_points.edit', [
            'item' => $qrPoint,
        ]);
    }

    public function update(Request $r, TenantQrPoint $qrPoint)
    {
        $tenantId = $this->tenantId();
        abort_if($qrPoint->tenant_id !== $tenantId, 404);

        $data = $r->validate([
            'name'         => ['required','string','max:120'],
            'address_text' => ['nullable','string','max:255'],
            'lat'          => ['required','numeric','between:-90,90'],
            'lng'          => ['required','numeric','between:-180,180'],
            'active'       => ['nullable','boolean'],
        ]);

        $data['active'] = (bool)($data['active'] ?? false);

        $qrPoint->update($data);

        return redirect()
            ->route('admin.qr-points.index')
            ->with('success', 'QR Point actualizado.');
    }

    public function destroy(TenantQrPoint $qrPoint)
    {
        $tenantId = $this->tenantId();
        abort_if($qrPoint->tenant_id !== $tenantId, 404);

        $qrPoint->delete();

        return redirect()
            ->route('admin.qr-points.index')
            ->with('success', 'QR Point eliminado.');
    }

    /**
     * Genera un code corto, URL-safe y UNIQUE.
     * 10 chars base32-ish: suficiente para millones con colisiones muy bajas.
     */
    private function generateUniqueCode(): string
    {
        for ($i=0; $i<12; $i++) {
            // Ejemplo: ORB + 8 chars => 11 total. Ajusta a gusto.
            $code = 'QR' . strtoupper(Str::random(10)); // alfanumérico
            $code = preg_replace('/[^A-Z0-9]/', 'X', $code);

            $exists = TenantQrPoint::where('code', $code)->exists();
            if (!$exists) return $code;
        }

        abort(500, 'No fue posible generar un código único. Intenta de nuevo.');
    }


    public function show(TenantQrPoint $qrPoint)
{
    $tenantId = $this->tenantId();
    abort_if($qrPoint->tenant_id !== $tenantId, 404);

    return view('admin.qr_points.show', [
        'item' => $qrPoint,
    ]);
}



}
