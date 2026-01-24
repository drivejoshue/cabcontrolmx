@extends('layouts.admin') {{-- o tu layout de tenant --}}

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Soporte</div>
        <h2 class="page-title">Tickets de Partners</h2>
        <div class="text-muted">Inbox de solicitudes y reportes creados por partners.</div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Abiertos</div>
        <div class="fs-3 fw-bold">{{ (int)($kpi['open'] ?? 0) }}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <div class="text-muted small">En revisión</div>
        <div class="fs-3 fw-bold">{{ (int)($kpi['in_review'] ?? 0) }}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Cerrados</div>
        <div class="fs-3 fw-bold">{{ (int)($kpi['closed'] ?? 0) }}</div>
      </div></div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <input class="form-control" name="q" value="{{ request('q') }}" placeholder="asunto o categoría">
        </div>

        <div class="col-md-3">
          <label class="form-label">Partner</label>
          <select class="form-select" name="partner_id">
            <option value="">Todos</option>
            @foreach($partners as $p)
              <option value="{{ $p->id }}" {{ (string)request('partner_id')===(string)$p->id ? 'selected':'' }}>
                {{ $p->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <select class="form-select" name="status">
            <option value="">Todos</option>
            @foreach(['open'=>'Abierto','in_review'=>'En revisión','closed'=>'Cerrado'] as $k=>$v)
              <option value="{{ $k }}" {{ request('status')===$k ? 'selected':'' }}>{{ $v }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-primary w-100"><i class="ti ti-filter me-1"></i>Filtrar</button>
          <a class="btn btn-outline-secondary w-100" href="{{ route('admin.partner_support.index') }}"><i class="ti ti-x"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th>Partner</th>
            <th>Asunto</th>
            <th>Categoría</th>
            <th>Estado</th>
            <th class="text-muted">Último</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $t)
            @php
              $st = (string)$t->status;
              $badge = match($st) {
                'open' => 'bg-red-lt text-red',
                'in_review' => 'bg-yellow-lt text-yellow',
                default => 'bg-green-lt text-green',
              };
              $last = !empty($t->last_msg_at) ? \Carbon\Carbon::parse($t->last_msg_at)->format('Y-m-d H:i') : '-';
            @endphp
            <tr>
              <td class="fw-semibold">{{ $t->partner_name ?? ('#'.$t->partner_id) }}</td>
              <td>{{ $t->subject ?? '—' }}</td>
              <td class="text-muted">{{ $t->category ?? '—' }}</td>
              <td><span class="badge {{ $badge }}">{{ $st }}</span></td>
              <td class="text-muted">{{ $last }}</td>
              <td class="text-end">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.partner_support.show', $t->id) }}">
                  Ver
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-muted p-3">Sin tickets.</td></tr>
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
