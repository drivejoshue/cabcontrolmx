<?php

namespace App\Http\Controllers\SysAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VerificationQueueController extends Controller
{
    private function requiredVehicleTypes(): array
{
    return ['foto_vehiculo','placas','cedula_transporte_publico','tarjeta_circulacion'];
}
private function requiredDriverTypes(): array
{
    // que coincida con los blades
    return ['foto_driver','ine','licencia'];
}

   // app/Http/Controllers/SysAdmin/VerificationQueueController.php (método index)
public function index()
{
    $tenantId = request('tenant_id');
    $status   = request('status'); // pending|verified|rejected
    $q        = trim((string)request('q', ''));

    // ▼ Lista para el dropdown
    $tenantsList = DB::table('tenants')
        ->select('id','name')
        ->orderBy('name')
        ->get();

    $vehiclesQ = DB::table('vehicles as v')
        ->join('tenants as t','t.id','=','v.tenant_id')
        ->leftJoin(DB::raw("
            (select tenant_id, vehicle_id, count(*) as pending_docs
             from vehicle_documents where status='pending'
             group by tenant_id, vehicle_id) pd
        "), function ($j) {
            $j->on('pd.tenant_id','=','v.tenant_id')->on('pd.vehicle_id','=','v.id');
        });

    if ($tenantId !== null && $tenantId !== '') {
        $vehiclesQ->where('v.tenant_id', (int)$tenantId);
    }
    if ($status) {
        $vehiclesQ->where('v.verification_status', $status);
    }
    if ($q !== '') {
        $vehiclesQ->where(function($w) use ($q){
            $w->where('v.economico','like',"%$q%")
              ->orWhere('v.plate','like',"%$q%")
              ->orWhere('t.name','like',"%$q%");
        });
    }

    $vehicles = $vehiclesQ
        ->where(function($w){
            $w->whereNull('v.verification_status')
              ->orWhere('v.verification_status','<>','verified')
              ->orWhereRaw('COALESCE(pd.pending_docs,0) > 0');
        })
        ->orderByRaw("COALESCE(pd.pending_docs,0) DESC")
        ->orderByDesc('v.id')
        ->select([
            'v.id','v.tenant_id','v.economico','v.plate','v.year',
            'v.verification_status','v.verification_notes',
            't.name as tenant_name',
            DB::raw('COALESCE(pd.pending_docs,0) as pending_docs'),
        ])
        ->paginate(25, ['*'], 'vehicles_page')
        ->appends(request()->query());

    $driversQ = DB::table('drivers as d')
        ->join('tenants as t','t.id','=','d.tenant_id')
        ->leftJoin(DB::raw("
            (select tenant_id, driver_id, count(*) as pending_docs
             from driver_documents where status='pending'
             group by tenant_id, driver_id) pd
        "), function ($j) {
            $j->on('pd.tenant_id','=','d.tenant_id')->on('pd.driver_id','=','d.id');
        });

    if ($tenantId !== null && $tenantId !== '') {
        $driversQ->where('d.tenant_id', (int)$tenantId);
    }
    if ($status) {
        $driversQ->where('d.verification_status', $status);
    }
    if ($q !== '') {
        $driversQ->where(function($w) use ($q){
            $w->where('d.name','like',"%$q%")
              ->orWhere('d.email','like',"%$q%")
              ->orWhere('d.phone','like',"%$q%")
              ->orWhere('t.name','like',"%$q%");
        });
    }

    $drivers = $driversQ
        ->where(function($w){
            $w->whereNull('d.verification_status')
              ->orWhere('d.verification_status','<>','verified')
              ->orWhereRaw('COALESCE(pd.pending_docs,0) > 0');
        })
        ->orderByRaw("COALESCE(pd.pending_docs,0) DESC")
        ->orderByDesc('d.id')
        ->select([
            'd.id','d.tenant_id','d.name','d.phone','d.email',
            'd.verification_status','d.verification_notes',
            't.name as tenant_name',
            DB::raw('COALESCE(pd.pending_docs,0) as pending_docs'),
        ])
        ->paginate(25, ['*'], 'drivers_page')
        ->appends(request()->query());

    return view('sysadmin.verification.index', compact('vehicles','drivers','tenantsList'));
}



    /* =========================================================
     *  VEHÍCULOS
     * ========================================================= */

    public function showVehicle(int $vehicleId)
    {
        $v = DB::table('vehicles as v')
            ->join('tenants as t','t.id','=','v.tenant_id')
            ->where('v.id',$vehicleId)
            ->select('v.*','t.name as tenant_name')
            ->first();

        abort_if(!$v, 404);

        $docs = DB::table('vehicle_documents')
            ->where('tenant_id',$v->tenant_id)
            ->where('vehicle_id',$v->id)
            ->orderByDesc('id')
            ->get();

        return view('sysadmin.verification.vehicle_show', [
            'v'        => $v,
            'docs'     => $docs,
            'required' => $this->requiredVehicleTypes(),
        ]);
    }

    public function reviewVehicleDoc(Request $r, int $docId)
    {
        $data = $r->validate([
            'action' => 'required|in:approve,reject',
            'notes'  => 'nullable|string|max:500',
        ]);

        $doc = DB::table('vehicle_documents')->where('id',$docId)->first();
        abort_if(!$doc, 404);

        $newStatus = $data['action'] === 'approve' ? 'approved' : 'rejected';

        DB::table('vehicle_documents')
            ->where('id',$docId)
            ->update([
                'status'       => $newStatus,
                'review_notes' => $data['notes'] ?? null,
                'reviewed_by'  => Auth::id(),
                'reviewed_at'  => now(),
                'updated_at'   => now(),
            ]);

        $this->recalcVehicleVerification((int)$doc->tenant_id, (int)$doc->vehicle_id);

        return back()->with('ok', 'Documento de vehículo actualizado.');
    }

    private function recalcVehicleVerification(int $tenantId, int $vehicleId): void
    {
        $required = $this->requiredVehicleTypes();

        $rows = DB::table('vehicle_documents')
            ->where('tenant_id',$tenantId)
            ->where('vehicle_id',$vehicleId)
            ->select('type','status')
            ->get();

        if ($rows->isEmpty()) {
            DB::table('vehicles')
                ->where('tenant_id',$tenantId)
                ->where('id',$vehicleId)
                ->update([
                    'verification_status' => null,
                    'updated_at'          => now(),
                ]);
            return;
        }

        $anyRejected = $rows->contains(fn($d) => $d->status === 'rejected');

        $approvedTypes = $rows
            ->where('status','approved')
            ->pluck('type')
            ->unique()
            ->values()
            ->all();

        $allRequiredApproved = collect($required)
            ->every(fn($t) => in_array($t, $approvedTypes, true));

        $newStatus = $anyRejected
            ? 'rejected'
            : ($allRequiredApproved ? 'verified' : 'pending');

        DB::table('vehicles')
            ->where('tenant_id',$tenantId)
            ->where('id',$vehicleId)
            ->update([
                'verification_status' => $newStatus,
                'updated_at'          => now(),
            ]);
    }

    /* =========================================================
     *  DRIVERS
     * ========================================================= */

    public function showDriver(int $driverId)
    {
        $d = DB::table('drivers as d')
            ->join('tenants as t','t.id','=','d.tenant_id')
            ->where('d.id',$driverId)
            ->select('d.*','t.name as tenant_name')
            ->first();

        abort_if(!$d, 404);

        $docs = DB::table('driver_documents')
            ->where('tenant_id',$d->tenant_id)
            ->where('driver_id',$d->id)
            ->orderByDesc('id')
            ->get();

        return view('sysadmin.verification.driver_show', [
            'd'        => $d,
            'docs'     => $docs,
            'required' => $this->requiredDriverTypes(),
        ]);
    }

    public function reviewDriverDoc(Request $r, int $docId)
    {
        $data = $r->validate([
            'action' => 'required|in:approve,reject',
            'notes'  => 'nullable|string|max:500',
        ]);

        $doc = DB::table('driver_documents')->where('id',$docId)->first();
        abort_if(!$doc, 404);

        $newStatus = $data['action'] === 'approve' ? 'approved' : 'rejected';

        DB::table('driver_documents')
            ->where('id',$docId)
            ->update([
                'status'       => $newStatus,
                'review_notes' => $data['notes'] ?? null,
                'reviewed_by'  => Auth::id(),
                'reviewed_at'  => now(),
                'updated_at'   => now(),
            ]);

        $this->recalcDriverVerification((int)$doc->tenant_id, (int)$doc->driver_id);

        return back()->with('ok', 'Documento de conductor actualizado.');
    }

    private function recalcDriverVerification(int $tenantId, int $driverId): void
    {
        $required = $this->requiredDriverTypes();

        $rows = DB::table('driver_documents')
            ->where('tenant_id',$tenantId)
            ->where('driver_id',$driverId)
            ->select('type','status')
            ->get();

        if ($rows->isEmpty()) {
            DB::table('drivers')
                ->where('tenant_id',$tenantId)
                ->where('id',$driverId)
                ->update([
                    'verification_status' => 'pending',
                    'updated_at'          => now(),
                ]);
            return;
        }

        $anyRejected = $rows->contains(fn($d) => $d->status === 'rejected');

        $approvedTypes = $rows
            ->where('status','approved')
            ->pluck('type')
            ->unique()
            ->values()
            ->all();

        $allRequiredApproved = collect($required)
            ->every(fn($t) => in_array($t, $approvedTypes, true));

        $newStatus = $anyRejected
            ? 'rejected'
            : ($allRequiredApproved ? 'verified' : 'pending');

        DB::table('drivers')
            ->where('tenant_id',$tenantId)
            ->where('id',$driverId)
            ->update([
                'verification_status' => $newStatus,
                'updated_at'          => now(),
            ]);
    }
}
