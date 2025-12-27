@extends('layouts.admin')

@section('title','Reporte avanzado de clientes')

@section('content')
<div class="container-fluid p-0">

  <div class="d-flex align-items-start justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-1">Clientes · BI</h1>
      <div class="text-muted">Consumo, frecuencia, última actividad, clasificación (Bronze/Silver/Gold).</div>
    </div>
    <div class="text-end small text-muted">
      <div>{{ now()->format('d M Y H:i') }}</div>
      <div>Tenant: <strong>{{ $tenantId }}</strong></div>
    </div>
  </div>

  {{-- KPI Cards --}}
  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Rides (total)</div>
          <div class="h4 mb-0">{{ number_format((int)($kpis->rides_total ?? 0)) }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Finalizados</div>
          <div class="h4 mb-0">{{ number_format((int)($kpis->rides_finished ?? 0)) }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Cancelados</div>
          <div class="h4 mb-0">{{ number_format((int)($kpis->rides_canceled ?? 0)) }}</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm border-0">
        <div class="card-body">
          <div class="text-muted small">Consumo total (finalizados)</div>
          <div class="h4 mb-0">${{ number_format((float)($kpis->spent_total ?? 0), 2) }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="get" action="">
        <div class="col-12 col-md-4">
          <label class="form-label">Buscar (nombre / teléfono / email)</label>
          <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Ej. Juan, 229..., @gmail.com">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Desde</label>
          <input type="date" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Hasta</label>
          <input type="date" name="to" value="{{ request('to') }}" class="form-control">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Tier</label>
          <select name="tier" class="form-select">
            <option value="">Todos</option>
            <option value="gold"   @selected(request('tier')==='gold')>Gold</option>
            <option value="silver" @selected(request('tier')==='silver')>Silver</option>
            <option value="bronze" @selected(request('tier')==='bronze')>Bronze</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Orden</label>
          <select name="sort" class="form-select">
            <option value="spent_90"      @selected(request('sort','spent_90')==='spent_90')>Consumo 90d</option>
            <option value="finished_90"   @selected(request('sort')==='finished_90')>Frecuencia 90d</option>
            <option value="last_ride_at"  @selected(request('sort')==='last_ride_at')>Último ride</option>
            <option value="lifetime_spent"@selected(request('sort')==='lifetime_spent')>Consumo histórico</option>
            <option value="avg_ticket"    @selected(request('sort')==='avg_ticket')>Ticket prom.</option>
            <option value="total_rides"   @selected(request('sort')==='total_rides')>Rides total</option>
          </select>
          <input type="hidden" name="dir" value="{{ request('dir','desc') }}">
        </div>

        <div class="col-12 d-flex gap-2 mt-2">
          <button class="btn btn-primary"><i class="fa fa-search me-1"></i> Filtrar</button>
          <a class="btn btn-outline-secondary" href="{{ url()->current() }}"><i class="fa fa-rotate-left me-1"></i> Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  {{-- Table --}}
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Cliente</th>
              <th class="text-center">Tier</th>
              <th class="text-center">Rides</th>
              <th class="text-center">Finalizados</th>
              <th class="text-end">Consumo 90d</th>
              <th class="text-end">Consumo histórico</th>
              <th class="text-end">Ticket prom.</th>
              <th>Último ride</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
          @forelse($clients as $c)
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  @if(!empty($c->passenger_avatar_url))
                    <img src="{{ $c->passenger_avatar_url }}" alt="avatar" width="34" height="34" class="rounded-circle">
                  @else
                    <div class="rounded-circle bg-secondary-subtle d-flex align-items-center justify-content-center" style="width:34px;height:34px;">
                      <span class="small text-muted">👤</span>
                    </div>
                  @endif
                  <div>
                    <div class="fw-semibold">{{ $c->passenger_name ?? 'Sin nombre' }}</div>
                    <div class="text-muted small">
                      {{ $c->passenger_phone ?? 'Sin teléfono' }}
                      @if(!empty($c->passenger_email)) · {{ $c->passenger_email }} @endif
                      @if(!empty($c->is_corporate)) · <span class="badge bg-info">Corp</span> @endif
                    </div>
                    <div class="text-muted small">Ref: <code>{{ $c->passenger_ref }}</code></div>
                  </div>
                </div>
              </td>

              <td class="text-center">
                @php
                  $tier = $c->tier ?? 'bronze';
                  $cls = $tier==='gold' ? 'bg-warning text-dark' : ($tier==='silver' ? 'bg-secondary' : 'bg-dark');
                @endphp
                <span class="badge {{ $cls }}">{{ strtoupper($tier) }}</span>
              </td>

              <td class="text-center">{{ (int)$c->total_rides }}</td>
              <td class="text-center">{{ (int)$c->finished_rides }}</td>

              <td class="text-end">${{ number_format((float)$c->spent_90, 2) }}</td>
              <td class="text-end">${{ number_format((float)$c->lifetime_spent, 2) }}</td>
              <td class="text-end">${{ number_format((float)$c->avg_ticket, 2) }}</td>

              <td>
                <span class="text-muted small">{{ $c->last_ride_at ? \Carbon\Carbon::parse($c->last_ride_at)->format('d M Y H:i') : '-' }}</span>
              </td>

              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary"
                   href="{{ route('admin.reports.clients.show', $c->passenger_ref) }}">
                  Ver
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-muted py-4">Sin resultados.</td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $clients->links() }}
      </div>
    </div>
  </div>

</div>
@endsection
