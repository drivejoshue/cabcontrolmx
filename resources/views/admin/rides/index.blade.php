@extends('layouts.admin')
@section('title','Corridas')

@section('content')
<div class="row g-3">

  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <div>
          <h3 class="card-title mb-1">Corridas</h3>
          <div class="text-muted">Consulta y filtra las corridas del tenant.</div>
        </div>
        <div class="text-end small text-muted">
          <div>{{ now()->format('d M Y H:i') }}</div>
          <div>Tenant: <strong>{{ auth()->user()->tenant_id ?? '-' }}</strong></div>
        </div>
      </div>
    </div>
  </div>

  {{-- FILTROS --}}
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form class="row g-2 align-items-end" method="GET" action="{{ route('admin.rides.index') }}">
          <div class="col-md-3">
            <label class="form-label mb-1">Estatus</label>
            <select name="status" class="form-select form-select-sm">
              @php $st = request('status'); @endphp
              <option value="">Todos</option>
              @foreach(['requested','accepted','en_route','arrived','on_board','finished','canceled','scheduled'] as $s)
                <option value="{{ $s }}" @selected($st === $s)>{{ strtoupper($s) }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label mb-1">Teléfono pasajero</label>
            <input type="text" class="form-control form-control-sm" name="phone" value="{{ request('phone') }}" placeholder="Ej. 229...">
          </div>

          <div class="col-md-3">
            <label class="form-label mb-1">Fecha</label>
            <input type="date" class="form-control form-control-sm" name="date" value="{{ request('date') }}">
          </div>

          <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm w-100" type="submit">
              <i class="bi bi-search me-1"></i> Filtrar
            </button>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.rides.index') }}">
              Limpiar
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- TABLA --}}
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <div class="fw-semibold">Listado</div>
        <div class="text-muted small">Mostrando {{ $rides->count() }} de {{ $rides->total() }}</div>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Pasajero</th>
                <th>Conductor</th>
                <th>Estatus</th>
                <th>Origen</th>
                <th>Destino</th>
                <th class="text-end">Monto</th>
                <th class="text-end">Creado</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              @forelse($rides as $r)
                @php
                  $st = $r->status ?? 'unknown';
                  $badge = match($st) {
                    'requested' => 'bg-secondary',
                    'accepted'  => 'bg-info',
                    'en_route'  => 'bg-warning text-dark',
                    'arrived'   => 'bg-warning text-dark',
                    'on_board'  => 'bg-primary',
                    'scheduled' => 'bg-dark',
                    'finished'  => 'bg-success',
                    'canceled'  => 'bg-danger',
                    default     => 'bg-light text-dark',
                  };
                  $amt = $r->total_amount ?? $r->quoted_amount ?? 0;
                @endphp
                <tr>
                  <td class="text-muted">#{{ $r->id }}</td>
                  <td class="fw-semibold">{{ $r->passenger_name ?? 'N/A' }}</td>
                  <td>{{ $r->driver_name ?? '—' }}</td>
                  <td><span class="badge {{ $badge }}">{{ strtoupper($st) }}</span></td>
                  <td><span class="text-truncate d-inline-block" style="max-width: 220px;">{{ $r->origin_label ?? '—' }}</span></td>
                  <td><span class="text-truncate d-inline-block" style="max-width: 220px;">{{ $r->dest_label ?? '—' }}</span></td>
                  <td class="text-end">${{ number_format((float)$amt, 2) }}</td>
                  <td class="text-end text-muted">{{ \Carbon\Carbon::parse($r->created_at)->format('d M H:i') }}</td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.rides.show', $r->id) }}">
                      Ver
                    </a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No hay corridas con esos filtros</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      @if($rides->hasPages())
       <div class="d-flex justify-content-center justify-content-md-end">
  {{ $rides->onEachSide(1)->links() }}
</div>

      @endif

    </div>
  </div>

</div>
@endsection

@push('styles')
<style>
  .table td, .table th { vertical-align: middle; }

  /* Paginación compacta (Bootstrap 5) */
  .pagination { margin: 0; gap: .25rem; flex-wrap: wrap; }
  .page-link  { padding: .25rem .55rem; font-size: .85rem; line-height: 1.2; }
</style>
@endpush
