@extends('layouts.sysadmin')

@section('title', 'Partner Topups · Tenant #'.$tenant->id.' · '.$tenant->name)

@push('styles')
<style>
  .stat-pill { border-radius: 999px; padding:.25rem .7rem; font-size:.75rem; }
  .tabular { font-variant-numeric: tabular-nums; }
  .tight td, .tight th { padding: .45rem .6rem; vertical-align: middle; }
  .btn-xs { padding: .15rem .45rem; font-size: .78rem; }
</style>
@endpush

@section('content')
<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Topups de Partners · {{ $tenant->name }}</h3>
      <div class="text-muted small">Tenant ID: {{ $tenant->id }}</div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.tenants.billing.show', $tenant) }}?tab=partners"
         class="btn btn-outline-secondary btn-sm">
        Volver a Partners
      </a>
    </div>
  </div>

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

  <div class="card mb-3">
    <div class="card-header"><strong>Filtros</strong></div>
    <div class="card-body">
      <form method="GET" action="{{ route('sysadmin.tenants.partner_topups.index', $tenant) }}" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label small">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="" @selected(($status ?? '')==='')>Todos</option>
            <option value="pending_review" @selected(($status ?? '')==='pending_review')>Pend. revisión</option>
            <option value="pending" @selected(($status ?? '')==='pending')>Pending</option>
            <option value="approved" @selected(($status ?? '')==='approved')>Approved</option>
            <option value="credited" @selected(($status ?? '')==='credited')>Credited</option>
            <option value="rejected" @selected(($status ?? '')==='rejected')>Rejected</option>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label small">Partner</label>
          <select name="partner_id" class="form-select form-select-sm">
            <option value="" @selected(($partnerId ?? '')==='')>Todos</option>
            @foreach($partners as $p)
              <option value="{{ $p->id }}" @selected((string)$p->id === (string)($partnerId ?? ''))>
                #{{ $p->id }} · {{ $p->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label small">Buscar (ref/payer/mp)</label>
          <input name="q" value="{{ $q ?? '' }}" class="form-control form-control-sm"
                 placeholder="external_reference, bank_ref, payer_name, mp_payment_id...">
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
          <button class="btn btn-primary btn-sm w-100">Aplicar</button>
          <a class="btn btn-outline-secondary btn-sm w-100"
             href="{{ route('sysadmin.tenants.partner_topups.index', $tenant) }}">
            Limpiar
          </a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Topups ({{ method_exists($items,'total') ? $items->total() : '' }})</strong>
      <span class="text-muted small">Orden: pendientes primero</span>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-striped tight tabular mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Partner</th>
              <th>Provider</th>
              <th>Status</th>
              <th class="text-end">Monto</th>
              <th>Refs</th>
              <th>Fecha</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          @forelse($items as $t)
            @php
              $st = strtolower((string)($t->status ?? 'pending'));
              $badge = match($st) {
                'pending_review' => 'warning',
                'pending'        => 'secondary',
                'approved'       => 'info',
                'credited'       => 'success',
                'rejected'       => 'danger',
                default          => 'secondary',
              };
            @endphp
            <tr>
              <td>{{ $t->id }}</td>
              <td>
                #{{ $t->partner_id }}
                @if(!empty($t->partner_name))
                  · {{ $t->partner_name }}
                @endif
              </td>
              <td>{{ $t->provider ?? '—' }}{{ $t->method ? ' / '.$t->method : '' }}</td>
              <td><span class="badge bg-{{ $badge }}">{{ strtoupper($st) }}</span></td>
              <td class="text-end">{{ number_format((float)$t->amount,2) }} {{ $t->currency ?? 'MXN' }}</td>
              <td class="small">
                <div>ext: {{ $t->external_reference ?? '—' }}</div>
                <div>bank: {{ $t->bank_ref ?? '—' }}</div>
              </td>
              <td class="small">{{ $t->created_at ?? '—' }}</td>
              <td class="text-end d-flex justify-content-end gap-1 flex-wrap">
                <a class="btn btn-outline-secondary btn-xs"
                   href="{{ route('sysadmin.tenants.partner_topups.show', [$tenant, $t]) }}">
                  Ver
                </a>

                @if(($t->status ?? '') === 'pending_review')
                  <form method="POST"
                        action="{{ route('sysadmin.tenants.partner_topups.approve', [$tenant, $t]) }}"
                        onsubmit="return confirm('¿Aprobar y acreditar este topup?');">
                    @csrf
                    <button class="btn btn-success btn-xs">Approve</button>
                  </form>

                  <form method="POST"
                        action="{{ route('sysadmin.tenants.partner_topups.reject', [$tenant, $t]) }}"
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

    @if(method_exists($items,'links'))
      <div class="card-footer">
        {{ $items->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
