@extends('layouts.admin')
@section('title','Editar tarifa')

@section('content')

@php
  $demo = [
    'base_fee' => 35,
    'per_km' => 12,
    'per_min' => 2,
    'min_total' => 50,
    'night_multiplier' => 1.20,
    'night_start_hour' => 22,
    'night_end_hour' => 6,
    'round_mode' => 'step',
    'round_step' => 1.00,
    'round_decimals' => 0,
    'round_to' => 1.00,
    'stop_fee' => 20,
'slider_min_pct' => 0.80,
'slider_max_pct' => 1.20,

  ];

  $base = (float) old('base_fee', $policy->base_fee ?? 0);
  $km   = (float) old('per_km', $policy->per_km ?? 0);
  $min  = (float) old('per_min', $policy->per_min ?? 0);
  $minTotal = (float) old('min_total', $policy->min_total ?? 0);
  $isIncomplete = ($base <= 0 || $km <= 0 || $min <= 0 || $minTotal <= 0);

  $kmExample = 10;
  $minExample = 13;
@endphp

<div class="container-fluid px-0">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h2 class="page-title mb-1">Editar tarifa</h2>
      <div class="text-muted">
        Ajusta estos valores para que la app calcule cotizaciones de forma consistente.
      </div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <span class="badge bg-secondary-lt text-secondary">Tenant #{{ $tenantId }}</span>
      <span class="badge {{ $isIncomplete ? 'bg-warning-lt text-warning' : 'bg-success-lt text-success' }}">
        {{ $isIncomplete ? 'Falta configurar' : 'Lista' }}
      </span>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold">Revisa los campos marcados</div>
      <div class="text-muted">Hay valores fuera de rango o incompletos.</div>
    </div>
  @elseif($isIncomplete)
    <div class="alert alert-warning">
      <div class="fw-semibold">Tarifa incompleta</div>
      <div class="text-muted">
        Para que la app funcione bien, configura como mínimo: <b>Base</b>, <b>Por km</b>, <b>Por min</b> y <b>Mínimo</b>.
      </div>
    </div>
  @else
    <div class="alert alert-info">
      <div class="fw-semibold">Fórmula</div>
      <div class="text-muted">
        Total = Base + (Km × Por km) + (Min × Por min). Si es noche, se aplica multiplicador. Al final se respeta el mínimo.
      </div>
    </div>
  @endif

  <div class="row row-cards">
    {{-- Form --}}
    <div class="col-12 col-lg-8">
      <form method="POST" action="{{ route('admin.fare_policies.update') }}">
        @csrf
        @method('PUT')

        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h3 class="card-title mb-0">Parámetros de tarifa</h3>

            <button type="button" class="btn btn-outline-secondary" id="btnUseDemo">
              <i class="ti ti-wand"></i> Usar sugerencia
            </button>
          </div>

          <div class="card-body">
            <div class="row g-3">

              <div class="col-md-3">
                <label class="form-label">Modo</label>
                <select class="form-select @error('mode') is-invalid @enderror" name="mode">
                  <option value="meter" @selected(old('mode',$policy->mode)=='meter')>Meter</option>
                </select>
                @error('mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Base</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input id="base_fee" type="number" step="1" min="20" max="1000"
                         class="form-control @error('base_fee') is-invalid @enderror"
                         name="base_fee" value="{{ old('base_fee',$policy->base_fee) }}">
                </div>
                <div class="form-hint">Se cobra al iniciar el viaje.</div>
                @error('base_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Por km</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input id="per_km" type="number" step="0.10" min="0.10" max="50"
                         class="form-control @error('per_km') is-invalid @enderror"
                         name="per_km" value="{{ old('per_km',$policy->per_km) }}">
                  <span class="input-group-text">/ km</span>
                </div>
                <div class="form-hint">Costo por distancia.</div>
                @error('per_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Por min</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input id="per_min" type="number" step="0.10" min="0.10" max="50"
                         class="form-control @error('per_min') is-invalid @enderror"
                         name="per_min" value="{{ old('per_min',$policy->per_min) }}">
                  <span class="input-group-text">/ min</span>
                </div>
                <div class="form-hint">Costo por tiempo (tráfico).</div>
                @error('per_min')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Mínimo</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input id="min_total" type="number" step="5" min="10" max="1000"
                         class="form-control @error('min_total') is-invalid @enderror"
                         name="min_total" value="{{ old('min_total',$policy->min_total) }}">
                </div>
                <div class="form-hint">Si el cálculo da menos, se cobra el mínimo.</div>
                @error('min_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
  <label class="form-label">Costo por parada (Stop fee)</label>
  <div class="input-group">
    <span class="input-group-text">$</span>
    <input id="stop_fee" type="number" step="1" min="0" max="9999"
           class="form-control @error('stop_fee') is-invalid @enderror"
           name="stop_fee" value="{{ old('stop_fee',$policy->stop_fee) }}">
  </div>
  <div class="form-hint">Se suma por cada parada (S1/S2).</div>
  @error('stop_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-3">
  <label class="form-label">Slider mínimo (puja)</label>
  <div class="input-group">
    <input id="slider_min_pct" type="number" step="0.01" min="0.50" max="1.00"
           class="form-control @error('slider_min_pct') is-invalid @enderror"
           name="slider_min_pct" value="{{ old('slider_min_pct',$policy->slider_min_pct ?? 0.80) }}">
    <span class="input-group-text">x</span>
  </div>
  <div class="form-hint">0.80 = 80% del recomendado.</div>
  @error('slider_min_pct')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-3">
  <label class="form-label">Slider máximo (puja)</label>
  <div class="input-group">
    <input id="slider_max_pct" type="number" step="0.01" min="1.00" max="1.50"
           class="form-control @error('slider_max_pct') is-invalid @enderror"
           name="slider_max_pct" value="{{ old('slider_max_pct',$policy->slider_max_pct ?? 1.20) }}">
    <span class="input-group-text">x</span>
  </div>
  <div class="form-hint">1.20 = 120% del recomendado.</div>
  @error('slider_max_pct')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>


              <div class="col-md-3">
                <label class="form-label">Multiplicador noche</label>
                <input id="night_multiplier" type="number" step="0.01" min="1.00" max="3.00"
                       class="form-control @error('night_multiplier') is-invalid @enderror"
                       name="night_multiplier" value="{{ old('night_multiplier',$policy->night_multiplier) }}">
                <div class="form-hint">Ej. 1.20 = +20%.</div>
                @error('night_multiplier')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Noche inicia</label>
                <input id="night_start_hour" type="number" min="0" max="23"
                       class="form-control @error('night_start_hour') is-invalid @enderror"
                       name="night_start_hour" value="{{ old('night_start_hour',$policy->night_start_hour) }}">
                <div class="form-hint">Hora local (0–23).</div>
                @error('night_start_hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Noche termina</label>
                <input id="night_end_hour" type="number" min="0" max="23"
                       class="form-control @error('night_end_hour') is-invalid @enderror"
                       name="night_end_hour" value="{{ old('night_end_hour',$policy->night_end_hour) }}">
                <div class="form-hint">Puede cruzar medianoche.</div>
                @error('night_end_hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Redondeo</label>
                <select class="form-select @error('round_mode') is-invalid @enderror" name="round_mode" id="round_mode">
                  <option value="step" @selected(old('round_mode',$policy->round_mode)=='step')>Por pasos (recomendado)</option>
                  <option value="decimals" @selected(old('round_mode',$policy->round_mode)=='decimals')>Por decimales</option>
                </select>
                <div class="form-hint">“Pasos” redondea a $1, $2, $5, etc.</div>
                @error('round_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3" id="wrap_round_step" style="display:none;">
                <label class="form-label">Paso</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input id="round_step" type="number" step="0.50" min="0.50" max="50"
                         class="form-control @error('round_step') is-invalid @enderror"
                         name="round_step" value="{{ old('round_step',$policy->round_step) }}">
                </div>
                <div class="form-hint">Ej. 1.00 = redondear a peso.</div>
                @error('round_step')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3" id="wrap_round_dec" style="display:none;">
                <label class="form-label">Decimales</label>
                <input id="round_decimals" type="number" min="0" max="4"
                       class="form-control @error('round_decimals') is-invalid @enderror"
                       name="round_decimals" value="{{ old('round_decimals',$policy->round_decimals) }}">
                <div class="form-hint">Ej. 0 = pesos enteros.</div>
                @error('round_decimals')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Round to</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input id="round_to" type="number" step="0.50" min="1" max="50"
                         class="form-control @error('round_to') is-invalid @enderror"
                         name="round_to" value="{{ old('round_to',$policy->round_to) }}">
                </div>
                <div class="form-hint">Unidad final (si aplica).</div>
                @error('round_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Vigente desde</label>
                <input type="date" class="form-control @error('active_from') is-invalid @enderror"
                       name="active_from" value="{{ old('active_from', optional($policy->active_from)->format('Y-m-d')) }}">
                @error('active_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              <div class="col-md-3">
                <label class="form-label">Vigente hasta</label>
                <input type="date" class="form-control @error('active_to') is-invalid @enderror"
                       name="active_to" value="{{ old('active_to', optional($policy->active_to)->format('Y-m-d')) }}">
                @error('active_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>

              

            </div>
          </div>

          <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
            <a href="{{ route('admin.fare_policies.index') }}" class="btn btn-outline-secondary">Cancelar</a>
           <button class="btn btn-primary" id="btnSave">
  <i class="ti ti-device-floppy"></i> Guardar
</button>

          </div>
        </div>
      </form>
    </div>

    {{-- Panel de ejemplo (friendly) --}}
    <div class="col-12 col-lg-4">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Ejemplo ({{ $kmExample }} km, {{ $minExample }} min)</h3>
        </div>
        <div class="card-body">
          <div class="text-muted mb-2">
            La app estima un total similar a este, usando los valores que guardes.
          </div>

          <div class="p-3 rounded border bg-light mb-3">
            <div class="fw-semibold mb-1">Día</div>
            <div class="text-muted small" id="calcDay">—</div>
          </div>

          <div class="p-3 rounded border bg-light">
            <div class="fw-semibold mb-1">Noche</div>
            <div class="text-muted small" id="calcNight">—</div>
          </div>

          <div class="alert alert-warning mt-3 mb-0">
            <div class="fw-semibold">Seguridad</div>
            Para evitar errores, el sistema no permite valores en cero o negativos en los campos principales.
          </div>
        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header">
          <h3 class="card-title mb-0">Buenas prácticas</h3>
        </div>
        <div class="card-body text-muted">
          <ul class="mb-0">
            <li>Haz cambios pequeños y prueba con viajes cortos y medianos.</li>
            <li>Si subes mucho el radio o la tarifa, puede afectar aceptación de conductores.</li>
            <li>El mínimo ayuda a evitar cotizaciones demasiado bajas.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

</div>

@push('scripts')
<script>
(function(){
  const demo = @json($demo);

  const kmExample = {{ $kmExample }};
  const minExample = {{ $minExample }};

  function money(v){
    const n = Number(v || 0);
    return '$' + n.toFixed(2);
  }

  function compute(){
    const base = Number(document.getElementById('base_fee')?.value || 0);
    const perKm = Number(document.getElementById('per_km')?.value || 0);
    const perMin = Number(document.getElementById('per_min')?.value || 0);
    const minTotal = Number(document.getElementById('min_total')?.value || 0);
    const nightMul = Math.max(1, Number(document.getElementById('night_multiplier')?.value || 1));

    const subtotal = base + (kmExample * perKm) + (minExample * perMin);
    const totalDay = Math.max(subtotal, minTotal);

    const subtotalNight = subtotal * nightMul;
    const totalNight = Math.max(subtotalNight, minTotal);

    const dayEl = document.getElementById('calcDay');
    const nightEl = document.getElementById('calcNight');

    if(dayEl){
      dayEl.innerHTML = `${money(base)} + (${kmExample} × ${money(perKm)}) + (${minExample} × ${money(perMin)}) = <b>${money(subtotal)}</b><br>`
                      + `Mínimo ${money(minTotal)} → <b>${money(totalDay)}</b>`;
    }
    if(nightEl){
      nightEl.innerHTML = `<b>${money(subtotal)}</b> × ${nightMul.toFixed(2)} = <b>${money(subtotalNight)}</b><br>`
                        + `Mínimo ${money(minTotal)} → <b>${money(totalNight)}</b>`;
    }
  }

  function toggleRound(){
    const el = document.getElementById('round_mode');
    const dec  = document.getElementById('wrap_round_dec');
    const step = document.getElementById('wrap_round_step');
    if(!el) return;
    const mode = el.value;
    if(dec)  dec.style.display  = (mode === 'decimals') ? 'block' : 'none';
    if(step) step.style.display = (mode === 'step') ? 'block' : 'none';
  }

document.querySelector('form')?.addEventListener('submit', () => {
  const b = document.getElementById('btnSave');
  if (b) { b.disabled = true; b.innerText = 'Guardando...'; }
});

  document.getElementById('round_mode')?.addEventListener('change', toggleRound);

  ['base_fee','per_km','per_min','min_total','night_multiplier'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', compute);
  });

  document.getElementById('btnUseDemo')?.addEventListener('click', () => {
    for (const k in demo) {
      const el = document.getElementById(k);
      if(el) el.value = demo[k];
    }
    // round fields
    document.getElementById('round_mode')?.dispatchEvent(new Event('change'));
    compute();
  });

  toggleRound();
  compute();
})();
</script>
@endpush

@endsection
