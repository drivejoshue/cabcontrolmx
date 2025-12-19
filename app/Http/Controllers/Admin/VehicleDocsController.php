<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VehicleDocsController extends Controller
{
    private function tenantId(): int
    {
        $tid = Auth::user()->tenant_id ?? null;
        if (!$tid) abort(403, 'Usuario sin tenant asignado');
        return (int)$tid;
    }

private function allowedTypes(): array
{
    return [
        'foto_vehiculo'              => 'Foto del vehículo',
        'placas'                     => 'Foto de placas',
        'cedula_transporte_publico'  => 'Cédula / Transporte público (Taxi)',
        'tarjeta_circulacion'        => 'Tarjeta de circulación',
        // opcionales:
        'seguro'                     => 'Póliza (opcional)',
    ];
}


    public function index(int $id)
{
    $tenantId = $this->tenantId();

    $vehicle = DB::table('vehicles')
        ->where('tenant_id',$tenantId)
        ->where('id',$id)
        ->first();
    abort_if(!$vehicle, 404);

    $docs = DB::table('vehicle_documents')
        ->where('tenant_id',$tenantId)
        ->where('vehicle_id',$id)
        ->orderByDesc('id')
        ->get();

    // 🔹 Requeridos base: foto, placas, cédula taxi, tarjeta de circulación
    $required = [
        'foto_vehiculo',
        'placas',
        'cedula_transporte_publico',
        'tarjeta_circulacion',
    ];

    $types = $this->allowedTypes();

    // docs aprobados por tipo (último aprobado)
    $approvedByType = collect($docs)
        ->where('status','approved')
        ->groupBy('type')
        ->map(fn($g)=>$g->sortByDesc('id')->first());

    $requiredOk = collect($required)->every(fn($t)=>isset($approvedByType[$t]));

    return view('admin.vehicles.documents', [
        'vehicle'        => $vehicle,
        'docs'           => $docs,
        'types'          => $types,
        'required'       => $required,
        'approvedByType' => $approvedByType,
        'requiredOk'     => $requiredOk,
    ]);
}


  public function store(Request $r, int $id)
    {
        $tenantId = $this->tenantId();

        $vehicle = DB::table('vehicles')
            ->where('tenant_id',$tenantId)
            ->where('id',$id)
            ->first();
        abort_if(!$vehicle, 404);

        $data = $r->validate([
            'type' => ['required', Rule::in(array_keys($this->allowedTypes()))],
            'file' => ['required', 'file', 'max:6144', 'mimes:jpg,jpeg,png,pdf'],
            // opcionales a futuro:
            // 'document_no' => ['nullable','string','max:80'],
            // 'issue_date'  => ['nullable','date'],
            // 'expiry_date' => ['nullable','date','after_or_equal:issue_date'],
        ]);

        // Evitar reemplazar aprobados para ese tipo
        $existingApproved = DB::table('vehicle_documents')
            ->where('tenant_id',$tenantId)
            ->where('vehicle_id',$id)
            ->where('type',$data['type'])
            ->where('status','approved')
            ->exists();

        if ($existingApproved) {
            return back()->withErrors([
                'file' => 'Ese documento ya fue aprobado. Si necesitas cambiarlo, solicita revisión a SysAdmin.'
            ]);
        }

        // Limpiar documento previo (pending/rejected/expired) del mismo tipo
        $old = DB::table('vehicle_documents')
            ->where('tenant_id',$tenantId)
            ->where('vehicle_id',$id)
            ->where('type',$data['type'])
            ->orderByDesc('id')
            ->first();

        if ($old && $old->file_path && Storage::disk('public')->exists($old->file_path)) {
            Storage::disk('public')->delete($old->file_path);
        }
        if ($old) {
            DB::table('vehicle_documents')->where('id',$old->id)->delete();
        }

        $uploaded = $r->file('file');

        $path = $uploaded->store("vehicle-documents/{$tenantId}/{$id}", 'public');

        DB::table('vehicle_documents')->insert([
            'tenant_id'    => $tenantId,
            'vehicle_id'   => $id,
            'type'         => $data['type'],
            'document_no'  => null, // o $r->input('document_no')
            'issuer'       => null, // o $r->input('issuer')
            'issue_date'   => null, // o $r->input('issue_date')
            'expiry_date'  => null, // o $r->input('expiry_date')
            'file_path'    => $path,
            'original_name'=> $uploaded->getClientOriginalName(),
            'mime'         => $uploaded->getClientMimeType(),
            'size_bytes'   => $uploaded->getSize(),
            'status'       => 'pending',
            'review_notes' => null,
            'reviewed_by'  => null,
            'reviewed_at'  => null,
            'ocr_json'     => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Al subir documentos, podemos dejar el vehículo en 'pending' si estaba rejected
        // (para no tirar abajo uno ya verificado solo por subir un opcional)
        if ($vehicle->verification_status === 'rejected') {
            DB::table('vehicles')
                ->where('tenant_id',$tenantId)
                ->where('id',$id)
                ->update([
                    'verification_status' => 'pending',
                    'verification_notes'  => null,
                    'updated_at'          => now(),
                ]);
        }

        return back()->with('ok','Documento subido. Queda en verificación.');
    }


    public function download(int $doc)
    {
        $tenantId = $this->tenantId();

        $d = DB::table('vehicle_documents')->where('tenant_id',$tenantId)->where('id',$doc)->first();
        abort_if(!$d, 404);

        return Storage::disk('public')->download($d->file_path);
    }

    public function destroy(int $doc)
    {
        $tenantId = $this->tenantId();

        $d = DB::table('vehicle_documents')->where('tenant_id',$tenantId)->where('id',$doc)->first();
        abort_if(!$d, 404);

        if ($d->status === 'approved') {
            return back()->withErrors(['delete' => 'No puedes borrar un documento aprobado.']);
        }

        if ($d->file_path && Storage::disk('public')->exists($d->file_path)) {
            Storage::disk('public')->delete($d->file_path);
        }

        DB::table('vehicle_documents')->where('id',$doc)->delete();

        return back()->with('ok','Documento eliminado.');
    }
}
