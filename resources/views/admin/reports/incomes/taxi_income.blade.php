@extends('layouts.admin')

@section('title', 'Ingresos por cobros a taxis')

@section('content')
<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="mb-0">Ingresos por cobros a taxis</h1>
      <div class="text-muted">Suma de cargos pagados (status=paid) filtrado por <b>paid_at</b>.</div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary"
         href="{{ route('admin.reports.incomes.taxi_income.csv', request()->query()) }}">
        Exportar CSV
      </a>
    </div>
  </div>

  {{-- Filtros --}}
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-2">
          <label class="form-label">Desde</label>
          <input type="date" name="from" value="{{ request('from', $from) }}" class="form-control">
        </div>

        <div class="col-md-2">
          <label class="form-label">Hasta</label>
          <input type="date" name="to" value="{{ request('to', $to) }}" class="form-control">
        </div>

        <div class="col-md-2">
          <label class="form-label">Periodo</label>
          <select name="period_type" class="form-select">
            <option value="">Todos</option>
            @foreach(['weekly'=>'Semanal','biweekly'=>'Quincenal','monthly'=>'Mensual'] as $k=>$lbl)
              <option value="{{ $k }}" @selected(request('period_type')===$k)>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Vehículo</label>
          <select name="vehicle_id" class="form-select">
            <option value="">Todos</option>
            @foreach($vehiclesForSelect as $v)
              @php
                $labelParts = [];
                if (!empty($v->economico)) $labelParts[] = "Econ. {$v->economico}";
                if (!empty($v->plate)) $labelParts[] = $v->plate;
                $bm = trim(($v->brand ?? '') . ' ' . ($v->model ?? ''));
                if (!empty($bm)) $labelParts[] = $bm;
                if (!empty($v->type)) $labelParts[] = $v->type;
                $label = implode(' · ', $labelParts) ?: ("Vehículo #{$v->id}");
              @endphp
              <option value="{{ $v->id }}" @selected((string)request('vehicle_id') === (string)$v->id)>{{ $label }}</option>
            @endforeach
          </select>
          <div class="text-muted small">Solo vehículos que ya tienen cobros generados.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Conductor</label>
          <select name="driver_id" class="form-select">
            <option value="">Todos</option>
            @foreach($driversForSelect as $d)
              @php
                $label = $d->name ?: "Driver #{$d->id}";
                if (!empty($d->phone)) $label .= " · {$d->phone}";
              @endphp
              <option value="{{ $d->id }}" @selected((string)request('driver_id') === (string)$d->id)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-12 d-flex align-items-end gap-2 mt-2">
          <button class="btn btn-primary">Filtrar</button>
          <a class="btn btn-outline-secondary" href="{{ route('admin.reports.incomes.taxi_income') }}">Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Cobrado (rango)</div>
          <div class="h2 mb-0">${{ number_format($kpiPaidTotal, 2) }}</div>
          <div class="text-muted small">{{ $kpiPaidCount }} pagos</div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Pendiente (acumulado)</div>
          <div class="h2 mb-0">${{ number_format($pendingTotal, 2) }}</div>
          <div class="text-muted small">status=pending</div>
        </div>
      </div>
    </div>

    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted">Cancelado (acumulado)</div>
          <div class="h2 mb-0">${{ number_format($canceledTotal, 2) }}</div>
          <div class="text-muted small">status=canceled</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Serie mensual --}}
  <div class="card mb-3">
    <div class="card-header">
      <div class="fw-semibold">Cobrado por mes</div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th style="width: 160px;">Mes</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
          @forelse($seriesMonthly as $r)
            <tr>
              <td>{{ $r->ym }}</td>
              <td>${{ number_format((float)$r->total, 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="2" class="text-muted text-center p-3">Sin datos en el rango.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Detalle --}}
  <div class="card">
    <div class="card-header">
      <div class="fw-semibold">Detalle de cargos pagados</div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-vcenter table-striped mb-0">
          <thead>
            <tr>
              <th style="width: 90px;">Cargo</th>
              <th style="width: 140px;">Pagado</th>
              <th style="width: 120px;">Monto</th>
              <th style="width: 110px;">Periodo</th>
              <th style="width: 210px;">Rango</th>
              <th>Vehículo</th>
              <th>Conductor</th>
              <th style="width: 180px;">Recibo</th>
            </tr>
          </thead>
          <tbody>
          @forelse($rows as $c)
            @php
              $vehLine1 = [];
              if (!empty($c->vehicle_economico)) $vehLine1[] = "Econ. {$c->vehicle_economico}";
              if (!empty($c->vehicle_plate)) $vehLine1[] = $c->vehicle_plate;

              $vehLine2 = trim(($c->vehicle_brand ?? '').' '.($c->vehicle_model ?? ''));
              $vehLine2 = $vehLine2 ?: null;

              $driverLine1 = $c->driver_name ?: null;
              $driverLine2 = $c->driver_phone ?: null;
            @endphp

            <tr>
              <td class="fw-semibold">#{{ $c->id }}</td>
              <td class="text-muted">
                {{ $c->paid_at ? \Illuminate\Support\Carbon::parse($c->paid_at)->format('Y-m-d H:i') : '—' }}
              </td>
              <td>${{ number_format((float)$c->amount, 2) }}</td>
              <td><span class="badge bg-azure">{{ $c->period_type }}</span></td>
              <td class="text-muted">{{ $c->period_start }} → {{ $c->period_end }}</td>

              <td>
                @if(!empty($vehLine1) || $vehLine2)
                  <div class="fw-semibold">{{ implode(' · ', $vehLine1) }}</div>
                  @if($vehLine2)<div class="text-muted small">{{ $vehLine2 }} @if($c->vehicle_type) · {{ $c->vehicle_type }} @endif</div>@endif
                @else
                  <span class="text-muted">Vehículo #{{ $c->vehicle_id ?? '—' }}</span>
                @endif
              </td>

              <td>
                @if($driverLine1)
                  <div class="fw-semibold">{{ $driverLine1 }}</div>
                  @if($driverLine2)<div class="text-muted small">{{ $driverLine2 }}</div>@endif
                @else
                  <span class="text-muted">Driver #{{ $c->driver_id ?? '—' }}</span>
                @endif
              </td>

              <td>
                @if($c->receipt_number)
                  <span class="fw-semibold">{{ $c->receipt_number }}</span>
                  <div class="text-muted small">{{ $c->issued_at }}</div>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-muted text-center p-3">No hay cargos pagados en el rango.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card-footer">
      {{ $rows->links() }}
    </div>
  </div>

</div>
@endsection
