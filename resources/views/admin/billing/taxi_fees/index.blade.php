@extends('layouts.admin')
@section('title','Cuotas por taxi')

@section('content')
<div class="container-fluid p-0">
  <div class="d-flex align-items-start justify-content-between mb-3">
    <div>
      <h1 class="h3 mb-1">Cuotas por taxi</h1>
      <div class="text-muted">Define cuánto cobra la base por unidad (no depende de rides).</div>
    </div>
  </div>

  <div class="card mb-3">
  <div class="card-body">
    <h3 class="card-title mb-1">Cómo usar esta herramienta</h3>
    <div class="text-muted">
      Define la <strong>cuota fija</strong> que cada taxi debe pagar a la central por periodo (no depende de rides).
    </div>

    <ol class="mt-3 mb-0">
      <li><strong>Crea una cuota</strong> seleccionando el taxi y el periodo (semanal/quincenal/mensual).</li>
      <li>Deja la cuota en <strong>Activa</strong> para que el generador la tome.</li>
      <li>Después ve a <strong>Cobros por taxi</strong> y presiona <strong>Generar cobros del periodo</strong>.</li>
    </ol>

    <div class="alert alert-info mt-3 mb-0">
      Consejo: Mantén <strong>una sola cuota activa</strong> por taxi y periodo para evitar confusión.
    </div>
  
  </div>
</div>


  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  <div class="card shadow-sm border-0 mb-3">


    <div class="card-body">
      <h6 class="mb-2">Crear cuota</h6>
      <form method="post" action="{{ route('admin.taxi_fees.update', 0) }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-12 col-md-4">
          <label class="form-label">Taxi (vehículo)</label>
          <select name="vehicle_id" class="form-select">
            <option value="">(Opcional)</option>
            @foreach($vehicles as $v)
              <option value="{{ $v->id }}">
                {{ $v->economico ? 'Econ '.$v->economico : ('Vehículo #'.$v->id) }} · {{ $v->plate }} · {{ $v->brand }} {{ $v->model }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Conductor (opcional)</label>
          <select name="driver_id" class="form-select">
            <option value="">(Opcional)</option>
            @foreach($drivers as $d)
              <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Periodo</label>
          <select name="period_type" class="form-select">
            <option value="weekly">Semanal</option>
            <option value="biweekly">Quincenal</option>
            <option value="monthly">Mensual</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Monto</label>
          <input name="amount" type="number" step="0.01" min="0" class="form-control" required>
        </div>
        <div class="col-12 col-md-1">
          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" name="active" value="1" checked>
            <label class="form-check-label">Act.</label>
          </div>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <h6 class="mb-2">Cuotas existentes</h6>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Taxi</th>
              <th>Conductor</th>
              <th class="text-center">Periodo</th>
              <th class="text-end">Monto</th>
              <th class="text-center">Activa</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
            @forelse($fees as $f)
              <tr>
                <td>
                  <div class="fw-semibold">
                    {{ $f->vehicle_economico ? 'Econ '.$f->vehicle_economico : ($f->vehicle_id ? 'Vehículo #'.$f->vehicle_id : '-') }}
                    @if($f->vehicle_plate) · {{ $f->vehicle_plate }} @endif
                  </div>
                  <div class="text-muted small">{{ trim(($f->vehicle_brand ?? '').' '.($f->vehicle_model ?? '')) }}</div>
                </td>
                <td>{{ $f->driver_name ?? '-' }}</td>
                <td class="text-center"><span class="badge bg-secondary">{{ $f->period_type }}</span></td>
                <td class="text-end">${{ number_format((float)$f->amount,2) }}</td>
                <td class="text-center">
                  @if($f->active) <span class="badge bg-success">Sí</span>
                  @else <span class="badge bg-dark">No</span> @endif
                </td>
                <td class="text-end">
                  <form method="post" action="{{ route('admin.taxi_fees.update', $f->id) }}" class="d-inline-flex gap-2">
                    @csrf
                    <input type="hidden" name="vehicle_id" value="{{ $f->vehicle_id }}">
                    <input type="hidden" name="driver_id" value="{{ $f->driver_id }}">
                    <input type="hidden" name="period_type" value="{{ $f->period_type }}">
                    <input type="number" step="0.01" min="0" name="amount" value="{{ $f->amount }}" class="form-control form-control-sm" style="width:120px;">
                    <div class="form-check mt-1">
                      <input class="form-check-input" type="checkbox" name="active" value="1" @checked($f->active)>
                    </div>
                    <button class="btn btn-sm btn-outline-primary">Actualizar</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">Sin cuotas.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">{{ $fees->links() }}</div>
    </div>
  </div>
</div>
@endsection
