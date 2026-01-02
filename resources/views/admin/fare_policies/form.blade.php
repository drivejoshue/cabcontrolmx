@extends('layouts.admin')
@section('title','Editar tarifa')
@section('content')

@php
  $base = (float) old('base_fee', $policy->base_fee ?? 0);
  $km   = (float) old('per_km', $policy->per_km ?? 0);
  $min  = (float) old('per_min', $policy->per_min ?? 0);
  $isZeroCore = ($base <= 0.0 && $km <= 0.0 && $min <= 0.0);

  // Valores demo (solo para mostrar badges)
  $demo = [
    'base_fee' => 35,
    'per_km'   => 12,
    'per_min'  => 2,
    'min_total'=> 50,
    'night_mul'=> 1.20,
    'night_start' => 22,
    'night_end'   => 6,
    'round_step'  => 1.00,
  ];
@endphp

<div class="row g-3">

  {{-- Header / contexto --}}
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0">Editar tarifa</h3>
        <div class="text-muted small">
          Ajusta <strong>Base</strong>, <strong>Por km</strong> y <strong>Por min</strong> para habilitar cotización y ofertas.
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge text-bg-secondary">Tenant #{{ $tenantId }}</span>
        <span class="badge {{ $isZeroCore ? 'text-bg-warning' : 'text-bg-success' }}">
          {{ $isZeroCore ? 'Falta configurar' : 'Configurada' }}
        </span>
      </div>
    </div>
  </div>

  {{-- Alertas de validación --}}
  <div class="col-12">
    @if($errors->any())
      <div class="alert alert-danger mb-0">
        <div class="fw-semibold">Revisa los campos marcados</div>
        <div class="small text-muted">Hay errores de validación. Corrige y vuelve a guardar.</div>
      </div>
    @elseif($isZeroCore)
      <div class="alert alert-warning mb-0">
        <div class="fw-semibold">Tu tarifa está en ceros</div>
        <div class="small text-muted">
          Si dejas <strong>Base</strong>, <strong>Por km</strong> y <strong>Por min</strong> en 0, la app podrá cotizar en $0 o no ofertar.
          Configura esos 3 campos y guarda.
        </div>
      </div>
    @else
      <div class="alert alert-info mb-0">
        <div class="fw-semibold">Fórmula de cálculo</div>
        <div class="small text-muted">
          Total = Base + (Km × Por km) + (Min × Por min) · (Multiplicador noche si aplica) y luego se aplica el Mínimo.
        </div>
      </div>
    @endif
  </div>

  {{-- Tips rápidos (sin JS extra, puro UI) --}}
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <span class="badge text-bg-light border">Sugerencia demo (MXN)</span>
          <span class="badge text-bg-primary">Base {{ number_format($demo['base_fee'],2) }}</span>
          <span class="badge text-bg-primary">Por km {{ number_format($demo['per_km'],2) }}</span>
          <span class="badge text-bg-primary">Por min {{ number_format($demo['per_min'],2) }}</span>
          <span class="badge text-bg-primary">Mínimo {{ number_format($demo['min_total'],2) }}</span>
          <span class="badge text-bg-dark">Noche x{{ number_format($demo['night_mul'],2) }}</span>
          <span class="badge text-bg-secondary">Noche {{ $demo['night_start'] }}–{{ $demo['night_end'] }}</span>
          <span class="badge text-bg-secondary">Redondeo ${{ number_format($demo['round_step'],2) }}</span>
        </div>
        <div class="text-muted small mt-2">
          Puedes copiar estos valores como base y luego ajustar a la realidad de la central.
        </div>
      </div>
    </div>
  </div>

  {{-- Formulario --}}
  <div class="col-12">
    <form method="POST" action="{{ route('admin.fare_policies.update') }}">
      @csrf
      @method('PUT')

      <div class="card shadow-sm border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Parámetros</h5>
          <span class="text-muted small">Campos clave:
            <span class="badge text-bg-primary">Base</span>
            <span class="badge text-bg-primary">Por km</span>
            <span class="badge text-bg-primary">Por min</span>
          </span>
        </div>

        <div class="card-body">
          <div class="row g-3">

            <div class="col-md-2">
              <label class="form-label">Modo</label>
              <select class="form-select" name="mode">
                <option value="meter" @selected(old('mode',$policy->mode)=='meter')>Meter</option>
              </select>
              <div class="form-text">Modo actual del taxímetro.</div>
              @error('mode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label class="form-label">
                Base <span class="badge text-bg-primary ms-1">clave</span>
              </label>
              <input type="number" step="0.01" min="0" class="form-control @error('base_fee') is-invalid @enderror"
                     name="base_fee" value="{{ old('base_fee',$policy->base_fee) }}">
              <div class="form-text">Se cobra siempre al iniciar.</div>
              @error('base_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label class="form-label">
                Por km <span class="badge text-bg-primary ms-1">clave</span>
              </label>
              <input type="number" step="0.01" min="0" class="form-control @error('per_km') is-invalid @enderror"
                     name="per_km" value="{{ old('per_km',$policy->per_km) }}">
              <div class="form-text">Costo por distancia.</div>
              @error('per_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label class="form-label">
                Por min <span class="badge text-bg-primary ms-1">clave</span>
              </label>
              <input type="number" step="0.01" min="0" class="form-control @error('per_min') is-invalid @enderror"
                     name="per_min" value="{{ old('per_min',$policy->per_min) }}">
              <div class="form-text">Costo por tiempo.</div>
              @error('per_min')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label class="form-label">
                Mínimo <span class="badge text-bg-light border ms-1">recomendado</span>
              </label>
              <input type="number" step="0.01" min="0" class="form-control @error('min_total') is-invalid @enderror"
                     name="min_total" value="{{ old('min_total',$policy->min_total) }}">
              <div class="form-text">Tope mínimo a cobrar.</div>
              @error('min_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label class="form-label">
                Mult. noche <span class="badge text-bg-light border ms-1">opcional</span>
              </label>
              <input type="number" step="0.01" min="0" class="form-control @error('night_multiplier') is-invalid @enderror"
                     name="night_multiplier" value="{{ old('night_multiplier',$policy->night_multiplier) }}">
              <div class="form-text">Ej. 1.20 = +20%.</div>
              @error('night_multiplier')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
              <label class="form-label">Noche inicia (0-23)</label>
              <input type="number" min="0" max="23"
                     class="form-control @error('night_start_hour') is-invalid @enderror"
                     name="night_start_hour" value="{{ old('night_start_hour',$policy->night_start_hour) }}">
              <div class="form-text">Hora local del tenant.</div>
              @error('night_start_hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
              <label class="form-label">Noche termina (0-23)</label>
              <input type="number" min="0" max="23"
                     class="form-control @error('night_end_hour') is-invalid @enderror"
                     name="night_end_hour" value="{{ old('night_end_hour',$policy->night_end_hour) }}">
              <div class="form-text">Puede cruzar medianoche.</div>
              @error('night_end_hour')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
              <label class="form-label">Redondeo</label>
              <select class="form-select @error('round_mode') is-invalid @enderror" name="round_mode" id="round_mode">
                <option value="step" @selected(old('round_mode',$policy->round_mode)=='step')>Step</option>
                <option value="decimals" @selected(old('round_mode',$policy->round_mode)=='decimals')>Decimals</option>
              </select>
              <div class="form-text">
                Step = redondear por pasos; Decimals = limitar decimales.
              </div>
              @error('round_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3" id="wrap_round_dec" style="display:none;">
              <label class="form-label">Decimales</label>
              <input type="number" min="0" max="4"
                     class="form-control @error('round_decimals') is-invalid @enderror"
                     name="round_decimals" value="{{ old('round_decimals',$policy->round_decimals) }}">
              <div class="form-text">Ej. 0 = pesos enteros.</div>
              @error('round_decimals')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3" id="wrap_round_step" style="display:none;">
              <label class="form-label">Paso</label>
              <input type="number" step="0.01" min="0"
                     class="form-control @error('round_step') is-invalid @enderror"
                     name="round_step" value="{{ old('round_step',$policy->round_step) }}">
              <div class="form-text">Ej. 1.00 = redondear a peso.</div>
              @error('round_step')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
              <label class="form-label">Round to</label>
              <input type="number" step="0.01" min="0"
                     class="form-control @error('round_to') is-invalid @enderror"
                     name="round_to" value="{{ old('round_to',$policy->round_to) }}">
              <div class="form-text">Unidad final (si aplica).</div>
              @error('round_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
              <label class="form-label">Vigencia desde</label>
              <input type="date"
                     class="form-control @error('active_from') is-invalid @enderror"
                     name="active_from" value="{{ old('active_from', optional($policy->active_from)->format('Y-m-d')) }}">
              <div class="form-text">Opcional.</div>
              @error('active_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
              <label class="form-label">Vigencia hasta</label>
              <input type="date"
                     class="form-control @error('active_to') is-invalid @enderror"
                     name="active_to" value="{{ old('active_to', optional($policy->active_to)->format('Y-m-d')) }}">
              <div class="form-text">Opcional.</div>
              @error('active_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
              <label class="form-label">
                Extras (JSON) <span class="badge text-bg-light border ms-1">opcional</span>
              </label>
              <textarea class="form-control font-monospace @error('extras') is-invalid @enderror"
                        rows="4" name="extras"
                        placeholder='{"aeropuerto":30,"mascotas":10}'>{{ old('extras', $policy->extras ? json_encode($policy->extras, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>

              <div class="d-flex flex-wrap gap-2 mt-2">
                <span class="badge text-bg-secondary">Ejemplos</span>
                <span class="badge text-bg-light border">{ "aeropuerto": 30 }</span>
                <span class="badge text-bg-light border">{ "mascotas": 10 }</span>
                <span class="badge text-bg-light border">{ "peaje": 25 }</span>
              </div>

              <div class="form-text mt-2">Debe ser JSON válido.</div>
              @error('extras')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

          </div>
        </div>

        <div class="card-footer bg-transparent border-0 d-flex justify-content-end gap-2">
          <a href="{{ route('admin.fare_policies.index') }}" class="btn btn-outline-secondary">Cancelar</a>
          <button class="btn btn-primary shadow">Guardar</button>
        </div>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
(function(){
  function toggleRound(){
    const el = document.getElementById('round_mode');
    if(!el) return;
    const mode = el.value;
    const dec  = document.getElementById('wrap_round_dec');
    const step = document.getElementById('wrap_round_step');
    if(dec)  dec.style.display  = (mode==='decimals') ? 'block' : 'none';
    if(step) step.style.display = (mode==='step') ? 'block' : 'none';
  }
  document.getElementById('round_mode')?.addEventListener('change', toggleRound);
  toggleRound();
})();
</script>
@endpush

@endsection
