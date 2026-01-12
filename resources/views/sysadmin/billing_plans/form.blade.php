@extends('layouts.sysadmin')

@section('title', $mode === 'create' ? 'Nuevo Billing Plan' : 'Editar Billing Plan')

@section('content')
@php
  $isEdit = $mode === 'edit';
  $action = $isEdit
    ? route('sysadmin.billing-plans.update', $plan)
    : route('sysadmin.billing-plans.store');
@endphp

<div class="page-header">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">{{ $isEdit ? 'Editar plan' : 'Nuevo plan' }}</h2>
      <div class="text-muted">Catálogo global de planes (SysAdmin)</div>
    </div>
    <div class="col-auto">
      <a href="{{ route('sysadmin.billing-plans.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i> Volver
      </a>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="card">
    <div class="card-body">
      @if($errors->any())
        <div class="alert alert-danger">
          <div class="fw-semibold mb-1">Revisa los campos:</div>
          <ul class="mb-0">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ $action }}" class="row g-3">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="col-12 col-md-4">
          <label class="form-label">Código</label>
          <input type="text" name="code" class="form-control" maxlength="50"
                 value="{{ old('code', $plan->code) }}" placeholder="PV_STARTER" required>
          <div class="form-hint">Único. Se usa para asignar defaults a tenants.</div>
        </div>

        <div class="col-12 col-md-8">
          <label class="form-label">Nombre</label>
          <input type="text" name="name" class="form-control" maxlength="120"
                 value="{{ old('name', $plan->name) }}" placeholder="Per Vehicle Starter" required>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Modelo</label>
          <select name="billing_model" class="form-select" required>
            @php $bm = old('billing_model', $plan->billing_model ?: 'per_vehicle'); @endphp
            <option value="per_vehicle" @selected($bm==='per_vehicle')>per_vehicle</option>
            <option value="commission" @selected($bm==='commission')>commission</option>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Moneda</label>
          <input type="text" name="currency" class="form-control" maxlength="10"
                 value="{{ old('currency', $plan->currency ?: 'MXN') }}" required>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Activo</label>
          @php $act = old('active', $plan->active ? '1' : '0'); @endphp
          <label class="form-check form-switch mt-1">
            <input class="form-check-input" type="checkbox" name="active" value="1" @checked($act==='1')>
            <span class="form-check-label">Disponible</span>
          </label>
        </div>

        <hr class="my-2">

        <div class="col-12 col-md-4">
          <label class="form-label">Base mensual</label>
          <input type="number" step="0.01" min="0" name="base_monthly_fee" class="form-control"
                 value="{{ old('base_monthly_fee', $plan->base_monthly_fee) }}" required>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Vehículos incluidos</label>
          <input type="number" step="1" min="0" name="included_vehicles" class="form-control"
                 value="{{ old('included_vehicles', $plan->included_vehicles) }}" required>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Precio por vehículo extra</label>
          <input type="number" step="0.01" min="0" name="price_per_vehicle" class="form-control"
                 value="{{ old('price_per_vehicle', $plan->price_per_vehicle) }}" required>
          <div class="form-hint">Ej: 299.00</div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Effective from (opcional)</label>
          <input type="date" name="effective_from" class="form-control"
                 value="{{ old('effective_from', optional($plan->effective_from)->format('Y-m-d')) }}">
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary">
            <i class="ti ti-device-floppy me-1"></i> Guardar
          </button>
          <a href="{{ route('sysadmin.billing-plans.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
