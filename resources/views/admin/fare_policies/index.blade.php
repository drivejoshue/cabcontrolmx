@extends('layouts.admin')
@section('title','Tarifas')
@section('content')

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Política de tarifa</h3>
  <a href="{{ route('admin.fare_policies.edit', ['tenant_id'=>$tenantId]) }}" class="btn btn-primary shadow">
    Editar
  </a>
</div>

@if(!$policy)
  <div class="card shadow-sm border-0">
    <div class="card-body text-muted">
      No hay política cargada aún para este tenant. Presiona <strong>Editar</strong> para crear la base.
    </div>
  </div>
@else
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3"><div class="text-muted small">Modo</div><div class="fw-semibold">{{ $policy->mode }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Base</div><div class="fw-semibold">{{ number_format($policy->base_fee,2) }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Por km</div><div class="fw-semibold">{{ number_format($policy->per_km,2) }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Por min</div><div class="fw-semibold">{{ number_format($policy->per_min,2) }}</div></div>

        <div class="col-md-3"><div class="text-muted small">Noche: inicia</div><div class="fw-semibold">{{ $policy->night_start_hour }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Noche: termina</div><div class="fw-semibold">{{ $policy->night_end_hour }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Mult. noche</div><div class="fw-semibold">{{ number_format($policy->night_multiplier,2) }}</div></div>

        <div class="col-md-3"><div class="text-muted small">Redondeo</div>
          <div class="fw-semibold">
            {{ $policy->round_mode }}
            @if($policy->round_mode === 'decimals') ({{ $policy->round_decimals }})
            @else ({{ number_format($policy->round_step,2) }})
            @endif
          </div>
        </div>

        <div class="col-md-3"><div class="text-muted small">Round to</div><div class="fw-semibold">{{ number_format($policy->round_to,2) }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Mínimo</div><div class="fw-semibold">{{ number_format($policy->min_total,2) }}</div></div>

        <div class="col-md-12">
          <div class="text-muted small mb-1">Extras (JSON)</div>
          <pre class="mb-0 bg-light p-2 rounded small">{{ $policy->extras ? json_encode($policy->extras, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '{}' }}</pre>
        </div>

        <div class="col-md-6"><div class="text-muted small">Vigente desde</div><div class="fw-semibold">{{ $policy->active_from?->format('Y-m-d') ?? '—' }}</div></div>
        <div class="col-md-6"><div class="text-muted small">Vigente hasta</div><div class="fw-semibold">{{ $policy->active_to?->format('Y-m-d') ?? '—' }}</div></div>
      </div>
    </div>
    <div class="card-footer bg-transparent border-0 text-end">
      <a href="{{ route('admin.fare_policies.edit', ['tenant_id'=>$tenantId]) }}" class="btn btn-outline-primary">
        Editar
      </a>
    </div>
  </div>
@endif
@endsection
