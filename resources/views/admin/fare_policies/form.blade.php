@extends('layouts.admin')
@section('title','Editar tarifa')
@section('content')

<form method="POST" class="row g-3" action="{{ route('admin.fare_policies.update') }}">
  @csrf
  @method('PUT')

  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Parámetros de tarifa</h5>
        <span class="badge text-bg-secondary">Tenant: {{ $tenantId }}</span>
      </div>
      <div class="card-body row g-3">

        <div class="col-md-2">
          <label class="form-label">Modo</label>
          <select class="form-select" name="mode">
            <option value="meter" @selected(old('mode',$policy->mode)=='meter')>Meter</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Base</label>
          <input type="number" step="0.01" class="form-control" name="base_fee" value="{{ old('base_fee',$policy->base_fee) }}">
        </div>

        <div class="col-md-2">
          <label class="form-label">Por km</label>
          <input type="number" step="0.01" class="form-control" name="per_km" value="{{ old('per_km',$policy->per_km) }}">
        </div>

        <div class="col-md-2">
          <label class="form-label">Por min</label>
          <input type="number" step="0.01" class="form-control" name="per_min" value="{{ old('per_min',$policy->per_min) }}">
        </div>

        <div class="col-md-2">
          <label class="form-label">Mínimo</label>
          <input type="number" step="0.01" class="form-control" name="min_total" value="{{ old('min_total',$policy->min_total) }}">
        </div>

        <div class="col-md-2">
          <label class="form-label">Mult. noche</label>
          <input type="number" step="0.01" class="form-control" name="night_multiplier" value="{{ old('night_multiplier',$policy->night_multiplier) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label">Noche inicia (0-23)</label>
          <input type="number" min="0" max="23" class="form-control" name="night_start_hour" value="{{ old('night_start_hour',$policy->night_start_hour) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label">Noche termina (0-23)</label>
          <input type="number" min="0" max="23" class="form-control" name="night_end_hour" value="{{ old('night_end_hour',$policy->night_end_hour) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label">Redondeo</label>
          <select class="form-select" name="round_mode" id="round_mode">
            <option value="step" @selected(old('round_mode',$policy->round_mode)=='step')>Step</option>
            <option value="decimals" @selected(old('round_mode',$policy->round_mode)=='decimals')>Decimals</option>
          </select>
        </div>

        <div class="col-md-3" id="wrap_round_dec" style="display:none;">
          <label class="form-label">Decimales</label>
          <input type="number" min="0" max="4" class="form-control" name="round_decimals" value="{{ old('round_decimals',$policy->round_decimals) }}">
        </div>

        <div class="col-md-3" id="wrap_round_step" style="display:none;">
          <label class="form-label">Paso</label>
          <input type="number" step="0.01" class="form-control" name="round_step" value="{{ old('round_step',$policy->round_step) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label">Round to</label>
          <input type="number" step="0.01" class="form-control" name="round_to" value="{{ old('round_to',$policy->round_to) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label">Vigencia desde</label>
          <input type="date" class="form-control" name="active_from" value="{{ old('active_from', optional($policy->active_from)->format('Y-m-d')) }}">
        </div>

        <div class="col-md-3">
          <label class="form-label">Vigencia hasta</label>
          <input type="date" class="form-control" name="active_to" value="{{ old('active_to', optional($policy->active_to)->format('Y-m-d')) }}">
        </div>

        <div class="col-12">
          <label class="form-label">Extras (JSON)</label>
          <textarea class="form-control font-monospace" rows="4" name="extras" placeholder='{"aeropuerto":30,"mascotas":10}'>{{ old('extras', $policy->extras ? json_encode($policy->extras, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
          <div class="form-text">Clave-valor adicional (validado como JSON).</div>
        </div>
      </div>

      <div class="card-footer bg-transparent border-0 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.fare_policies.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary shadow">Guardar</button>
      </div>
    </div>
  </div>
</form>

@push('scripts')
<script>
(function(){
  function toggleRound(){
    const mode = document.getElementById('round_mode').value;
    document.getElementById('wrap_round_dec').style.display  = (mode==='decimals') ? 'block' : 'none';
    document.getElementById('wrap_round_step').style.display = (mode==='step')     ? 'block' : 'none';
  }
  document.getElementById('round_mode')?.addEventListener('change', toggleRound);
  toggleRound();
})();
</script>
@endpush

@endsection
