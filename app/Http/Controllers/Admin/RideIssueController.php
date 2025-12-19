<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RideIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $query = RideIssue::query()
            ->forTenant($tenantId)
            ->with(['ride', 'passenger', 'driver'])
            ->orderByDesc('created_at');

        if ($status && in_array($status, ['open', 'in_review', 'resolved', 'closed'], true)) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->where('category', $category);
        }

        $issues = $query->paginate(30);

        return view('admin.ride_issues.index', compact('issues', 'status', 'category'));
    }

    public function show(int $id)
    {
        $tenantId = $this->currentTenantId();

        $issue = RideIssue::query()
            ->forTenant($tenantId)
            ->with(['ride', 'passenger', 'driver'])
            ->findOrFail($id);

        return view('admin.ride_issues.show', compact('issue'));
    }

    public function updateStatus(Request $request, int $id)
    {
        $tenantId = $this->currentTenantId();

        $data = $request->validate([
            'status'       => 'required|string|in:open,in_review,resolved,closed',
            'internal_note'=> 'nullable|string|max:5000',
        ]);

        $issue = RideIssue::query()
            ->forTenant($tenantId)
            ->findOrFail($id);

        $issue->status = $data['status'];

        if (in_array($data['status'], ['resolved', 'closed'], true)) {
            $issue->resolved_at = now();
        }

        $issue->save();

        // FUTURO: guardar internal_note en una tabla ride_issue_notes si quieres tener histórico de comentarios.

        return redirect()
            ->route('admin.ride_issues.show', $issue)
            ->with('status', 'Reporte actualizado correctamente.');
    }
}
