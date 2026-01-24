@extends('layouts.partner')

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Cuenta</div>
        <h2 class="page-title">Soporte / Solicitudes</h2>
        <div class="text-muted">Canal de comunicación con tu tenant (solicitudes, bugs, sugerencias).</div>
      </div>

      <div class="col-auto ms-auto d-flex gap-2">
        <a class="btn btn-primary" href="{{ route('partner.support.create') }}">
          <i class="ti ti-plus me-1"></i> Nueva solicitud
        </a>
      </div>
    </div>
  </div>

  {{-- filtros --}}
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('partner.support.index') }}" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <input type="text" class="form-control" name="q" value="{{ request('q') }}" placeholder="Asunto">
        </div>

        <div class="col-md-3">
          <label class="form-label">Categoría</label>
          <select class="form-select" name="category">
            <option value="">Todas</option>
            @foreach([
              'taxi_stand'=>'TaxiStand / Bases',
              'tariff'=>'Tarifas',
              'bug'=>'Bug / Inconsistencia',
              'suggestion'=>'Sugerencia',
              'other'=>'Otro'
            ] as $k=>$v)
              <option value="{{ $k }}" {{ request('category')===$k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Estado</label>
          <select class="form-select" name="status">
            <option value="">Todos</option>
            @foreach(['open'=>'Abierto','in_progress'=>'En proceso','resolved'=>'Resuelto','closed'=>'Cerrado'] as $k=>$v)
              <option value="{{ $k }}" {{ request('status')===$k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-primary w-100">
            <i class="ti ti-filter me-1"></i> Filtrar
          </button>
          <a class="btn btn-outline-secondary w-100" href="{{ route('partner.support.index') }}">
            Limpiar
          </a>
        </div>
      </form>
    </div>
  </div>

  {{-- tabla --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Solicitudes</div>
      <div class="text-muted small">Ordenadas por última actividad</div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th>Asunto</th>
            <th style="width:160px;">Categoría</th>
            <th style="width:140px;">Estado</th>
            <th style="width:140px;">Prioridad</th>
            <th style="width:170px;">Último mensaje</th>
            <th style="width:110px;"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($threads as $t)
            @php
              $st = (string)$t->status;
              $stBadge = match($st){
                'open'        => 'bg-red-lt text-red',
                'in_progress' => 'bg-yellow-lt text-yellow',
                'resolved'    => 'bg-green-lt text-green',
                'closed'      => 'bg-secondary-lt text-secondary',
                default       => 'bg-secondary-lt text-secondary',
              };

              $prio = (string)($t->priority ?? 'normal');
              $pBadge = match($prio){
                'urgent' => 'bg-red-lt text-red',
                'high'   => 'bg-orange-lt text-orange',
                'low'    => 'bg-secondary-lt text-secondary',
                default  => 'bg-blue-lt text-blue',
              };

              $cat = (string)($t->category ?? 'other');
              $catLabel = [
                'taxi_stand'=>'TaxiStand',
                'tariff'=>'Tarifas',
                'bug'=>'Bug',
                'suggestion'=>'Sugerencia',
                'other'=>'Otro',
              ][$cat] ?? $cat;

              $lastAt = $t->last_message_at ?: $t->updated_at;
              $lastAtFmt = $lastAt ? \Carbon\Carbon::parse($lastAt)->format('Y-m-d H:i') : '-';
            @endphp

            <tr>
              <td>
                <div class="fw-semibold">{{ $t->subject }}</div>
                <div class="text-muted small">#{{ $t->id }}</div>
              </td>
              <td class="text-muted">{{ $catLabel }}</td>
              <td><span class="badge {{ $stBadge }}">{{ $st }}</span></td>
              <td><span class="badge {{ $pBadge }}">{{ $prio }}</span></td>
              <td class="text-muted">{{ $lastAtFmt }}</td>
              <td class="text-end">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('partner.support.show', $t->id) . '?' . http_build_query(request()->query()) }}">
                  Ver
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-muted p-3">No hay tickets con los filtros actuales.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $threads->links() }}
    </div>
  </div>

</div>
@endsection
