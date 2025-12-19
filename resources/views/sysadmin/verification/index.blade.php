@extends('layouts.sysadmin')
@section('title','Verificación de taxis y conductores')

@section('content')
<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Centro de verificación</h3>
      <div class="text-muted">
        Vehículos y conductores con documentos pendientes o no verificados.
      </div>
    </div>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#tab-vehicles">
        Vehículos
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#tab-drivers">
        Conductores
      </a>
    </li>
  </ul>

  <div class="tab-content">

    {{-- TAB VEHÍCULOS --}}
    <div class="tab-pane fade show active" id="tab-vehicles">
      <div class="card mb-3">
        <div class="card-header">
          <strong>Vehículos por revisar</strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Tenant</th>
                  <th>Eco / Placa</th>
                  <th>Año</th>
                  <th>Pendientes</th>
                  <th>Status verificación</th>
                  <th>Notas</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
              @forelse($vehicles as $v)
                @php
                  $status = $v->verification_status ?? 'pending';
                  $statusLower = strtolower($status);
                  $badge = match($statusLower) {
                    'verified' => 'success',
                    'rejected' => 'danger',
                    default    => 'warning',
                  };
                @endphp
                <tr>
                  <td class="text-muted">
                    {{ $v->tenant_name }}<br>
                    <span class="small text-muted">Tenant #{{ $v->tenant_id }}</span>
                  </td>
                  <td>
                    <strong>#{{ $v->economico }}</strong><br>
                    <span class="text-muted small">{{ $v->plate ?: '—' }}</span>
                  </td>
                  <td>{{ $v->year ?: '—' }}</td>
                  <td>
                    @if($v->pending_docs > 0)
                      <span class="badge bg-warning text-dark">
                        {{ $v->pending_docs }} pend.
                      </span>
                    @else
                      <span class="badge bg-success">0</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge bg-{{ $badge }}">{{ $statusLower }}</span>
                  </td>
                  <td class="text-muted small">
                    {{ $v->verification_notes ?: '—' }}
                  </td>
                  <td class="text-end">
                    <a href="{{ route('sysadmin.verifications.vehicles.show', $v->id) }}"
                       class="btn btn-sm btn-outline-primary">
                      Revisar
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center text-muted py-3">
                    No hay vehículos pendientes de verificación.
                  </td>
                </tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
        @if(method_exists($vehicles, 'links'))
          <div class="card-footer">
            {{ $vehicles->links() }}
          </div>
        @endif
      </div>
    </div>

    {{-- TAB CONDUCTORES --}}
    <div class="tab-pane fade" id="tab-drivers">
      <div class="card mb-3">
        <div class="card-header">
          <strong>Conductores por revisar</strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Tenant</th>
                  <th>Conductor</th>
                  <th>Contacto</th>
                  <th>Pendientes</th>
                  <th>Status verificación</th>
                  <th>Notas</th>
                  <th class="text-end">Acciones</th>
                </tr>
              </thead>
              <tbody>
              @forelse($drivers as $d)
                @php
                  $status = $d->verification_status ?? 'pending';
                  $statusLower = strtolower($status);
                  $badge = match($statusLower) {
                    'verified' => 'success',
                    'rejected' => 'danger',
                    default    => 'warning',
                  };
                @endphp
                <tr>
                  <td class="text-muted">
                    {{ $d->tenant_name }}<br>
                    <span class="small text-muted">Tenant #{{ $d->tenant_id }}</span>
                  </td>
                  <td>
                    <strong>{{ $d->name }}</strong><br>
                    <span class="small text-muted">{{ $d->email ?: '—' }}</span>
                  </td>
                  <td class="small text-muted">
                    Tel: {{ $d->phone ?: '—' }}
                  </td>
                  <td>
                    @if($d->pending_docs > 0)
                      <span class="badge bg-warning text-dark">
                        {{ $d->pending_docs }} pend.
                      </span>
                    @else
                      <span class="badge bg-success">0</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge bg-{{ $badge }}">{{ $statusLower }}</span>
                  </td>
                  <td class="text-muted small">
                    {{ $d->verification_notes ?: '—' }}
                  </td>
                  <td class="text-end">
                    <a href="{{ route('sysadmin.verifications.drivers.show', $d->id) }}"
                       class="btn btn-sm btn-outline-primary">
                      Revisar
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center text-muted py-3">
                    No hay conductores pendientes de verificación.
                  </td>
                </tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
        @if(method_exists($drivers, 'links'))
          <div class="card-footer">
            {{ $drivers->links() }}
          </div>
        @endif
      </div>
    </div>

  </div> {{-- tab-content --}}
</div>
@endsection
