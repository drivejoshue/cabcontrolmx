<?php

namespace App\Http\Controllers\Partner\Inbox;

use App\Http\Controllers\Controller;
use App\Models\PartnerNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class PartnerInboxController extends Controller
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
            'q'      => ['nullable', 'string', 'max:120'],
            'type'   => ['nullable', 'string', 'max:64'],
            'level'  => ['nullable', 'in:info,success,warning,danger'],
            'unread' => ['nullable', 'in:0,1'],
        ]);

        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $q = PartnerNotification::query()->forContext($tenantId, $partnerId);

        // ✅ unread=1 => solo no leídas | unread=0 => no filtrar | vacío => todas
        if ($r->filled('unread') && $r->input('unread') === '1') {
            $q->whereNull('read_at');
        }

        if ($type = $r->input('type')) {
            $q->where('type', $type);
        }

        if ($level = $r->input('level')) {
            $q->where('level', $level);
        }

        if ($search = trim((string) $r->input('q'))) {
            $like = '%' . $search . '%';
            $q->where(function ($w) use ($like) {
                $w->where('title', 'like', $like)
                  ->orWhere('body', 'like', $like);
            });
        }

        $items = $q->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $kpi = [
            'unread' => PartnerNotification::query()
                ->forContext($tenantId, $partnerId)
                ->whereNull('read_at')
                ->count(),
        ];

        return view('partner.inbox.index', compact('items', 'kpi'));
    }

    public function markRead(Request $r, int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $n = PartnerNotification::query()
            ->forContext($tenantId, $partnerId)
            ->where('id', $id)
            ->firstOrFail();

        if ($n->read_at === null) {
            $n->update(['read_at' => now()]);
        }

        return back()->with('ok', 'Notificación marcada como leída.');
    }

    public function markAllRead()
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        PartnerNotification::query()
            ->forContext($tenantId, $partnerId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('ok', 'Inbox marcado como leído.');
    }

    public function go(int $id)
    {
        $tenantId  = $this->tenantId();
        $partnerId = $this->partnerId();

        $n = PartnerNotification::query()
            ->forContext($tenantId, $partnerId)
            ->where('id', $id)
            ->firstOrFail();

        // ✅ marcar como leído al abrir
        if ($n->read_at === null) {
            $n->update(['read_at' => now()]);
        }

        $type = (string) ($n->entity_type ?? '');
        $eid  = (int) ($n->entity_id ?? 0);

        if ($type === '' || $eid <= 0) {
            return redirect()->route('partner.inbox.index');
        }

        // helper: redirigir solo si la ruta existe (evita 500)
        $safeRedirect = function (string $routeName, array $params = []) {
            if (Route::has($routeName)) {
                return redirect()->route($routeName, $params);
            }
            return redirect()->route('partner.inbox.index')
                ->with('warn', 'Este evento no tiene vista relacionada (ruta no disponible).');
        };

        return match ($type) {

            // ✅ ride_id directo
            'ride' => $safeRedirect('partner.reports.rides.show', ['ride' => $eid]),

            // ✅ driver quality (driverId)
            'driver' => $safeRedirect('partner.reports.driver_quality.show', ['driver' => $eid]),

            // ✅ vehicles report (vehicleId)
            'vehicle' => $safeRedirect('partner.reports.vehicles.show', ['vehicle' => $eid]),

            // ✅ issue: resolver ride_id desde ride_issues.id = entity_id
            'ride_issue' => ($rideId = $this->resolveRideIdFromIssue($tenantId, $eid))
                ? $safeRedirect('partner.reports.rides.show', ['ride' => $rideId])
                : redirect()->route('partner.inbox.index')->with('warn', 'Issue no encontrado o sin ride asociado.'),

            // ✅ topup
            'partner_topup' => Route::has('partner.topups.show')
                ? redirect()->route('partner.topups.show', ['topup' => $eid])
                : (Route::has('partner.topups.index')
                    ? redirect()->route('partner.topups.index')
                    : (Route::has('partner.wallet.index')
                        ? redirect()->route('partner.wallet.index')
                        : redirect()->route('partner.inbox.index'))),

            default => redirect()->route('partner.inbox.index'),
        };
    }

    private function resolveRideIdFromIssue(int $tenantId, int $issueId): ?int
    {
        $rideId = DB::table('ride_issues')
            ->where('tenant_id', $tenantId)
            ->where('id', $issueId)
            ->value('ride_id');

        $rideId = (int) ($rideId ?? 0);

        return $rideId > 0 ? $rideId : null;
    }
}
