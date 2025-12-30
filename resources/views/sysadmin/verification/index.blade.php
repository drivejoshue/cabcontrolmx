{{-- resources/views/sysadmin/verification/index.blade.php --}}
@extends('layouts.sysadmin')
@section('title','Centro de verificación')

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <h2 class="page-title">Centro de verificación</h2>
        <div class="text-muted">Documentos de vehículos y conductores (SysAdmin).</div>
      </div>
      <div class="col-auto ms-auto">
        <a href="{{ route('sysadmin.invoices.index') }}" class="btn btn-outline-secondary btn-sm">Facturas</a>
      </div>
    </div>
  </div>

 <form class="card mb-3" method="get" action="{{ route('sysadmin.verifications.index') }}">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-12 col-md-4">
        <label class="form-label">Tenant</label>
        @php $currentTenant = (string)request('tenant_id',''); @endphp
        <select name="tenant_id" id="tenant_id" class="form-select">
          <option value="">Todos</option>
          @foreach($tenantsList as $t)
            <option value="{{ $t->id }}" {{ $currentTenant === (string)$t->id ? 'selected' : '' }}>
              {{ $t->name }} ( #{{ $t->id }} )
            </option>
          @endforeach
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label">Estatus verificación</label>
        @php $st = request('status'); @endphp
        <select name="status" class="form-select">
          <option value="">Todos</option>
          @foreach(['pending','verified','rejected'] as $opt)
            <option value="{{ $opt }}" @selected($st===$opt)>{{ ucfirst($opt) }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label">Buscar</label>
        <input type="text" name="q" value="{{ request('q') }}" class="form-control"
               placeholder="eco, placa, nombre, email, teléfono…">
      </div>

      <div class="col-12 col-md-2 d-flex align-items-end gap-2">
        <button class="btn btn-primary w-100">Filtrar</button>
        <a href="{{ route('sysadmin.verifications.index') }}" class="btn btn-outline-secondary">
          Limpiar
        </a>
      </div>
    </div>
  </div>
  {{-- Mantén la paginación en 1 cuando cambie tenant/status/q --}}
  <input type="hidden" name="vehicles_page" value="1">
  <input type="hidden" name="drivers_page"  value="1">
</form>


  <ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
      <a class="nav-link active" data-bs-toggle="tab" href="#tab-vehicles" role="tab">Vehículos</a>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" data-bs-toggle="tab" href="#tab-drivers" role="tab">Conductores</a>
    </li>
  </ul>

  <div class="tab-content">
    {{-- Vehículos --}}
    <div class="tab-pane fade show active" id="tab-vehicles" role="tabpanel">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
          <strong>Vehículos por revisar</strong>
          <div class="text-muted small">Total: {{ method_exists($vehicles,'total') ? $vehicles->total() : count($vehicles) }}</div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter table-striped">
            <thead class="table-light">
            <tr>
              <th>Tenant</th>
              <th>Eco/Placa</th>
              <th>Año</th>
              <th>Pend.</th>
              <th>Estatus</th>
              <th>Notas</th>
              <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            @forelse($vehicles as $v)
              @php
                $status = strtolower($v->verification_status ?? 'pending');
                $badge  = $status==='verified' ? 'success' : ($status==='rejected'?'danger':'warning');
              @endphp
              <tr>
                <td>
                  <div class="fw-semibold">{{ $v->tenant_name }}</div>
                  <div class="text-muted small">#{{ $v->tenant_id }}</div>
                </td>
                <td>
                  <div class="fw-semibold">#{{ $v->economico }}</div>
                  <div class="text-muted small">{{ $v->plate ?: '—' }}</div>
                </td>
                <td>{{ $v->year ?: '—' }}</td>
                <td>
                  @if($v->pending_docs > 0)
                    <span class="badge bg-warning text-dark">{{ $v->pending_docs }}</span>
                  @else
                    <span class="badge bg-success">0</span>
                  @endif
                </td>
                <td><span class="badge bg-{{ $badge }}">{{ $status }}</span></td>
                <td class="text-muted small">{{ $v->verification_notes ?: '—' }}</td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary"
                     href="{{ route('sysadmin.verifications.vehicles.show', $v->id) }}">
                    Revisar
                  </a>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">Sin vehículos pendientes.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
        @if(method_exists($vehicles,'links'))
          <div class="card-footer">{{ $vehicles->appends(request()->query())->links() }}</div>
        @endif
      </div>
    </div>

    {{-- Conductores --}}
    <div class="tab-pane fade" id="tab-drivers" role="tabpanel">
      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <strong>Conductores por revisar</strong>
          <div class="text-muted small">Total: {{ method_exists($drivers,'total') ? $drivers->total() : count($drivers) }}</div>
        </div>
        <div class="table-responsive">
          <table class="table table-vcenter table-striped">
            <thead class="table-light">
            <tr>
              <th>Tenant</th>
              <th>Conductor</th>
              <th>Contacto</th>
              <th>Pend.</th>
              <th>Estatus</th>
              <th>Notas</th>
              <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            @forelse($drivers as $d)
              @php
                $status = strtolower($d->verification_status ?? 'pending');
                $badge  = $status==='verified' ? 'success' : ($status==='rejected'?'danger':'warning');
              @endphp
              <tr>
                <td>
                  <div class="fw-semibold">{{ $d->tenant_name }}</div>
                  <div class="text-muted small">#{{ $d->tenant_id }}</div>
                </td>
                <td>
                  <div class="fw-semibold">{{ $d->name }}</div>
                  <div class="text-muted small">{{ $d->email ?: '—' }}</div>
                </td>
                <td class="text-muted small">Tel: {{ $d->phone ?: '—' }}</td>
                <td>
                  @if($d->pending_docs > 0)
                    <span class="badge bg-warning text-dark">{{ $d->pending_docs }}</span>
                  @else
                    <span class="badge bg-success">0</span>
                  @endif
                </td>
                <td><span class="badge bg-{{ $badge }}">{{ $status }}</span></td>
                <td class="text-muted small">{{ $d->verification_notes ?: '—' }}</td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary"
                     href="{{ route('sysadmin.verifications.drivers.show', $d->id) }}">
                    Revisar
                  </a>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">Sin conductores pendientes.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
        @if(method_exists($drivers,'links'))
          <div class="card-footer">{{ $drivers->appends(request()->query())->links() }}</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function(){
    if (window.TomSelect) {
      new TomSelect('#tenant_id', {
        create: false,
        sortField: {field:'text',direction:'asc'},
        maxOptions: 1000,
        placeholder: 'Todos',
        allowEmptyOption: true
      });
    }
  });
</script>
@endpush
