<?php

namespace App\Http\Controllers\Admin\Support;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantPartnerSupportController extends Controller
{
    private function tenantId(): int
    {
        $tid = (int) auth()->user()->tenant_id;
        abort_if($tid <= 0, 403);
        return $tid;
    }

    public function index(Request $r)
    {
        $r->validate([
            'q'         => ['nullable','string','max:120'],
            'status'    => ['nullable','in:open,in_review,closed'],
            'partner_id'=> ['nullable','integer','min:1'],
        ]);

        $tenantId = $this->tenantId();

        // partner_threads
        $q = DB::table('partner_threads as t')
            ->where('t.tenant_id', $tenantId);

        if ($r->filled('status')) {
            $q->where('t.status', $r->input('status'));
        }

        if ($r->filled('partner_id')) {
            $q->where('t.partner_id', (int) $r->input('partner_id'));
        }

        if ($search = trim((string) $r->input('q'))) {
            $like = '%' . $search . '%';
            $q->where(function ($w) use ($like) {
                $w->where('t.subject', 'like', $like)
                  ->orWhere('t.category', 'like', $like)
                  ->orWhere('t.priority', 'like', $like);
            });
        }

        // Listado con partner + métricas (usa last_message_at si existe)
        $items = $q->leftJoin('partners as p', 'p.id', '=', 't.partner_id')
            ->select([
                't.*',
                'p.name as partner_name',
                DB::raw('COALESCE(t.last_message_at, t.created_at) as last_msg_at'),
                DB::raw('(select count(*) from partner_thread_messages m where m.thread_id = t.id) as msgs_count'),
                // "unread" para tenant: último msg > last_tenant_read_at (si null => hay algo)
                DB::raw("
                    case
                      when t.last_message_at is null then 0
                      when t.last_tenant_read_at is null then 1
                      when t.last_message_at > t.last_tenant_read_at then 1
                      else 0
                    end as tenant_unread
                "),
            ])
            ->orderByDesc(DB::raw('COALESCE(t.last_message_at, t.created_at)'))
            ->paginate(20)
            ->withQueryString();

        $kpi = [
            'open'      => (int) DB::table('partner_threads')->where('tenant_id', $tenantId)->where('status', 'open')->count(),
            'in_review' => (int) DB::table('partner_threads')->where('tenant_id', $tenantId)->where('status', 'in_review')->count(),
            'closed'    => (int) DB::table('partner_threads')->where('tenant_id', $tenantId)->where('status', 'closed')->count(),
        ];

        $partners = DB::table('partners')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.partner_support.index', compact('items', 'kpi', 'partners'));
    }

    public function show(Request $r, int $thread)
    {
        $tenantId = $this->tenantId();

        $t = DB::table('partner_threads as t')
            ->leftJoin('partners as p', 'p.id', '=', 't.partner_id')
            ->where('t.tenant_id', $tenantId)
            ->where('t.id', $thread)
            ->select(['t.*', 'p.name as partner_name'])
            ->first();

        abort_if(! $t, 404);

        // Marcar como leído por tenant al abrir
        DB::table('partner_threads')
            ->where('tenant_id', $tenantId)
            ->where('id', $thread)
            ->update([
                'last_tenant_read_at' => now(),
                'updated_at'          => now(),
            ]);

        $messages = DB::table('partner_thread_messages as m')
            ->where('m.tenant_id', $tenantId)
            ->where('m.thread_id', $thread)
            ->orderBy('m.id', 'asc')
            ->get();

        return view('admin.partner_support.show', compact('t', 'messages'));
    }

    public function reply(Request $r, int $thread)
    {
        $tenantId = $this->tenantId();

        $r->validate([
            'body' => ['required','string','max:4000'],
        ]);

        $t = DB::table('partner_threads')
            ->where('tenant_id', $tenantId)
            ->where('id', $thread)
            ->first();

        abort_if(! $t, 404);

        DB::transaction(function () use ($thread, $tenantId, $r) {

            // Insert en partner_thread_messages (columnas reales)
            $msgId = DB::table('partner_thread_messages')->insertGetId([
                'tenant_id'    => $tenantId,
                'partner_id'   => (int) $t = DB::table('partner_threads')->where('id',$thread)->value('partner_id'),
                'thread_id'    => $thread,
                'author_role'  => 'admin',               // ✅ no "tenant"
                'author_id'    => (int) auth()->id(),
                'message'      => $r->input('body'),     // ✅ no "body"
                'meta'         => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Actualiza thread: last_message_*
            DB::table('partner_threads')
                ->where('tenant_id', $tenantId)
                ->where('id', $thread)
                ->update([
                    // si estaba open -> in_review (opcional)
                    'status'              => DB::raw("IF(status='open','in_review',status)"),
                    'last_message_id'     => $msgId,
                    'last_message_at'     => now(),
                    'last_tenant_read_at' => now(), // el admin ya lo leyó al responder
                    'updated_at'          => now(),
                ]);
        });

        return back()->with('ok', 'Respuesta enviada.');
    }

    public function setStatus(Request $r, int $thread)
    {
        $tenantId = $this->tenantId();

        $r->validate([
            'status' => ['required','in:open,in_review,closed'],
        ]);

        $affected = DB::table('partner_threads')
            ->where('tenant_id', $tenantId)
            ->where('id', $thread)
            ->update([
                'status'     => $r->input('status'),
                'updated_at' => now(),
            ]);

        abort_if($affected <= 0, 404);

        return back()->with('ok', 'Estado actualizado.');
    }

    // (Opcional) endpoint dedicado si quieres botón "Marcar leído" sin abrir show
    public function markRead(Request $r, int $thread)
    {
        $tenantId = $this->tenantId();

        $affected = DB::table('partner_threads')
            ->where('tenant_id', $tenantId)
            ->where('id', $thread)
            ->update([
                'last_tenant_read_at' => now(),
                'updated_at'          => now(),
            ]);

        abort_if($affected <= 0, 404);

        return back()->with('ok', 'Marcado como leído.');
    }
}
