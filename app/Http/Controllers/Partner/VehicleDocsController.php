<?php

namespace App\Http\Controllers\Partner;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VehicleDocsController extends BasePartnerController
{
    private function allowedTypes(): array
    {
        return [
            'foto_vehiculo'             => 'Foto del vehículo',
            'placas'                    => 'Foto de placas',
            'cedula_transporte_publico' => 'Cédula / Transporte público (Taxi)',
            'tarjeta_circulacion'       => 'Tarjeta de circulación',
            // opcionales:
            'seguro'                    => 'Póliza (opcional)',
        ];
    }

    public function index(int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        // ✅ Vehículo debe pertenecer al partner (por vehicles.partner_id)
        $vehicle = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('id', $id)
            ->first();
        abort_if(!$vehicle, 404);

        $docs = DB::table('vehicle_documents')
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $id)
            ->orderByDesc('id')
            ->get();

        $required = [
            'foto_vehiculo',
            'placas',
            'cedula_transporte_publico',
            'tarjeta_circulacion',
        ];

        $types = $this->allowedTypes();

        // docs aprobados por tipo (último aprobado)
        $approvedByType = collect($docs)
            ->where('status', 'approved')
            ->groupBy('type')
            ->map(fn ($g) => $g->sortByDesc('id')->first());

        $requiredOk = collect($required)->every(fn ($t) => isset($approvedByType[$t]));

        return view('partner.vehicles.documents', [
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
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $vehicle = DB::table('vehicles')
            ->where('tenant_id', $tenantId)
            ->where('partner_id', $partnerId)
            ->where('id', $id)
            ->first();
        abort_if(!$vehicle, 404);

        // ✅ OJO: aquí ya NO existe "vehicle_photo"; usamos los keys del Admin
        $data = $r->validate([
            'type' => ['required', Rule::in(array_keys($this->allowedTypes()))],
            'file' => ['required', 'file', 'max:6144', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        // Evitar reemplazar aprobados para ese tipo
        $existingApproved = DB::table('vehicle_documents')
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $id)
            ->where('type', $data['type'])
            ->where('status', 'approved')
            ->exists();

        if ($existingApproved) {
            return back()->withErrors([
                'file' => 'Ese documento ya fue aprobado. Si necesitas cambiarlo, solicita revisión a SysAdmin.'
            ]);
        }

        // Limpiar doc previo (pending/rejected/expired) del mismo tipo
        $old = DB::table('vehicle_documents')
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $id)
            ->where('type', $data['type'])
            ->orderByDesc('id')
            ->first();

        if ($old && $old->file_path && Storage::disk('public')->exists($old->file_path)) {
            Storage::disk('public')->delete($old->file_path);
        }
        if ($old) {
            DB::table('vehicle_documents')->where('id', $old->id)->delete();
        }

        $uploaded = $r->file('file');
        $path = $uploaded->store("vehicle-documents/{$tenantId}/{$id}", 'public');

        DB::table('vehicle_documents')->insert([
            'tenant_id'     => $tenantId,
            'vehicle_id'    => $id,
            'type'          => $data['type'],
            'document_no'   => null,
            'issuer'        => null,
            'issue_date'    => null,
            'expiry_date'   => null,
            'file_path'     => $path,
            'original_name' => $uploaded->getClientoriginalName(),
            'mime'          => $uploaded->getClientMimeType(),
            'size_bytes'    => $uploaded->getSize(),
            'status'        => 'pending',
            'review_notes'  => null,
            'reviewed_by'   => null,
            'reviewed_at'   => null,
            'ocr_json'      => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Igual que Admin: solo “revive” si estaba rejected (no tumba verificados por subir opcional)
        if (($vehicle->verification_status ?? null) === 'rejected') {
            DB::table('vehicles')
                ->where('tenant_id', $tenantId)
                ->where('id', $id)
                ->update([
                    'verification_status' => 'pending',
                    'verification_notes'  => null,
                    'updated_at'          => now(),
                ]);
        }

        return back()->with('ok', 'Documento subido. Queda en verificación.');
    }

    public function download(int $doc)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        // ✅ Doc debe pertenecer a un vehículo del partner
        $d = DB::table('vehicle_documents as d')
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->where('d.tenant_id', $tenantId)
            ->where('d.id', $doc)
            ->where('v.partner_id', $partnerId)
            ->select('d.*')
            ->first();
        abort_if(!$d, 404);

        return Storage::disk('public')->download($d->file_path);
    }

    public function destroy(int $doc)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $d = DB::table('vehicle_documents as d')
            ->join('vehicles as v', 'v.id', '=', 'd.vehicle_id')
            ->where('d.tenant_id', $tenantId)
            ->where('d.id', $doc)
            ->where('v.partner_id', $partnerId)
            ->select('d.*')
            ->first();
        abort_if(!$d, 404);

        if (($d->status ?? null) === 'approved') {
            return back()->withErrors(['delete' => 'No puedes borrar un documento aprobado.']);
        }

        if ($d->file_path && Storage::disk('public')->exists($d->file_path)) {
            Storage::disk('public')->delete($d->file_path);
        }

        DB::table('vehicle_documents')->where('id', $doc)->delete();

        return back()->with('ok', 'Documento eliminado.');
    }
}
