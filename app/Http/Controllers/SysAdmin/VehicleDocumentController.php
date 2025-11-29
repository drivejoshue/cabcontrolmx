<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VehicleDocumentController extends Controller
{
    public function index(Tenant $tenant, Vehicle $vehicle)
    {
        // Seguridad: que el vehículo pertenezca al tenant
        if ((int) $vehicle->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        $documents = $vehicle->documents()->orderByDesc('id')->get();

        return view('sysadmin.vehicles.documents.index', [
            'tenant'    => $tenant,
            'vehicle'   => $vehicle,
            'documents' => $documents,
        ]);
    }

    public function store(Request $request, Tenant $tenant, Vehicle $vehicle)
    {
        if ((int) $vehicle->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        $data = $request->validate([
            'type'        => 'required|in:tarjeta_circulacion,poliza_seguro,refrendo,verificacion,placas,otro',
            'document_no' => 'nullable|string|max:80',
            'issuer'      => 'nullable|string|max:120',
            'issue_date'  => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'file'        => 'nullable|file|max:4096', // 4 MB
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('vehicle_docs', 'public');
        }

        $doc = new VehicleDocument();
        $doc->vehicle_id   = $vehicle->id;
        $doc->tenant_id    = $tenant->id;
        $doc->type         = $data['type'];
        $doc->document_no  = $data['document_no'] ?? null;
        $doc->issuer       = $data['issuer'] ?? null;
        $doc->issue_date   = $data['issue_date'] ?? null;
        $doc->expiry_date  = $data['expiry_date'] ?? null;
        $doc->file_path    = $filePath;
        $doc->status       = 'pending';
        $doc->review_notes = null;
        $doc->reviewed_by  = null;
        $doc->reviewed_at  = null;
        $doc->save();

        return redirect()
            ->back()
            ->with('status', 'Documento registrado (pendiente de revisión).');
    }

    public function review(Request $request, VehicleDocument $document)
    {
        $data = $request->validate([
            'status'       => 'required|in:pending,approved,rejected,expired',
            'review_notes' => 'nullable|string|max:255',
        ]);

        $document->status       = $data['status'];
        $document->review_notes = $data['review_notes'] ?? null;
        $document->reviewed_by  = $request->user()->id;
        $document->reviewed_at  = now();
        $document->save();

        // Aquí podríamos actualizar verification_status del vehículo:
        //  - si todos los docs clave están approved => vehicle.verification_status = 'verified'
        //  - si hay alguno rejected => 'rejected'
        // Lo dejamos para una sub-fase específica.

        return redirect()
            ->back()
            ->with('status', 'Documento revisado.');
    }

    /**
     * Opcional: descarga del archivo si quieres.
     */
    public function download(VehicleDocument $document)
    {
        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('public')->download($document->file_path);
    }
}
