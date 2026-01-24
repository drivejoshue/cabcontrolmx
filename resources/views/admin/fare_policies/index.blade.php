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
  $missingPolicy = !$policy;

  $demo = (object)[
    'base_fee' => 35.00,
    'per_km' => 12.00,
    'per_min' => 2.00,
    'min_total' => 50.00,
    'night_multiplier' => 1.20,
    'night_start_hour' => 22,
    'night_end_hour' => 6,
    'round_mode' => 'step',
    'round_step' => 1.00,
    'round_decimals' => 0,
    'round_to' => 1.00,
    'stop_fee' => 20.00,
'slider_min_pct' => 0.80,
'slider_max_pct' => 1.20,
  ];

  $kmExample = 10;
  $minExample = 13;

  $p = $policy ?: $demo;

  $subtotalDay = (float)$p->base_fee + ($kmExample * (float)$p->per_km) + ($minExample * (float)$p->per_min);
  $totalDay = max($subtotalDay, (float)$p->min_total);

  $subtotalNight = $subtotalDay * max(1.0, (float)($p->night_multiplier ?? 1.0));
  $totalNight = max($subtotalNight, (float)$p->min_total);

  // Ejemplo corto para explicar “mínimo”
  $kmShort = 0.3;
  $minShort = 1;
  $subtotalShort = (float)$p->base_fee + ($kmShort * (float)$p->per_km) + ($minShort * (float)$p->per_min);
  $totalShort = max($subtotalShort, (float)$p->min_total);

  $money = fn($v) => '$'.number_format((float)$v, 2);
@endphp

<div class="container-fluid px-0">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h2 class="page-title mb-1">Política de tarifa</h2>
      <div class="text-muted">
        Configura la tarifa para que la app pueda <b>cotizar</b> y generar <b>ofertas</b> sin errores.
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('admin.fare_policies.edit', ['tenant_id'=>$tenantId]) }}" class="btn btn-primary">
        <i class="ti ti-pencil"></i> Editar
      </a>
    </div>
  </div>

  {{-- Guía y ejemplos --}}
  <div class="row row-cards mb-3">
    <div class="col-12 col-lg-8">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Cómo se calcula una cotización</h3>
        </div>
        <div class="card-body">
          <div class="text-muted mb-2">
            La app usa esta fórmula para estimar el costo y poder enviar ofertas a conductores:
          </div>

          <div class="p-3 rounded border bg-light">
            <div class="fw-semibold mb-1">Fórmula</div>
            <div class="text-muted">
              <b>Total</b> = Base + (Km × Por km) + (Min × Por min)
              <span class="mx-1">→</span>
              si es <b>noche</b> se aplica el multiplicador
              <span class="mx-1">→</span>
              y al final se respeta el <b>Mínimo</b>.
            </div>
          </div>

          <div class="mt-3">
            <div class="fw-semibold mb-1">Ejemplo práctico ({{ $kmExample }} km y {{ $minExample }} min)</div>
            <div class="text-muted">
              Con tu tarifa actual:
              <ul class="mb-2">
                <li>Día: {{ $money($p->base_fee) }} + ({{ $kmExample }} × {{ $money($p->per_km) }}) + ({{ $minExample }} × {{ $money($p->per_min) }}) = <b>{{ $money($subtotalDay) }}</b> → Mínimo {{ $money($p->min_total) }} → <b>{{ $money($totalDay) }}</b></li>
                <li>Noche (x{{ number_format((float)($p->night_multiplier ?? 1.0),2) }}): <b>{{ $money($subtotalDay) }}</b> × {{ number_format((float)($p->night_multiplier ?? 1.0),2) }} = <b>{{ $money($subtotalNight) }}</b> → Mínimo {{ $money($p->min_total) }} → <b>{{ $money($totalNight) }}</b></li>
              </ul>

              <div class="fw-semibold mb-1">Ejemplo de viaje corto (para entender el “Mínimo”)</div>
              <div class="text-muted">
                {{ $kmShort }} km y {{ $minShort }} min = <b>{{ $money($subtotalShort) }}</b>.
                Si queda por debajo del mínimo, se cobra el mínimo: <b>{{ $money($totalShort) }}</b>.
              </div>
            </div>
          </div>

          <div class="alert alert-warning mt-3 mb-0">
            <div class="fw-semibold">Recomendación</div>
            Mantén <b>Base</b>, <b>Por km</b>, <b>Por min</b> y <b>Mínimo</b> por encima de 0.
            Si se dejan en cero, la app puede cotizar en $0 o no generar ofertas correctamente.
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Sugerencia rápida (valores base)</h3>
        </div>
        <div class="card-body">
          <div class="text-muted mb-2">Puedes usar esto como punto de partida:</div>
          <div class="d-flex flex-column gap-1">
            <div class="d-flex justify-content-between"><span class="text-muted">Base</span><span class="fw-semibold">{{ $money($demo->base_fee) }}</span></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Por km</span><span class="fw-semibold">{{ $money($demo->per_km) }}</span></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Por min</span><span class="fw-semibold">{{ $money($demo->per_min) }}</span></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Mínimo</span><span class="fw-semibold">{{ $money($demo->min_total) }}</span></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Noche</span><span class="fw-semibold">x{{ number_format($demo->night_multiplier,2) }} ({{ $demo->night_start_hour }}–{{ $demo->night_end_hour }})</span></div>
          </div>

          <div class="mt-3 small text-muted">
            Si tu central maneja tarifas muy distintas, ajusta con calma y prueba primero con viajes cortos y medianos.
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Estado: policy --}}
  @if($missingPolicy)
    <div class="alert alert-warning">
      <div class="fw-semibold">Aún no hay política de tarifa para este tenant</div>
      <div>Presiona <b>Editar</b> para crearla con valores iniciales y ajustarlos.</div>
    </div>
  @else
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <div class="text-muted small">Modo</div>
            <div class="fw-semibold">{{ $policy->mode }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Base</div>
            <div class="fw-semibold">{{ $money($policy->base_fee) }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Por km</div>
            <div class="fw-semibold">{{ $money($policy->per_km) }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Por min</div>
            <div class="fw-semibold">{{ $money($policy->per_min) }}</div>
          </div>

          <div class="col-md-3">
            <div class="text-muted small">Noche</div>
            <div class="fw-semibold">{{ $policy->night_start_hour }}–{{ $policy->night_end_hour }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Multiplicador noche</div>
            <div class="fw-semibold">x{{ number_format((float)$policy->night_multiplier,2) }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Mínimo</div>
            <div class="fw-semibold">{{ $money($policy->min_total) }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Redondeo</div>
            <div class="fw-semibold">
              {{ $policy->round_mode }}
              @if($policy->round_mode === 'decimals')
                ({{ (int)$policy->round_decimals }})
              @else
                ({{ number_format((float)$policy->round_step, 2) }})
              @endif
            </div>
          </div>


          <div class="col-md-3">
  <div class="text-muted small">Stop fee</div>
  <div class="fw-semibold">{{ $money($policy->stop_fee ?? 0) }}</div>
</div>

<div class="col-md-3">
  <div class="text-muted small">Slider (puja)</div>
  <div class="fw-semibold">
    {{ number_format((float)($policy->slider_min_pct ?? 0.80) * 100, 0) }}% –
    {{ number_format((float)($policy->slider_max_pct ?? 1.20) * 100, 0) }}%
  </div>
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

      <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
        <div class="text-muted small">
          Consejo: evita tarifas en cero para no tener cotizaciones inválidas.
        </div>
        <a href="{{ route('admin.fare_policies.edit', ['tenant_id'=>$tenantId]) }}" class="btn btn-outline-primary">
          <i class="ti ti-pencil"></i> Editar
        </a>
      </div>
    </div>
  @endif

</div>
@endsection
