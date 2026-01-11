<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantDocumentsController extends Controller
{


 public function store(Request $request)
{
    $tenant = Tenant::findOrFail(auth()->user()->tenant_id);

    // Reglas base de archivo
    $fileRules = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']; // 10MB

    // Detectar si ya existen docs requeridos
    $existing = TenantDocument::where('tenant_id', $tenant->id)
        ->whereIn('type', [
            TenantDocument::TYPE_ID_OFFICIAL,
            TenantDocument::TYPE_PROOF_ADDRESS,
            TenantDocument::TYPE_TAX_CERTIFICATE,
        ])
        ->get()
        ->keyBy('type');

    $hasId    = $existing->has(TenantDocument::TYPE_ID_OFFICIAL);
    $hasProof = $existing->has(TenantDocument::TYPE_PROOF_ADDRESS);

    /**
     * Regla de negocio:
     * - id_official: requerido si NO existe aún (y si no viene proof y tampoco existe)
     * - proof_address: requerido si NO existe aún (y si no viene id y tampoco existe)
     * - tax_certificate: opcional siempre
     *
     * Esto permite que el usuario suba ambos en un solo envío,
     * o que suba uno hoy y el otro después, sin bloquearlo.
     */
    $rules = [
        'id_official' => array_merge(
            (!$hasId ? ['required_without:proof_address'] : ['nullable']),
            $fileRules
        ),
        'proof_address' => array_merge(
            (!$hasProof ? ['required_without:id_official'] : ['nullable']),
            $fileRules
        ),
        'tax_certificate' => array_merge(['nullable'], $fileRules),
    ];

    $messages = [
        'id_official.required_without'   => 'Debes subir tu Identificación oficial o el Comprobante de domicilio (faltan documentos requeridos).',
        'proof_address.required_without' => 'Debes subir tu Comprobante de domicilio o la Identificación oficial (faltan documentos requeridos).',

        'id_official.mimes'     => 'Identificación oficial: formato no válido. Usa PDF/JPG/JPEG/PNG.',
        'proof_address.mimes'   => 'Comprobante de domicilio: formato no válido. Usa PDF/JPG/JPEG/PNG.',
        'tax_certificate.mimes' => 'Constancia fiscal: formato no válido. Usa PDF/JPG/JPEG/PNG.',

        'id_official.max'     => 'Identificación oficial: el archivo excede 10 MB.',
        'proof_address.max'   => 'Comprobante de domicilio: el archivo excede 10 MB.',
        'tax_certificate.max' => 'Constancia fiscal: el archivo excede 10 MB.',
    ];

    $data = $request->validate($rules, $messages);

    // Guardrail: si no llegó ningún archivo (por ejemplo, ya tienen ambos requeridos y le dieron enviar vacío)
    if (
        !$request->hasFile('id_official') &&
        !$request->hasFile('proof_address') &&
        !$request->hasFile('tax_certificate')
    ) {
        return back()
            ->withErrors(['docs' => 'No seleccionaste ningún archivo para subir.'])
            ->withInput();
    }



    DB::transaction(function () use ($tenant, $request) {
        $map = [
            TenantDocument::TYPE_ID_OFFICIAL     => 'id_official',
            TenantDocument::TYPE_PROOF_ADDRESS   => 'proof_address',
            TenantDocument::TYPE_TAX_CERTIFICATE => 'tax_certificate',
        ];

        foreach ($map as $type => $field) {
            if (!$request->hasFile($field)) continue;

            $file = $request->file($field);

            // Extra pro: bloquear archivos corruptos o no válidos
            if (!$file->isValid()) {
                throw new \RuntimeException("Archivo inválido para {$field}.");
            }

            // Guardar
           $dir  = "tenant-documents/{$tenant->id}/{$type}";
			$path = $file->store($dir, ['disk' => 'private']);

            TenantDocument::updateOrCreate(
                ['tenant_id' => $tenant->id, 'type' => $type],
                [
                    'status'        => 'pending',
                    'disk'          => 'private',
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime'          => $file->getMimeType(),
                    'size_bytes'    => (int) $file->getSize(),
                    'uploaded_by'   => auth()->id(),
                    'uploaded_at'   => now(),
                    'reviewed_by'   => null,
                    'reviewed_at'   => null,
                    'review_notes'  => null,
                ]
            );
        }
    });

    // Mensaje pro: indicar si ya quedó “completo” lo requerido
    $nowHasId = TenantDocument::where('tenant_id', $tenant->id)
        ->where('type', TenantDocument::TYPE_ID_OFFICIAL)
        ->exists();

    $nowHasProof = TenantDocument::where('tenant_id', $tenant->id)
        ->where('type', TenantDocument::TYPE_PROOF_ADDRESS)
        ->exists();

    if ($nowHasId && $nowHasProof) {
        return back()->with('status', 'Documentos enviados. Identificación y comprobante quedaron registrados y pendientes de validación por SysAdmin.');
    }

    return back()->with('status', 'Documento enviado. Aún faltan documentos requeridos (Identificación y/o Comprobante).');
}



    public function download(TenantDocument $doc)
    {
        $tenantId = (int)auth()->user()->tenant_id;
        abort_if((int)$doc->tenant_id !== $tenantId, 404);

        abort_unless(Storage::disk($doc->disk)->exists($doc->path), 404);

        return Storage::disk($doc->disk)->download($doc->path, $doc->original_name ?: basename($doc->path));
    }
}
