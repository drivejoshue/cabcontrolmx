@extends('layouts.sysadmin')

@section('title','Billing Plans')

@section('content')
<div class="page-header">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">Billing Plans</h2>
      <div class="text-muted">Catálogo global de precios (SysAdmin)</div>
    </div>
    <div class="col-auto">
      <a href="{{ route('sysadmin.billing-plans.create') }}" class="btn btn-primary">
        <i class="ti ti-plus me-1"></i> Nuevo plan
      </a>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="card">
    <div class="card-body">
      @if(session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
      @endif
      @if(session('err'))
        <div class="alert alert-danger">{{ session('err') }}</div>
      @endif

      <form class="row g-2 mb-3" method="GET" action="{{ route('sysadmin.billing-plans.index') }}">
        <div class="col-12 col-md-6">
          <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por code o name...">
        </div>
        <div class="col-12 col-md-auto">
          <button class="btn btn-outline-secondary">
            <i class="ti ti-search me-1"></i> Buscar
          </button>
          <a href="{{ route('sysadmin.billing-plans.index') }}" class="btn btn-outline-secondary">
            Limpiar
          </a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-vcenter">
          <thead>
            <tr>
              <th>Estado</th>
              <th>Código</th>
              <th>Nombre</th>
              <th>Modelo</th>
              <th class="text-end">Base mensual</th>
              <th class="text-end">Incluidos</th>
              <th class="text-end">Precio/vehículo</th>
              <th>Moneda</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($plans as $p)
              @php
                $badge = $p->active ? 'bg-success-lt text-success' : 'bg-secondary-lt text-secondary';
                $modelBadge = $p->billing_model === 'per_vehicle'
                  ? 'bg-azure-lt text-azure'
                  : 'bg-purple-lt text-purple';
              @endphp
              <tr>
                <td><span class="badge {{ $badge }}">{{ $p->active ? 'Activo' : 'Inactivo' }}</span></td>
                <td class="fw-semibold">{{ $p->code }}</td>
                <td>{{ $p->name }}</td>
                <td><span class="badge {{ $modelBadge }}">{{ $p->billing_model }}</span></td>

                <td class="text-end">${{ number_format((float)$p->base_monthly_fee, 2) }}</td>
                <td class="text-end">{{ (int)$p->included_vehicles }}</td>
                <td class="text-end fw-semibold">${{ number_format((float)$p->price_per_vehicle, 2) }}</td>
                <td>{{ $p->currency }}</td>

                <td class="text-end">
                  <a href="{{ route('sysadmin.billing-plans.edit', $p) }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-edit me-1"></i> Editar
                  </a>

                  <form method="POST" action="{{ route('sysadmin.billing-plans.destroy', $p) }}" class="d-inline"
                        onsubmit="return confirm('¿Eliminar este plan?');">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">
                      <i class="ti ti-trash me-1"></i> Eliminar
                    </button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="9" class="text-muted">Sin planes.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $plans->links() }}
      </div>
    </div>
  </div>
</div>
@endsection
