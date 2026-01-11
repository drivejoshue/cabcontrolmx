<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RideIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RideIssueNote;


class RideIssueController extends Controller
{
    protected function currentTenantId(): int
    {
        $tenantId = optional(Auth::user())->tenant_id;

        if (!$tenantId) {
            abort(403, 'Usuario sin tenant asignado');
        }

        return (int) $tenantId;
    }

  public function index(Request $request)
{
    $tenantId = $this->currentTenantId();

    $status   = $request->query('status');
    $category = $request->query('category');
    $severity = $request->query('severity');
    $reporter = $request->query('reporter_type');
    $forward  = $request->query('forward_to_platform');
    $type     = $request->query('type'); // APP/PAGO/...
    $qTerm    = trim((string) $request->query('q', ''));

    $query = RideIssue::query()
        ->forTenant($tenantId)
        ->with(['ride', 'passenger', 'driver'])
        ->orderByDesc('created_at');

    if ($status && in_array($status, ['open','in_review','resolved','closed'], true)) {
        $query->where('status', $status);
    }

    if ($category) {
        $query->where('category', $category);
    }

    if ($severity && in_array($severity, ['low','normal','high','critical'], true)) {
        $query->where('severity', $severity);
    }

    if ($reporter && in_array($reporter, ['passenger','driver','tenant','system'], true)) {
        $query->where('reporter_type', $reporter);
    }

    if ($forward !== null && $forward !== '') {
        $query->where('forward_to_platform', (int)$forward);
    }

    // type derivado por categoría
    if ($type) {
        $type = strtoupper($type);
        $typeMap = [
            'APP'       => ['app_problem'],
            'PAGO'      => ['payment','overcharge'],
            'SEGURIDAD' => ['safety'],
            'RUTA'      => ['route'],
            'CONDUCTA'  => ['driver_behavior','passenger_behavior'],
            'VEHICULO'  => ['vehicle'],
            'OBJETO'    => ['lost_item'],
            'OTRO'      => ['other'],
        ];
        if (isset($typeMap[$type])) {
            $query->whereIn('category', $typeMap[$type]);
        }
    }

    if ($qTerm !== '') {
        $query->where(function ($qq) use ($qTerm) {
            $qq->where('title', 'like', "%{$qTerm}%")
               ->orWhere('id', $qTerm)
               ->orWhere('ride_id', $qTerm);
        });
    }

    $issues = $query->paginate(30)->withQueryString();

    return view('admin.ride_issues.index', compact(
        'issues','status','category','severity','reporter','forward','type','qTerm'
    ));
}


  public function show(int $id)
{
    $tenantId = $this->currentTenantId();

    $issue = RideIssue::query()
        ->forTenant($tenantId)
        ->with(['ride', 'passenger', 'driver', 'notes.user'])
        ->findOrFail($id);

    return view('admin.ride_issues.show', compact('issue'));
}


   public function updateStatus(Request $request, int $id)
{
    $tenantId = $this->currentTenantId();

    $data = $request->validate([
        'status'        => 'required|string|in:open,in_review,resolved,closed',
        'severity'      => 'nullable|string|in:low,normal,high,critical',
        'internal_note' => 'nullable|string|max:5000',
        'resolution_notes' => 'nullable|string|max:20000', // si agregaste campo
        'forward_to_platform' => 'nullable|boolean',
    ]);

    $issue = RideIssue::query()
        ->forTenant($tenantId)
        ->findOrFail($id);

    $issue->status = $data['status'];

    if (!empty($data['severity'])) {
        $issue->severity = $data['severity'];
    }

    if (array_key_exists('forward_to_platform', $data)) {
        $issue->forward_to_platform = (bool)$data['forward_to_platform'];
    }

    if (array_key_exists('resolution_notes', $data)) {
        $issue->resolution_notes = $data['resolution_notes'];
    }

    if (in_array($data['status'], ['resolved','closed'], true)) {
        $issue->resolved_at = $issue->resolved_at ?? now();
        if (property_exists($issue, 'resolved_by_user_id')) {
            $issue->resolved_by_user_id = $issue->resolved_by_user_id ?? Auth::id();
        }
        if ($data['status'] === 'closed' && property_exists($issue, 'closed_at')) {
            $issue->closed_at = $issue->closed_at ?? now();
        }
    } else {
        // si reabre, no cierres
        if (property_exists($issue, 'closed_at')) {
            $issue->closed_at = null;
        }
    }

    $issue->save();

    // Guardar nota en histórico si viene
    if (!empty($data['internal_note'])) {
        RideIssueNote::create([
            'tenant_id' => $issue->tenant_id,
            'ride_issue_id' => $issue->id,
            'user_id' => Auth::id(),
            'visibility' => 'tenant',
            'note' => $data['internal_note'],
        ]);
    } else {
        // opcional: nota automática de auditoría
        RideIssueNote::create([
            'tenant_id' => $issue->tenant_id,
            'ride_issue_id' => $issue->id,
            'user_id' => Auth::id(),
            'visibility' => 'tenant',
            'note' => "Cambio de estado: {$issue->status}" . (!empty($data['severity']) ? " / severity={$issue->severity}" : ""),
        ]);
    }

    return redirect()
        ->route('admin.ride_issues.show', $issue)
        ->with('status', 'Reporte actualizado correctamente.');
}


public function storeNote(Request $request, int $id)
{
    $tenantId = $this->currentTenantId();

    $data = $request->validate([
        'note' => 'required|string|max:5000',
    ]);

    $issue = RideIssue::query()->forTenant($tenantId)->findOrFail($id);

    \App\Models\RideIssueNote::create([
        'tenant_id' => $issue->tenant_id,
        'ride_issue_id' => $issue->id,
        'user_id' => Auth::id(),
        'visibility' => 'tenant',
        'note' => $data['note'],
    ]);

    return back()->with('status', 'Nota agregada.');
}





}
