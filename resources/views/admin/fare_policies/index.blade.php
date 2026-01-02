@extends('layouts.admin')
@section('title','Tarifas')
@section('content')

@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif

@if(session('warn'))
  <div class="alert alert-warning">{{ session('warn') }}</div>
@endif

@php
  $isZeroPolicy = $policy
      && ((float)($policy->base_fee ?? 0) == 0.0)
      && ((float)($policy->per_km ?? 0) == 0.0)
      && ((float)($policy->per_min ?? 0) == 0.0);

  $missingPolicy = !$policy;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0">Política de tarifa</h3>
    <div class="text-muted small">
      Configura la tarifa base y el costo por kilómetro/minuto para que la app pueda cotizar y generar ofertas.
    </div>
  </div>

  <div class="d-flex gap-2">
    <a href="{{ route('admin.fare_policies.edit', ['tenant_id'=>$tenantId]) }}" class="btn btn-primary shadow">
      Editar
    </a>

    {{-- Opcional: si implementas un endpoint para clonar del tenant 100 --}}
    {{-- 
    <form method="POST" action="{{ route('admin.fare_policies.seed') }}">
      @csrf
      <button type="submit" class="btn btn-outline-secondary">
        Cargar tarifa demo (Global)
      </button>
    </form>
    --}}
  </div>
</div>

{{-- Instrucciones claras --}}
<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <div class="d-flex align-items-start gap-3">
      <div class="flex-grow-1">
        <div class="fw-semibold mb-1">Cómo funciona la cotización</div>
        <ul class="mb-0 text-muted small">
          <li><strong>Base</strong>: se cobra siempre al iniciar el viaje.</li>
          <li><strong>Por km</strong>: se multiplica por la distancia estimada.</li>
          <li><strong>Por min</strong>: se multiplica por el tiempo estimado (tráfico).</li>
          <li><strong>Mínimo</strong>: si el total calculado es menor, se cobra el mínimo.</li>
          <li><strong>Noche</strong>: si el viaje cae en horario nocturno, se aplica el <strong>multiplicador</strong>.</li>
        </ul>
      </div>
      <div class="text-end">
        <div class="text-muted small mb-1">Sugerencia rápida (demo)</div>
        <div class="small">
          Base: <strong>$35</strong><br>
          Por km: <strong>$12</strong><br>
          Por min: <strong>$2</strong><br>
          Mínimo: <strong>$50</strong><br>
          Noche: <strong>x1.20</strong>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Estados: no policy / policy en ceros --}}
@if($missingPolicy)
  <div class="alert alert-warning">
    <div class="fw-semibold mb-1">Aún no hay política de tarifa para este tenant</div>
    <div class="small">
      Presiona <strong>Editar</strong> para crearla. Recomendación: no dejes <strong>Base / Por km / Por min</strong> en cero,
      ya que la app no podrá cotizar correctamente.
    </div>
  </div>
@elseif($isZeroPolicy)
  <div class="alert alert-warning">
    <div class="fw-semibold mb-1">Tarifa incompleta (valores en 0)</div>
    <div class="small">
      Actualmente <strong>Base, Por km y Por min</strong> están en <strong>0</strong>. La app mostrará cotizaciones en $0 o no podrá ofertar.
      Entra a <strong>Editar</strong> y ajusta al menos esos 3 campos (y opcionalmente el <strong>Mínimo</strong>).
    </div>
  </div>
@endif

@if(!$policy)
  <div class="card shadow-sm border-0">
    <div class="card-body text-muted">
      No hay política cargada aún para este tenant.
      Presiona <strong>Editar</strong> para crear la base con valores iniciales y después ajusta tus tarifas.
    </div>
  </div>
@else
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="text-muted small">Modo</div>
          <div class="fw-semibold">{{ $policy->mode }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Base</div>
          <div class="fw-semibold">{{ number_format($policy->base_fee, 2) }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Por km</div>
          <div class="fw-semibold">{{ number_format($policy->per_km, 2) }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Por min</div>
          <div class="fw-semibold">{{ number_format($policy->per_min, 2) }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Noche: inicia</div>
          <div class="fw-semibold">{{ $policy->night_start_hour }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Noche: termina</div>
          <div class="fw-semibold">{{ $policy->night_end_hour }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Mult. noche</div>
          <div class="fw-semibold">{{ number_format($policy->night_multiplier, 2) }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Redondeo</div>
          <div class="fw-semibold">
            {{ $policy->round_mode }}
            @if($policy->round_mode === 'decimals')
              ({{ $policy->round_decimals }})
            @else
              ({{ number_format($policy->round_step, 2) }})
            @endif
          </div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Round to</div>
          <div class="fw-semibold">{{ number_format($policy->round_to, 2) }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Mínimo</div>
          <div class="fw-semibold">{{ number_format($policy->min_total, 2) }}</div>
        </div>

        <div class="col-md-12">
          <div class="text-muted small mb-1">Extras (JSON)</div>
          <pre class="mb-0 bg-light p-2 rounded small">{{ $policy->extras ? json_encode($policy->extras, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '{}' }}</pre>
        </div>

        <div class="col-md-6">
          <div class="text-muted small">Vigente desde</div>
          <div class="fw-semibold">{{ $policy->active_from?->format('Y-m-d') ?? '—' }}</div>
        </div>

        <div class="col-md-6">
          <div class="text-muted small">Vigente hasta</div>
          <div class="fw-semibold">{{ $policy->active_to?->format('Y-m-d') ?? '—' }}</div>
        </div>
      </div>
    </div>

    <div class="card-footer bg-transparent border-0 d-flex justify-content-between align-items-center">
      <div class="text-muted small">
        Recomendación: configura <strong>Base / Por km / Por min</strong> y un <strong>Mínimo</strong> para evitar cotizaciones en $0.
      </div>
      <a href="{{ route('admin.fare_policies.edit', ['tenant_id'=>$tenantId]) }}" class="btn btn-outline-primary">
        Editar
      </a>
    </div>
  </div>
@endif

@endsection
