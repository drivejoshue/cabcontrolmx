<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DriverDocsController extends Controller
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
            'licencia' => 'Licencia',
            'ine'      => 'INE',
            'selfie'   => 'Selfie',
            'foto_conductor' => 'Foto (opcional)',
        ];
    }

    public function index(int $id)
    {
        $tenantId = $this->tenantId();

        $driver = DB::table('drivers')->where('tenant_id',$tenantId)->where('id',$id)->first();
        abort_if(!$driver, 404);

        $docs = DB::table('driver_documents')
            ->where('tenant_id',$tenantId)
            ->where('driver_id',$id)
            ->orderByDesc('id')
            ->get();

        $required = ['licencia','ine','selfie'];

        return view('admin.drivers.documents', [
            'driver' => $driver,
            'docs' => $docs,
            'types' => $this->allowedTypes(),
            'required' => $required,
        ]);
    }

    public function store(Request $r, int $id)
    {
        $tenantId = $this->tenantId();

        $driver = DB::table('drivers')->where('tenant_id',$tenantId)->where('id',$id)->first();
        abort_if(!$driver, 404);

        $data = $r->validate([
        'type' => ['required', Rule::in(array_keys($this->allowedTypes()))],
        'file' => ['required','file','mimes:jpg,jpeg,png,pdf','max:6144'],
    ]);

        $existingApproved = DB::table('driver_documents')
            ->where('tenant_id',$tenantId)
            ->where('driver_id',$id)
            ->where('type',$data['type'])
            ->where('status','approved')
            ->exists();

        if ($existingApproved) {
            return back()->withErrors(['file' => 'Ese documento ya fue aprobado. Para cambiarlo, requiere revisión Orbana Admin.']);
        }

        $old = DB::table('driver_documents')
            ->where('tenant_id',$tenantId)
            ->where('driver_id',$id)
            ->where('type',$data['type'])
            ->orderByDesc('id')
            ->first();

        if ($old && $old->file_path && Storage::disk('public')->exists($old->file_path)) {
            Storage::disk('public')->delete($old->file_path);
        }
        if ($old) {
            DB::table('driver_documents')->where('id',$old->id)->delete();
        }

        $path = $r->file('file')->store("driver-documents/{$tenantId}/{$id}", 'public');

        DB::table('driver_documents')->insert([
            'tenant_id' => $tenantId,
            'driver_id' => $id,
            'type'      => $data['type'],
            'file_path' => $path,
            'status'    => 'pending',
            'ocr_json'  => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes'=> null,
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        DB::table('drivers')->where('tenant_id',$tenantId)->where('id',$id)->update([
            'verification_status' => 'pending',
            'updated_at' => now(),
        ]);

        return back()->with('ok','Documento subido. Queda en verificación.');
    }

    public function download(int $doc)
    {
        $tenantId = $this->tenantId();

        $d = DB::table('driver_documents')->where('tenant_id',$tenantId)->where('id',$doc)->first();
        abort_if(!$d, 404);

        return Storage::disk('public')->download($d->file_path);
    }

    public function destroy(int $doc)
    {
        $tenantId = $this->tenantId();

        $d = DB::table('driver_documents')->where('tenant_id',$tenantId)->where('id',$doc)->first();
        abort_if(!$d, 404);

        if ($d->status === 'approved') {
            return back()->withErrors(['delete' => 'No puedes borrar un documento aprobado.']);
        }

        if ($d->file_path && Storage::disk('public')->exists($d->file_path)) {
            Storage::disk('public')->delete($d->file_path);
        }

        DB::table('driver_documents')->where('id',$doc)->delete();

        return back()->with('ok','Documento eliminado.');
    }
}
