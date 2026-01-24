@extends('layouts.admin')

@section('title','Recargas de Partners')

@section('content')
@php
  $status    = request('status','');
  $partnerId = request('partner_id','');
  $q         = request('q','');

  $badgeClass = function($s) {
    $s = strtolower((string)$s);
    return match($s) {
      'pending_review','pending' => 'bg-warning-lt text-warning',
      'approved'                => 'bg-info-lt text-info',
      'credited'                => 'bg-success-lt text-success',
      'rejected'                => 'bg-danger-lt text-danger',
      default                   => 'bg-secondary-lt text-secondary',
    };
  };
@endphp

<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h3 mb-0">Recargas de Partners</h1>
      <div class="text-muted small">Historial de solicitudes (solo lectura en Admin).</div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.partners.index') }}">
        <i class="ti ti-users me-1"></i> Partners
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-center">

        {{-- TODO en una sola línea --}}
        <div class="col-12 col-lg-3">
          <label class="form-label mb-1">Estatus</label>
          <select name="status" class="form-select form-select-sm">
            <option value="" @selected($status==='')>Todos</option>
            <option value="pending_review" @selected($status==='pending_review')>Pendientes</option>
            <option value="approved" @selected($status==='approved')>Aprobadas</option>
            <option value="credited" @selected($status==='credited')>Acreditadas</option>
            <option value="rejected" @selected($status==='rejected')>Rechazadas</option>
          </select>
        </div>

        <div class="col-12 col-lg-3">
          <label class="form-label mb-1">Partner</label>
          <select name="partner_id" class="form-select form-select-sm">
            <option value="">Todos</option>
            @foreach($partners as $p)
              <option value="{{ $p->id }}" @selected((string)$partnerId === (string)$p->id)>
                {{ $p->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-12 col-lg-4">
          <label class="form-label mb-1">Buscar</label>
          <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text"
                   name="q"
                   class="form-control form-control-sm"
                   value="{{ $q }}"
                   placeholder="Referencia, external ref, monto…">
          </div>
        </div>

        <div class="col-12 col-lg-2">
          <label class="form-label mb-1 d-none d-lg-block">&nbsp;</label>
          <div class="d-flex gap-2 justify-content-lg-end">
            <button class="btn btn-primary btn-sm" type="submit">
              <i class="ti ti-filter me-1"></i> Filtrar
            </button>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.partner_topups.index') }}">
              Limpiar
            </a>
          </div>
        </div>

      </form>

      <div class="text-muted small mt-2">
        * Solo partners del tenant.
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="card-title mb-0">
        <i class="ti ti-receipt me-1"></i> Listado
      </div>
      <div class="text-muted small">
        Total: {{ method_exists($items,'total') ? $items->total() : count($items) }}
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th class="text-muted">ID</th>
            <th>Partner</th>
            <th>Monto</th>
            <th>Estatus</th>
            <th>Referencia</th>
            <th>Fecha</th>
            <th class="text-end">Acción</th>
          </tr>
        </thead>

        <tbody>
          @forelse($items as $t)
            <tr>
              <td class="text-muted">#{{ $t->id }}</td>

              <td>
                <div class="fw-semibold">{{ $t->partner_name ?: ('Partner #'.$t->partner_id) }}</div>
                <div class="text-muted small">
                  Proveedor: {{ $t->provider ?? '—' }} · Método: {{ $t->method ?? '—' }}
                </div>
              </td>

              <td class="fw-semibold">
                ${{ number_format((float)$t->amount,2) }} {{ $t->currency ?? 'MXN' }}
              </td>

              <td>
                <span class="badge {{ $badgeClass($t->status) }}">{{ strtoupper((string)$t->status) }}</span>
                @if(!empty($t->review_status))
                  <div class="text-muted small">Rev: {{ $t->review_status }}</div>
                @endif
              </td>

              <td class="text-muted">
                {{ $t->bank_ref ?: ($t->external_reference ?: '—') }}
              </td>

              <td class="text-muted small">
                {{ optional($t->created_at)->format('Y-m-d H:i') }}
              </td>

              <td class="text-end">
                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.partner_topups.show', $t->id) }}">
                  <i class="ti ti-eye me-1"></i> Ver
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-muted text-center py-4">Sin registros.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $items->links() }}
    </div>
  </div>

</div>
@endsection
