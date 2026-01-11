@extends('layouts.admin')
@section('title','Cobros por taxi')

@section('content')
<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-1">Cobros por taxi</h1>
    <div class="text-muted">Generación por periodo + pago manual + simulación comisión (informativa).</div>
    <div class="text-muted small">Periodo: <strong>{{ $periodType }}</strong> · {{ $pStart }} → {{ $pEnd }} · TZ: {{ $tz }}</div>
  </div>

  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-primary"
       href="{{ route('admin.taxi_charges.export', request()->query()) }}">
      Exportar (Excel)
    </a>

    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#purgeModal">
      Vaciar historial
    </button>
  </div>
</div>

<div class="alert alert-info alert-dismissible fade show mt-3 mb-0" role="alert">
  Nota: Orbana pone a disposición opcional el uso de esta herramienta,
  <strong>esta herramienta NO es auditada por el equipo de Orbana, tampoco es compartida para estadísticas o terceros</strong>.
  Es 100% privada y el Tenant puede vaciar su historial o exportarlo en cualquier momento.

  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
</div>
<div class="alert alert-warning alert-dismissible fade show mt-3 mb-0" role="alert">
      Nota: Este recibo es <strong>informativo</strong> (no fiscal). Úsalo como comprobante interno de la central.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>

<div id="howtoCard" class="card mb-3">
  <div class="card-body position-relative">
    <button type="button"
            class="btn-close position-absolute top-0 end-0 m-2"
            aria-label="Cerrar"
            onclick="document.getElementById('howtoCard')?.remove();">
    </button>

    <h3 class="card-title mb-1">Flujo de trabajo</h3>
    <div class="text-muted">
      Genera cobros por periodo a partir de las cuotas activas y registra pagos manuales con recibo.
    </div>

    <ol class="mt-3 mb-0">
      <li><strong>Elige el periodo</strong> (Semanal / Quincenal / Mensual). La fecha “Ancla” solo sirve para calcular el rango.</li>
      <li><strong>Genera cobros del periodo</strong>. El sistema es idempotente: si ya existen cobros, los omite.</li>
      <li><strong>Marca pagado</strong> cuando el taxi pague (pago manual).</li>
      <li><strong>Emite recibo</strong> y si lo deseas <strong>imprime</strong>. El recibo queda registrado.</li>
    </ol>

   
  </div>
</div>
 
@if(session('warn'))
  <div class="alert alert-warning alert-dismissible fade show">
    {{ session('warn') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show">
    <div class="fw-semibold mb-1">Revisa lo siguiente:</div>
    <ul class="mb-0">
      @foreach ($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif


  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label">Periodo</label>
          <select name="period_type" class="form-select">
            <option value="weekly"   @selected(request('period_type','weekly')==='weekly')>Semanal</option>
            <option value="biweekly" @selected(request('period_type')==='biweekly')>Quincenal</option>
            <option value="monthly"  @selected(request('period_type')==='monthly')>Mensual</option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Ancla (opcional)</label>
          <input type="date" name="anchor_date" value="{{ request('anchor_date') }}" class="form-control">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Estado</label>
          <select name="status" class="form-select">
            <option value="">Todos</option>
            <option value="pending" @selected(request('status')==='pending')>Pendiente</option>
            <option value="paid"    @selected(request('status')==='paid')>Pagado</option>
            <option value="canceled"@selected(request('status')==='canceled')>Cancelado</option>
          </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
          <button class="btn btn-primary mt-4">Filtrar</button>
          <a class="btn btn-outline-secondary mt-4" href="{{ url()->current() }}">Limpiar</a>
        </div>
      </form>

      <hr>

      <form method="post" action="{{ route('admin.taxi_charges.generate') }}" class="d-flex gap-2 flex-wrap">
        @csrf
        <input type="hidden" name="period_type" value="{{ $periodType }}">
        <input type="hidden" name="anchor_date" value="{{ request('anchor_date') }}">
        <button class="btn btn-outline-primary">
          Generar cobros del periodo
        </button>
        <div class="text-muted small align-self-center">
          Comisión simulada: <strong>{{ number_format($commissionPercent,1) }}%</strong> (solo informativo)
        </div>
      </form>
    </div>
  </div>


  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Taxi</th>
              <th>Conductor</th>
              <th class="text-end">Cuota</th>
              <th class="text-end">Ingreso real</th>
              <th class="text-end">Comisión sim.</th>
              <th class="text-center">Estado</th>
              <th>Recibo</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($charges as $c)
              @php
                $gross = (float)($grossByVehicle[$c->vehicle_id] ?? 0);
                $sim = $gross * ($commissionPercent/100.0);
              @endphp
              <tr>
                <td>
                  <div class="fw-semibold">
                    {{ $c->vehicle_economico ? 'Econ '.$c->vehicle_economico : ($c->vehicle_id ? 'Vehículo #'.$c->vehicle_id : '-') }}
                    @if($c->vehicle_plate) · {{ $c->vehicle_plate }} @endif
                  </div>
                  <div class="text-muted small">{{ trim(($c->vehicle_brand ?? '').' '.($c->vehicle_model ?? '')) }}</div>
                </td>
                <td>{{ $c->driver_name ?? '-' }}</td>
                <td class="text-end">${{ number_format((float)$c->amount,2) }}</td>
                <td class="text-end">${{ number_format($gross,2) }}</td>
                <td class="text-end">${{ number_format($sim,2) }}</td>
                <td class="text-center">
                 @php
  $badge = match($c->status) {
    'paid'     => 'bg-success-lt text-success',
    'pending'  => 'bg-warning-lt text-warning',
    'canceled' => 'bg-secondary-lt text-secondary',
    default    => 'bg-secondary-lt text-secondary',
  };
  $label = match($c->status) {
    'paid' => 'Pagado',
    'pending' => 'Pendiente',
    'canceled' => 'Cancelado',
    default => strtoupper($c->status),
  };
@endphp
<span class="badge {{ $badge }}">{{ $label }}</span>

              
                </td>
                <td>
                  @if($c->receipt_id)
                    <a href="{{ route('admin.taxi_receipts.show', $c->receipt_id) }}" class="badge bg-info text-dark">{{ $c->receipt_number }}</a>
                  @else
                    <span class="text-muted small">—</span>
                  @endif
                </td>
                <td class="text-end">
                  <div class="d-inline-flex gap-2">
                    @if($c->status !== 'paid')
                      <form method="post" action="{{ route('admin.taxi_charges.pay', $c->id) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-success">Marcar pagado</button>
                      </form>
                    @endif

                    @if($c->status !== 'canceled')
                      <form method="post" action="{{ route('admin.taxi_charges.cancel', $c->id) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary">Cancelar</button>
                      </form>
                    @endif

                    <form method="post" action="{{ route('admin.taxi_charges.receipt', $c->id) }}">
                      @csrf
                      <button class="btn btn-sm btn-outline-primary">Recibo</button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="8" class="text-center text-muted py-4">Sin cobros en este periodo.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">{{ $charges->links() }}</div>
    </div>
  </div>




</div>

{{-- Modal Vaciar historial --}}
<div class="modal fade" id="purgeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Vaciar historial</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <form method="post" action="{{ route('admin.taxi_charges.purge') }}">
        @csrf
        <div class="modal-body">
          <div class="alert alert-warning mb-3">
            Esta acción borrará <strong>cobros y recibos</strong> de esta herramienta para este tenant.
            No afecta rides ni operación del Dispatch.
          </div>

          <label class="form-label">Escribe <strong>ACEPTAR</strong> para confirmar</label>
          <input id="purgeConfirmText" name="confirm_text" class="form-control" autocomplete="off" required>

          <div class="text-muted small mt-2">
            Recomendación: antes exporta (Excel) si necesitas respaldo.
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button id="purgeBtn" class="btn btn-danger" disabled>Vaciar</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const input = document.getElementById('purgeConfirmText');
  const btn   = document.getElementById('purgeBtn');
  if(!input || !btn) return;

  function sync(){
    btn.disabled = (input.value || '').trim().toUpperCase() !== 'ACEPTAR';
  }
  input.addEventListener('input', sync);
  sync();
})();
</script>
@endpush

@endsection
