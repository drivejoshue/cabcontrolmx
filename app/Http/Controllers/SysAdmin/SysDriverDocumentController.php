<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Driver;
use App\Models\DriverDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SysDriverDocumentController extends Controller
{
    /**
     * Tipos requeridos para considerar al driver "verificado"
     */
    private function requiredTypes(): array
    {
        // Debe coincidir con el ENUM de driver_documents:
        // enum('licencia','ine','selfie','foto_conductor')
        return ['licencia', 'ine', 'selfie'];
    }

    /**
     * Etiquetas legibles por tipo de documento
     */
    private function allowedTypes(): array
    {
        return [
            'licencia'       => 'Licencia de conducir',
            'ine'            => 'Identificación oficial (INE)',
            'selfie'         => 'Selfie con documento',
            'foto_conductor' => 'Foto del conductor (opcional)',
        ];
    }

    /**
     * Vista completa de documentos de un conductor (por tenant/driver)
     * GET /sysadmin/tenants/{tenant}/drivers/{driver}/documents
     */
    public function index(Tenant $tenant, Driver $driver)
    {
        // Seguridad: que el driver pertenezca al tenant
        if ((int) $driver->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        $docs = DriverDocument::where('tenant_id', $tenant->id)
            ->where('driver_id', $driver->id)
            ->orderByDesc('id')
            ->get();

        return view('sysadmin.drivers.documents.index', [
            'tenant'   => $tenant,
            'driver'   => $driver,
            'docs'     => $docs,
            'types'    => $this->allowedTypes(),
            'required' => $this->requiredTypes(),
        ]);
    }

    /**
     * Revisar (aprobar/rechazar) un documento de driver
     * POST /sysadmin/driver-documents/{document}/review
     */
    public function review(Request $request, DriverDocument $document)
    {
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'notes'  => 'nullable|string|max:500',
        ]);

        $newStatus = $data['action'] === 'approve' ? 'approved' : 'rejected';

        $document->status       = $newStatus;
        $document->review_notes = $data['notes'] ?? null;
        $document->reviewed_by  = Auth::id();
        $document->reviewed_at  = now();
        $document->save();

        // Recalcular el verification_status del driver usando TODOS los docs
        $this->recalcDriverVerification($document->tenant_id, $document->driver_id);

        return back()->with('ok', 'Documento actualizado.');
    }

    /**
     * Descargar/ver archivo
     * GET /sysadmin/driver-documents/{document}/download
     */
    public function download(DriverDocument $document)
    {
        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('public')->download($document->file_path);
    }

    /**
     * Recalcula verification_status del driver según sus docs.
     *
     * Regla:
     *  - Si hay algún doc REJECTED => status = 'rejected'
     *  - Si TODOS los requeridos tienen al menos un APPROVED => 'verified'
     *  - En cualquier otro caso => 'pending'
     */
    private function recalcDriverVerification(int $tenantId, int $driverId): void
    {
        $required = $this->requiredTypes();

        $docs = DriverDocument::where('tenant_id', $tenantId)
            ->where('driver_id', $driverId)
            ->get();

        $approved    = $docs->where('status', 'approved');
        $hasRejected = $docs->where('status', 'rejected')->isNotEmpty();

        $hasAllRequired = collect($required)->every(function (string $type) use ($approved) {
            return $approved->where('type', $type)->count() > 0;
        });

        $newStatus = 'pending';

        if ($hasRejected) {
            $newStatus = 'rejected';
        } elseif ($hasAllRequired) {
            $newStatus = 'verified';
        }

        DB::table('drivers')
            ->where('tenant_id', $tenantId)
            ->where('id', $driverId)
            ->update([
                'verification_status' => $newStatus,
                'updated_at'          => now(),
            ]);
    }

     public function view(DriverDocument $document)
    {
        // Opcional: si quieres asegurar que el SysAdmin no vea docs “cruzados” de otros tenants,
        // puedes validar aquí el tenant si lo necesitas.

        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'Archivo no encontrado');
        }

        $path = Storage::disk('public')->path($document->file_path);
        $mime = Storage::disk('public')->mimeType($document->file_path) ?? 'application/octet-stream';

        // Muestra inline: imágenes/PDF se ven en el navegador
        return response()->file($path, [
            'Content-Type' => $mime,
        ]);
    }

   
}
