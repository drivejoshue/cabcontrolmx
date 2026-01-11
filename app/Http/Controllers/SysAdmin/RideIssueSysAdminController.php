<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use App\Models\RideIssue;
use App\Models\RideIssueNote;
use Illuminate\Http\Request;

class RideIssueSysAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = RideIssue::query()
            ->with(['tenant:id,name', 'ride:id,tenant_id,status,created_at', 'driver:id,name', 'passenger:id,name']);

        if ($request->filled('tenant_id')) $q->where('tenant_id', (int)$request->input('tenant_id'));
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('severity')) $q->where('severity', $request->string('severity'));
        if ($request->filled('category')) $q->where('category', $request->string('category'));
        if ($request->filled('forward_to_platform')) $q->where('forward_to_platform', (int)$request->input('forward_to_platform'));

        if ($request->filled('q')) {
            $term = trim((string)$request->input('q'));
            $q->where(function ($qq) use ($term) {
                $qq->where('title', 'like', "%{$term}%")
                   ->orWhere('id', $term)
                   ->orWhere('ride_id', $term);
            });
        }

        $items = $q->orderByDesc('created_at')->paginate(30)->withQueryString();
        return view('sysadmin.ride_issues.index', compact('items'));
    }

    public function show(Request $request, RideIssue $issue)
    {
        $issue->load(['tenant', 'ride', 'passenger', 'driver', 'notes.user', 'resolvedByUser']);
        return view('sysadmin.ride_issues.show', compact('issue'));
    }

    public function update(Request $request, RideIssue $issue)
    {
        $data = $request->validate([
            'status' => 'required|in:open,in_review,resolved,closed',
            'severity' => 'required|in:low,normal,high,critical',
            'forward_to_platform' => 'required|boolean',
            'resolution_notes' => 'nullable|string|max:20000',
        ]);

        if (in_array($data['status'], ['resolved','closed'], true)) {
            $issue->resolved_at = $issue->resolved_at ?? now();
            $issue->resolved_by_user_id = $issue->resolved_by_user_id ?? $request->user()->id;
            if ($data['status'] === 'closed') $issue->closed_at = $issue->closed_at ?? now();
        } else {
            $issue->closed_at = null;
        }

        $issue->fill($data)->save();

        RideIssueNote::create([
            'tenant_id' => $issue->tenant_id,
            'ride_issue_id' => $issue->id,
            'user_id' => $request->user()->id,
            'visibility' => 'platform',
            'note' => "SYSADMIN update: status={$issue->status}, severity={$issue->severity}, forward_to_platform=" . ($issue->forward_to_platform ? '1':'0'),
        ]);

        return redirect()->route('sysadmin.ride_issues.show', $issue)->with('ok', 'Issue actualizado.');
    }

    public function storeNote(Request $request, RideIssue $issue)
    {
        $data = $request->validate([
            'note' => 'required|string|max:20000',
            'share_with_tenant' => 'nullable|boolean',
        ]);

        RideIssueNote::create([
            'tenant_id' => $issue->tenant_id,
            'ride_issue_id' => $issue->id,
            'user_id' => $request->user()->id,
            'visibility' => ($request->boolean('share_with_tenant') ? 'tenant' : 'platform'),
            'note' => $data['note'],
        ]);

        return back()->with('ok', 'Nota agregada.');
    }
}
