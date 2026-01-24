<?php

namespace App\Http\Controllers\Partner\Support;

use App\Http\Controllers\Controller;
use App\Models\PartnerThread;
use App\Models\PartnerThreadMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerSupportController extends Controller
{
    private function tenantId(): int
    {
        $tid = (int) auth()->user()->tenant_id;
        abort_if($tid <= 0, 403);
        return $tid;
    }

    private function partnerId(): int
    {
        $pid = (int) (session('partner_id') ?: auth()->user()->default_partner_id);
        abort_if($pid <= 0, 403, 'Falta contexto de partner.');
        return $pid;
    }

    public function index(Request $r)
    {
        $r->validate([
            'status' => ['nullable','in:open,in_progress,resolved,closed'],
            'category' => ['nullable','in:taxi_stand,tariff,bug,suggestion,other'],
            'q' => ['nullable','string','max:120'],
        ]);

        $tenantId = $this->tenantId();
        $partnerId = $this->partnerId();

        $q = PartnerThread::query()
            ->forContext($tenantId, $partnerId);

        if ($st = $r->input('status')) $q->where('status', $st);
        if ($cat = $r->input('category')) $q->where('category', $cat);

        if ($search = trim((string)$r->input('q'))) {
            $like = '%' . $search . '%';
            $q->where('subject','like',$like);
        }

        $threads = $q->orderByDesc(DB::raw('COALESCE(last_message_at, created_at)'))
            ->paginate(20)
            ->withQueryString();

        return view('partner.support.index', compact('threads'));
    }

    public function create()
    {
        return view('partner.support.create');
    }

    public function store(Request $r)
    {
        $r->validate([
            'subject' => ['required','string','max:190'],
            'category' => ['required','in:taxi_stand,tariff,bug,suggestion,other'],
            'priority' => ['nullable','in:low,normal,high,urgent'],
            'message' => ['required','string','max:8000'],
        ]);

        $tenantId = $this->tenantId();
        $partnerId = $this->partnerId();

        return DB::transaction(function () use ($r, $tenantId, $partnerId) {
            $thread = PartnerThread::create([
                'tenant_id' => $tenantId,
                'partner_id' => $partnerId,
                'category' => $r->category,
                'priority' => $r->input('priority', 'normal'),
                'status' => 'open',
                'subject' => $r->subject,
                'last_partner_read_at' => now(),
            ]);

            $msg = PartnerThreadMessage::create([
                'tenant_id' => $tenantId,
                'partner_id' => $partnerId,
                'thread_id' => $thread->id,
                'author_role' => 'partner',
                'author_id' => auth()->id(),
                'message' => $r->message,
            ]);

            $thread->touchLastMessage($msg->id);

            return redirect()
                ->route('partner.support.show', $thread->id)
                ->with('ok', 'Solicitud creada.');
        });
    }

    public function show(Request $r, int $threadId)
    {
        $tenantId = $this->tenantId();
        $partnerId = $this->partnerId();

        $thread = PartnerThread::query()
            ->forContext($tenantId, $partnerId)
            ->where('id', $threadId)
            ->firstOrFail();

        $messages = PartnerThreadMessage::query()
            ->where('thread_id', $thread->id)
            ->orderBy('id')
            ->get();

            

        // Partner leyó hasta ahora
        $thread->update(['last_partner_read_at' => now()]);

        return view('partner.support.show', compact('thread','messages'));
    }

    public function reply(Request $r, int $threadId)
    {
        $r->validate([
            'message' => ['required','string','max:8000'],
        ]);

        $tenantId = $this->tenantId();
        $partnerId = $this->partnerId();

        $thread = PartnerThread::query()
            ->forContext($tenantId, $partnerId)
            ->where('id', $threadId)
            ->firstOrFail();

        abort_if(in_array($thread->status, ['closed'], true), 422, 'El ticket está cerrado.');

        return DB::transaction(function () use ($r, $thread, $tenantId, $partnerId) {
            $msg = PartnerThreadMessage::create([
                'tenant_id' => $tenantId,
                'partner_id' => $partnerId,
                'thread_id' => $thread->id,
                'author_role' => 'partner',
                'author_id' => auth()->id(),
                'message' => $r->message,
            ]);

            $thread->status = in_array($thread->status, ['resolved'], true) ? 'in_progress' : $thread->status;
            $thread->last_partner_read_at = now();
            $thread->touchLastMessage($msg->id);

            return back()->with('ok', 'Mensaje enviado.');
        });
    }

    public function close(int $threadId)
    {
        $tenantId = $this->tenantId();
        $partnerId = $this->partnerId();

        $thread = PartnerThread::query()
            ->forContext($tenantId, $partnerId)
            ->where('id', $threadId)
            ->firstOrFail();

        $thread->update(['status' => 'closed']);

        return back()->with('ok', 'Ticket cerrado.');
    }
}
