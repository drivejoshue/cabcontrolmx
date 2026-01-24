@extends('layouts.admin')

@section('title', 'Partner: '.$partner->name)

@section('content')
@php
  $money = fn($n) => '$'.number_format((float)$n, 2).' '.($currency ?? 'MXN');

  $pill = function($s){
    $s = strtolower((string)$s);
    return match($s){
      'active' => 'bg-success-lt text-success',
      'suspended' => 'bg-warning-lt text-warning',
      'closed' => 'bg-danger-lt text-danger',
      default => 'bg-secondary-lt text-secondary',
    };
  };

  $driverBadge = function($s){
    $s = strtolower((string)$s);
    return match($s){
      'idle' => 'bg-success-lt text-success',
      'busy' => 'bg-warning-lt text-warning',
      'on_ride' => 'bg-info-lt text-info',
      'offline' => 'bg-secondary-lt text-secondary',
      default => 'bg-secondary-lt text-secondary',
    };
  };

  $movDirBadge = function($d){
    $d = strtolower((string)$d);
    return $d === 'credit' ? 'bg-success-lt text-success' : 'bg-danger-lt text-danger';
  };
@endphp

<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <h1 class="h3 mb-0">{{ $partner->name }}</h1>
        <span class="badge {{ $pill($partner->status) }}">{{ strtoupper($partner->status) }}</span>
        @if((int)($partner->is_active ?? 1) === 0)
          <span class="badge bg-danger-lt text-danger">INACTIVO</span>
        @endif
      </div>
      <div class="text-muted small">
        {{ $partner->code }} · Kind: {{ $partner->kind }} · Tenant #{{ $partner->tenant_id }}
      </div>
      <div class="text-muted small">Vista solo lectura (Admin).</div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.partners.index') }}">
        <i class="ti ti-arrow-left me-1"></i> Volver
      </a>
      <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.partner_topups.index', ['partner_id' => $partner->id]) }}">
        <i class="ti ti-receipt me-1"></i> Recargas
      </a>
    </div>
  </div>

  {{-- Métricas --}}
  <div class="row row-cards mb-3">

    <div class="col-12 col-md-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <span class="avatar bg-azure-lt text-azure me-3"><i class="ti ti-wallet"></i></span>
            <div>
              <div class="text-muted">Saldo (Wallet)</div>
              <div class="h3 m-0">{{ $money($balance) }}</div>
              <div class="text-muted small">
                Última recarga: {{ $wallet->last_topup_at ?? '—' }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <span class="avatar bg-green-lt text-green me-3"><i class="ti ti-arrows-exchange"></i></span>
            <div>
              <div class="text-muted">Movimientos</div>
              <div class="h3 m-0">{{ (int)($movStats->total ?? 0) }}</div>
              <div class="text-muted small">
                Cred: {{ $money($movStats->total_credit ?? 0) }} · Deb: {{ $money($movStats->total_debit ?? 0) }}
              </div>
              <div class="text-muted small">Último: {{ $movLastAt ?: '—' }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <span class="avatar bg-indigo-lt text-indigo me-3"><i class="ti ti-user"></i></span>
            <div>
              <div class="text-muted">Conductores</div>
              <div class="h3 m-0">{{ (int)$driversAssigned }}</div>
              <div class="text-muted small">
                Reclutados: {{ (int)$driversRecruited }}
              </div>
              <div class="text-muted small">
                Idle: {{ (int)($driversByStatus['idle'] ?? 0) }}
                · Busy: {{ (int)($driversByStatus['busy'] ?? 0) }}
                · OnRide: {{ (int)($driversByStatus['on_ride'] ?? 0) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="card card-sm">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <span class="avatar bg-orange-lt text-orange me-3"><i class="ti ti-car"></i></span>
            <div>
              <div class="text-muted">Vehículos</div>
              <div class="h3 m-0">{{ (int)$vehiclesAssigned }}</div>
              <div class="text-muted small">
                Activos: {{ (int)$vehiclesActive }} · Reclutados: {{ (int)$vehiclesRecruited }}
              </div>
              <div class="text-muted small">
                Cargos impagos: {{ $money($chargesStats->unpaid_total ?? 0) }}
              </div>
              <div class="text-muted small">Último cargo: {{ $lastChargeAt ?: '—' }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div class="row row-cards">

    {{-- Perfil / datos --}}
    <div class="col-12 col-lg-4">
      <div class="card">
        <div class="card-header">
          <div class="card-title mb-0"><i class="ti ti-id me-1"></i> Perfil</div>
        </div>
        <div class="card-body">
          <div class="mb-2">
            <div class="text-muted small">Contacto</div>
            <div class="fw-semibold">{{ $partner->contact_name ?: '—' }}</div>
            <div class="text-muted small">
              {{ $partner->contact_phone ?: '—' }} · {{ $partner->contact_email ?: '—' }}
            </div>
          </div>

          <div class="mb-2">
            <div class="text-muted small">Dirección</div>
            <div class="fw-semibold">{{ $partner->address_line1 ?: '—' }}</div>
            <div class="text-muted small">
              {{ $partner->city ?: '—' }}, {{ $partner->state ?: '—' }} · {{ $partner->country ?: '—' }} · CP {{ $partner->postal_code ?: '—' }}
            </div>
          </div>

          <div class="mb-2">
            <div class="text-muted small">Pagos</div>
            <div class="text-muted small">
              Banco: <span class="fw-semibold text-body">{{ $partner->payout_bank ?: '—' }}</span><br>
              Benef.: <span class="fw-semibold text-body">{{ $partner->payout_beneficiary ?: '—' }}</span><br>
              Cuenta: <span class="fw-semibold text-body">{{ $partner->payout_account ?: '—' }}</span><br>
              CLABE: <span class="fw-semibold text-body">{{ $partner->payout_clabe ?: '—' }}</span>
            </div>
          </div>

          <div class="mb-0">
            <div class="text-muted small">Notas</div>
            <div class="text-muted">{{ $partner->notes ?: '—' }}</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Movimientos wallet --}}
    <div class="col-12 col-lg-8">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="card-title mb-0"><i class="ti ti-wallet me-1"></i> Wallet · Movimientos recientes</div>
          <div class="text-muted small">Mostrando últimos {{ count($movements) }}</div>
        </div>

        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>Dirección</th>
                <th>Monto</th>
                <th>Balance después</th>
                <th>Ref</th>
                <th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              @forelse($movements as $m)
                <tr>
                  <td class="text-muted">#{{ $m->id }}</td>
                  <td class="fw-semibold">{{ $m->type }}</td>
                  <td>
                    <span class="badge {{ $movDirBadge($m->direction) }}">{{ strtoupper($m->direction ?? '—') }}</span>
                  </td>
                  <td class="fw-semibold">
                    {{ ($m->direction === 'debit' ? '-' : '+') }}{{ $money($m->amount) }}
                  </td>
                  <td class="text-muted">{{ $money($m->balance_after ?? 0) }}</td>
                  <td class="text-muted">
                    {{ $m->ref_type ? ($m->ref_type.'#'.$m->ref_id) : '—' }}
                    @if(!empty($m->external_ref))
                      <div class="text-muted small">{{ $m->external_ref }}</div>
                    @endif
                  </td>
                  <td class="text-muted small">{{ $m->created_at }}</td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-muted text-center py-4">Sin movimientos.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

      </div>
    </div>

    {{-- Drivers --}}
    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="card-title mb-0"><i class="ti ti-users me-1"></i> Conductores del partner</div>
          <div class="text-muted small">Últimos {{ count($lastDrivers) }}</div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Status</th>
                <th>Verificación</th>
                <th>Alta</th>
              </tr>
            </thead>
            <tbody>
              @forelse($lastDrivers as $d)
                <tr>
                  <td class="text-muted">#{{ $d->id }}</td>
                  <td>
                    <div class="fw-semibold">{{ $d->name }}</div>
                    <div class="text-muted small">{{ $d->phone ?: '—' }}</div>
                  </td>
                  <td><span class="badge {{ $driverBadge($d->status) }}">{{ strtoupper($d->status) }}</span></td>
                  <td class="text-muted">{{ $d->verification_status ?? '—' }}</td>
                  <td class="text-muted small">{{ $d->created_at ?? '—' }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-muted text-center py-4">Sin conductores asignados.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Vehicles --}}
    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <div class="card-title mb-0"><i class="ti ti-car me-1"></i> Vehículos del partner</div>
          <div class="text-muted small">Últimos {{ count($lastVehicles) }}</div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter card-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Económico</th>
                <th>Placa</th>
                <th>Modelo</th>
                <th>Activo</th>
                <th>Verificación</th>
              </tr>
            </thead>
            <tbody>
              @forelse($lastVehicles as $v)
                <tr>
                  <td class="text-muted">#{{ $v->id }}</td>
                  <td class="fw-semibold">{{ $v->economico }}</td>
                  <td class="text-muted">{{ $v->plate }}</td>
                  <td class="text-muted">
                    {{ trim(($v->brand ?? '').' '.($v->model ?? '')) ?: '—' }}
                    <div class="text-muted small">{{ $v->type ?: '—' }}</div>
                  </td>
                  <td>
                    @if((int)$v->active === 1)
                      <span class="badge bg-success-lt text-success">SI</span>
                    @else
                      <span class="badge bg-secondary-lt text-secondary">NO</span>
                    @endif
                  </td>
                  <td class="text-muted">{{ $v->verification_status ?? '—' }}</td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-muted text-center py-4">Sin vehículos asignados.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

</div>
@endsection
