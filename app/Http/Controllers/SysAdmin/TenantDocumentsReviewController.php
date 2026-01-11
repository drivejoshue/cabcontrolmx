<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantDocumentsReviewController extends Controller
{
    public function index(Tenant $tenant)
    {
        // Aquí normalmente validarías rol sysadmin
        // abort_unless((int)auth()->user()->is_sysadmin === 1, 403);

        $docs = TenantDocument::where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('type');

        return view('sysadmin.tenants.documents', compact('tenant', 'docs'));
    }

    public function download(TenantDocument $doc)
    {
        // abort_unless((int)auth()->user()->is_sysadmin === 1, 403);

        abort_unless(Storage::disk($doc->disk)->exists($doc->path), 404);

       return Storage::disk($doc->disk)->download($doc->path, $doc->original_name ?: basename($doc->path));

    }

    public function approve(TenantDocument $doc)
    {
        // abort_unless((int)auth()->user()->is_sysadmin === 1, 403);

        $doc->status = 'approved';
        $doc->reviewed_by = auth()->id();
        $doc->reviewed_at = now();
        $doc->review_notes = null;
        $doc->save();

        return back()->with('ok', 'Documento aprobado.');
    }

    public function reject(Request $request, TenantDocument $doc)
    {
        // abort_unless((int)auth()->user()->is_sysadmin === 1, 403);

        $data = $request->validate([
            'review_notes' => ['required','string','max:400'],
        ], [
            'review_notes.required' => 'Debes escribir un motivo/observación para rechazar.',
        ]);

        $doc->status = 'rejected';
        $doc->reviewed_by = auth()->id();
        $doc->reviewed_at = now();
        $doc->review_notes = $data['review_notes'];
        $doc->save();

        return back()->with('ok', 'Documento rechazado con observación.');
    }

    public function reopen(TenantDocument $doc)
    {
        // abort_unless((int)auth()->user()->is_sysadmin === 1, 403);

        // Reabrir para permitir re-subida del tenant
        $doc->status = 'pending';
        $doc->reviewed_by = auth()->id();
        $doc->reviewed_at = now();
        $doc->review_notes = null; // o conserva si quieres histórico
        $doc->save();

        return back()->with('ok', 'Documento reabierto (pendiente). El tenant podrá re-subir.');
    }
}
