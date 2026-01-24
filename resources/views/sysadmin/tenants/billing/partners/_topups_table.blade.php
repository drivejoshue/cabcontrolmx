{{-- resources/views/sysadmin/tenants/billing/partners/show.blade.php --}}
@extends('layouts.sysadmin')

@section('title', 'Partner Billing · Tenant #'.$tenant->id.' · Partner #'.$partner->id)

@push('styles')
<style>
  .stat-pill { border-radius: 999px; padding:.25rem .7rem; font-size:.75rem; }
  .tabular { font-variant-numeric: tabular-nums; }
  .tight td, .tight th { padding: .45rem .6rem; vertical-align: middle; }
  details > summary { cursor:pointer; }
  .btn-xs { padding: .15rem .45rem; font-size: .78rem; }
</style>
@endpush

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">
        Partner: {{ $partner->name ?? ('#'.$partner->id) }}
        <span class="badge bg-secondary stat-pill align-middle">Partner #{{ $partner->id }}</span>
        <span class="badge bg-primary stat-pill align-middle">Tenant #{{ $tenant->id }}</span>
      </h3>
      <div class="text-muted small">
        Tenant: {{ $tenant->name }}
        · Partner status: {{ strtoupper((string)($partner->status ?? 'active')) }}
        · Email: {{ $partner->email ?? '—' }}
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.tenants.billing.show', $tenant) }}?tab=partners"
         class="btn btn-outline-secondary btn-sm">
        Volver a partners
      </a>

      {{-- NOTA: NO usamos rutas globales de topups (partner_topups.index) aquí.
           Esta pantalla es SOLO del partner. --}}
      <a href="#topups" class="btn btn-outline-primary btn-sm">
        Ver topups
      </a>
    </div>
  </div>

  {{-- Flash --}}
  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  @php
    // Defensivos
    $wallet    = $wallet ?? null;
    $movements = $movements ?? ($movementItems ?? collect());
    $topups    = $topups ?? ($topupItems ?? collect());

    $bal = (float)($wallet->balance ?? 0);
    $cur = (string)($wallet->currency ?? 'MXN');

    // Filtro local de status (opcional, depende de tu controller)
    $status = request('status', '');
  @endphp

  <div class="row g-3">

    {{-- Wallet --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header"><strong>Wallet del partner</strong></div>
        <div class="card-body">
          <div class="display-6 tabular mb-2">
            ${{ number_format($bal, 2) }} <span class="fs-6">{{ $cur }}</span>
          </div>
          <div class="text-muted small">updated_at: {{ $wallet->updated_at ?? '—' }}</div>
          <div class="text-muted small">last_topup_at: {{ $wallet->last_topup_at ?? '—' }}</div>

          <hr>

          <div class="alert alert-info small mb-0">
            Operación: los topups del partner se <strong>validan manualmente</strong> (approve/reject).
          </div>
        </div>
      </div>
    </div>

    {{-- Info partner --}}
    <div class="col-12 col-lg-8">
      <div class="card h-100">
        <div class="card-header"><strong>Información</strong></div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-3">Partner</dt><dd class="col-9">{{ $partner->name ?? '—' }}</dd>
            <dt class="col-3">Email</dt><dd class="col-9">{{ $partner->email ?? '—' }}</dd>
            <dt class="col-3">Teléfono</dt><dd class="col-9">{{ $partner->phone ?? '—' }}</dd>
            <dt class="col-3">Status</dt><dd class="col-9">{{ strtoupper((string)($partner->status ?? 'active')) }}</dd>
            <dt class="col-3">Creado</dt><dd class="col-9">{{ $partner->created_at ?? '—' }}</dd>
          </dl>

          @if(!empty($partner->notes))
            <hr>
            <div class="small">
              <strong>Notas:</strong><br>
              <div class="text-muted" style="white-space: pre-wrap;">{{ $partner->notes }}</div>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Topups --}}
    <div class="col-12" id="topups">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Topups del partner</strong>

          {{-- Filtro local (misma URL / mismo show) --}}
          <form method="GET"
                action="{{ route('sysadmin.tenants.partners.billing.show', [$tenant, $partner]) }}"
                class="d-flex gap-2 align-items-center">
            <select name="status" class="form-select form-select-sm" style="max-width:220px;">
              <option value="" @selected($status==='')>Todos</option>
              <option value="pending" @selected($status==='pending')>Pendientes</option>
              <option value="pending_review" @selected($status==='pending_review')>Pend. revisión</option>
              <option value="approved" @selected($status==='approved')>Aprobados</option>
              <option value="credited" @selected($status==='credited')>Acreditados</option>
              <option value="rejected" @selected($status==='rejected')>Rechazados</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm">Filtrar</button>
          </form>
        </div>

        <div class="card-body">
          @include('sysadmin.tenants.billing.partners._topups_table', [
            'tenant'  => $tenant,
            'partner' => $partner,
            'topups'  => $topups
          ])
        </div>

        @if(method_exists($topups, 'links'))
          <div class="card-footer">
            {{ $topups->withQueryString()->links() }}
          </div>
        @endif
      </div>
    </div>

    {{-- Movements --}}
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Movimientos de wallet (últimos)</strong>

          {{-- Si tus movimientos son paginator, puedes usar links abajo. --}}
        </div>

        <div class="card-body p-0">
         @php
  $topups = $topups ?? $items ?? collect();
@endphp

<div class="table-responsive">
  <table class="table table-sm table-striped tight tabular mb-0">
    <thead>
      <tr>
        <th>#</th>
        <th>Partner</th>
        <th>Status</th>
        <th class="text-end">Monto</th>
        <th>Refs</th>
        <th>Comprobante</th>
        <th>Fecha</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
    @forelse($topups as $t)
      @php
        $st = strtolower((string)($t->status ?? 'pending_review'));
        $badge = match($st) {
          'pending_review','pending' => 'warning',
          'approved' => 'info',
          'credited' => 'success',
          'rejected' => 'danger',
          default => 'secondary',
        };
        $hasProof = !empty($t->proof_path);
      @endphp

      <tr>
        <td>{{ $t->id }}</td>
        <td>
          #{{ $t->partner_id }}
          @if(!empty($t->partner_name)) · {{ $t->partner_name }} @endif
        </td>
        <td><span class="badge bg-{{ $badge }}">{{ strtoupper($st) }}</span></td>
        <td class="text-end">{{ number_format((float)$t->amount,2) }} {{ $t->currency ?? 'MXN' }}</td>
        <td class="small">
          <div>ext: {{ $t->external_reference ?? '—' }}</div>
          <div>bank: {{ $t->bank_ref ?? '—' }}</div>
        </td>
        <td>
          @if($hasProof)
            <span class="badge bg-success">Sí</span>
          @else
            <span class="badge bg-secondary">No</span>
          @endif
        </td>
        <td class="small">{{ $t->created_at ?? '—' }}</td>
        <td class="text-end d-flex justify-content-end gap-1 flex-wrap">
          <a class="btn btn-outline-secondary btn-xs"
             href="{{ route('sysadmin.tenants.partner_topups.show', [$tenant, $t->id]) }}">
            Ver
          </a>

          @if(in_array($st, ['pending_review','pending'], true))
            <form method="POST"
                  action="{{ route('sysadmin.tenants.partner_topups.approve', [$tenant, $t->id]) }}"
                  onsubmit="return confirm('¿Aprobar y acreditar este topup?');">
              @csrf
              <button class="btn btn-success btn-xs">Approve</button>
            </form>

            <form method="POST"
                  action="{{ route('sysadmin.tenants.partner_topups.reject', [$tenant, $t->id]) }}"
                  onsubmit="return confirm('¿Rechazar este topup?');">
              @csrf
              <input type="hidden" name="review_notes" value="Rechazado por SysAdmin.">
              <button class="btn btn-outline-danger btn-xs">Reject</button>
            </form>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="8" class="text-muted">Sin topups.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

        </div>

        @if(method_exists($movements, 'links'))
          <div class="card-footer">
            {{ $movements->withQueryString()->links() }}
          </div>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection
