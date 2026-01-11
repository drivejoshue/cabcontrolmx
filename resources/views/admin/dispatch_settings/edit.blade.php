{{-- resources/views/admin/dispatch_settings/edit.blade.php --}}
@extends('layouts.admin')

@section('title','Ajustes de Despacho')

@section('content')
<div class="container-fluid px-0">

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">Revisa estos campos:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  {{-- ========= GUÍA FRIENDLY ========= --}}
  <div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div>
        <h3 class="card-title mb-0">Ajustes de despacho</h3>
        <div class="text-muted" style="font-size:.85rem;">
          Configura cómo se buscan conductores y cómo se envían ofertas. Los mínimos están protegidos para evitar fallas.
        </div>
      </div>
      <span class="badge bg-secondary-lt text-secondary">
        Central (Tenant): {{ auth()->user()->tenant_id ?? 'sin-tenant' }}
      </span>
    </div>

    <div class="card-body">
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="fw-semibold mb-2">Cómo funciona (explicado simple)</div>
          <ul class="mb-0" style="font-size:.9rem;">
            <li><b>Auto Dispatch</b>: inicia el envío automático de ofertas.</li>
            <li><b>Olas</b>: se mandan ofertas en “rondas” a varios conductores.</li>
            <li><b>Expiración</b>: Es el tiempo que la oferta estara disponible para ser enviada, puede reenviarse varias veces durante ese tiempo mientras algun conductor acepta.</li>
            <li><b>Radios</b>: definen qué tan lejos buscamos conductores y cuándo tomamos en cuenta una base (Taxi Stand).</li>
          </ul>

          <div class="alert alert-warning mt-3 mb-0" style="font-size:.9rem;">
            <div class="fw-semibold mb-1">Regla rápida</div>
            Cambia <b>una cosa a la vez</b> y prueba unos viajes. Si cambias varias, se vuelve difícil saber qué ayudó.
          </div>
        </div>

        <div class="col-lg-5">
          <div class="alert alert-info mb-0" style="font-size:.9rem;">
            <div class="fw-semibold mb-1">Ejemplo fácil (5 km)</div>

            <div class="mb-2">
              Si el viaje está a <b>5 km</b> de conductores:
              <ul class="mb-2">
                <li>Con <b>Radio general ≥ 5</b> → entran a la búsqueda.</li>
                <li>Con <b>Radio general &lt; 5</b> → es posible que no encuentre conductor.</li>
              </ul>

              Si existe una base (Taxi Stand):
              <ul class="mb-0">
                <li>Con <b>Radio de base</b> suficiente → la base puede priorizarse.</li>
                <li>Con <b>Radio de base</b> muy chico → la base puede “no contar” aunque esté cerca.</li>
              </ul>
            </div>

            <div class="text-muted" style="font-size:.85rem;">
              Los sectores y bases ayudan a ordenar el despacho. El radio final de alcance se ajusta en el core.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ========= FORM ========= --}}
  <form class="row g-3" method="POST" action="{{ route('admin.dispatch_settings.update') }}">
    @csrf
    @method('PUT')

    @php
      // Valores actuales (old -> row)
      $v_auto_enabled = (int) old('auto_enabled', (int)($row->auto_enabled ?? 1));
      $v_delay        = (int) old('auto_dispatch_delay_s', (int)($row->auto_dispatch_delay_s ?? 2));
      $v_preview_n     = (int) old('auto_dispatch_preview_n', (int)($row->auto_dispatch_preview_n ?? 8));
      $v_preview_km    = (float) old('auto_dispatch_radius_km', (float)($row->auto_dispatch_radius_km ?? 8));

      $v_wave_n        = (int) old('wave_size_n', (int)($row->wave_size_n ?? 8));
      $v_expires       = (int) old('offer_expires_sec', (int)($row->offer_expires_sec ?? 25));
      $v_lead_min      = (int) old('lead_time_min', (int)($row->lead_time_min ?? 0));
      $v_auto_single   = (int) old('auto_assign_if_single', (int)($row->auto_assign_if_single ?? 0));

      $v_nearby_km     = (float) old('nearby_search_radius_km', (float)($row->nearby_search_radius_km ?? 8));
      $v_stand_km      = (float) old('stand_radius_km', (float)($row->stand_radius_km ?? 1.5));
      $v_use_google    = (int) old('use_google_for_eta', (int)($row->use_google_for_eta ?? 0));
    @endphp

    {{-- ====== AUTO DISPATCH ====== --}}
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Auto Dispatch</h3>
        </div>

        <div class="card-body">
          <div class="row g-3">

            <div class="col-md-3">
              <label class="form-label">Habilitado</label>
              <select name="auto_enabled" class="form-select">
                <option value="1" @selected($v_auto_enabled === 1)>Sí</option>
                <option value="0" @selected($v_auto_enabled === 0)>No</option>
              </select>
              <div class="text-muted" style="font-size:.85rem;">Activa o desactiva el despacho automático.</div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Delay (seg)</span>
                <span class="badge bg-secondary-lt text-secondary" id="delayBadge">{{ $v_delay }}s</span>
              </label>
              <input
                class="form-range"
                type="range"
                name="auto_dispatch_delay_s"
                id="delayRange"
                min="1" max="30" step="1"
                value="{{ $v_delay }}"
              >
              <div class="text-muted" style="font-size:.85rem;">
                Espera breve antes de iniciar. Ayuda a evitar que el sistema “arranque en seco”.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Previsualizar (N)</span>
                <span class="badge bg-secondary-lt text-secondary" id="previewNBadge">{{ $v_preview_n }}</span>
              </label>
              <input
                class="form-range"
                type="range"
                name="auto_dispatch_preview_n"
                id="previewNRange"
                min="1" max="20" step="1"
                value="{{ $v_preview_n }}"
              >
              <div class="text-muted" style="font-size:.85rem;">
                Cuántos candidatos se consideran “a la vista” para el flujo automático.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Radio de previsualización (km)</span>
                <span class="badge bg-secondary-lt text-secondary" id="previewKmBadge">{{ number_format($v_preview_km, 1) }} km</span>
              </label>
              <input
                class="form-range"
                type="range"
                name="auto_dispatch_radius_km"
                id="previewKmRange"
                min="0.5" max="15" step="0.5"
                value="{{ $v_preview_km }}"
              >
              <div class="text-muted" style="font-size:.85rem;">
                Área donde el sistema busca candidatos para iniciar el flujo.
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    {{-- ====== OLAS Y EXPIRACIÓN ====== --}}
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Olas y tiempo de respuesta</h3>
        </div>

        <div class="card-body">
          <div class="row g-3">

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Tamaño de ola (N)</span>
                <span class="badge bg-secondary-lt text-secondary" id="waveBadge">{{ $v_wave_n }}</span>
              </label>
              <input
                class="form-range"
                type="range"
                name="wave_size_n"
                id="waveRange"
                min="1" max="15" step="1"
                value="{{ $v_wave_n }}"
              >
              <div class="text-muted" style="font-size:.85rem;">
                Cuántos conductores reciben oferta en cada ronda.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Tiempo de expiracion de oferta (seg)</span>
                <span class="badge bg-secondary-lt text-secondary" id="expiresBadge">{{ $v_expires }}s</span>
              </label>
              <input
                class="form-range"
                type="range"
                name="offer_expires_sec"
                id="expiresRange"
                min="60" max="300" step="30"
                value="{{ $v_expires }}"
              >
              <div class="text-muted" style="font-size:.85rem;">
                Es el tiempo que la oferta estara visible para autokick .
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Lead time (min)</span>
                <span class="badge bg-secondary-lt text-secondary" id="leadBadge">{{ $v_lead_min }}m</span>
              </label>
              <input
                class="form-range"
                type="range"
                name="lead_time_min"
                id="leadRange"
                min="0" max="120" step="5"
                value="{{ $v_lead_min }}"
              >
              <div class="text-muted" style="font-size:.85rem;">
                Para programados: minutos mínimos antes de intentar asignar.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label">Auto-asignar si solo hay 1</label>
              <select name="auto_assign_if_single" class="form-select">
                <option value="1" @selected($v_auto_single === 1)>Sí</option>
                <option value="0" @selected($v_auto_single === 0)>No</option>
              </select>
              <div class="text-muted" style="font-size:.85rem;">
                Si hay un solo candidato claro, asigna sin esperar rondas.
              </div>
            </div>

          </div>

          <div class="alert alert-info mt-3 mb-0" style="font-size:.9rem;">
            <div class="fw-semibold mb-1">Consejo rápido</div>
            Si subes <b>Tamaño de ola</b>, se notifica a más conductores a la vez. Si lo bajas, es más “ordenado” pero puede tardar más.
          </div>
        </div>
      </div>
    </div>

    {{-- ====== BÚSQUEDA Y BASES ====== --}}
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Búsqueda y bases (Taxi Stands)</h3>
        </div>

        <div class="card-body">
          <div class="row g-3">

            <div class="col-md-4">
              <label class="form-label d-flex justify-content-between">
                <span>Radio general (km)</span>
                <span class="badge bg-secondary-lt text-secondary" id="nearbyBadge">{{ number_format($v_nearby_km, 1) }} km</span>
              </label>
              <input
                class="form-range"
                type="range"
                name="nearby_search_radius_km"
                id="nearbyRange"
                min="0.5" max="30" step="0.5"
                value="{{ $v_nearby_km }}"
              >
              <div class="text-muted" style="font-size:.85rem;">
                Define qué tan lejos se buscan conductores si no hay una base cercana.
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label d-flex justify-content-between">
                <span>Radio de base (km)</span>
                <span class="badge bg-secondary-lt text-secondary" id="standBadge">{{ number_format($v_stand_km, 1) }} km</span>
              </label>
              <input
                class="form-range"
                type="range"
                name="stand_radius_km"
                id="standRange"
                min="0.2" max="10" step="0.1"
                value="{{ $v_stand_km }}"
              >
              <div class="text-muted" style="font-size:.85rem;">
                Define cuándo se toma en cuenta la cola de un paradero (Taxi Stand).
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Mejorar ETA (opcional)</label>
              <select name="use_google_for_eta" class="form-select">
                <option value="1" @selected($v_use_google === 1)>Sí</option>
                <option value="0" @selected($v_use_google === 0)>No</option>
              </select>
              <div class="text-muted" style="font-size:.85rem;">
                Puede mejorar tiempos estimados. Actívalo solo si ya lo tienes planeado para tu operación.
              </div>
            </div>

          </div>

          <div class="alert alert-warning mt-3 mb-0" style="font-size:.9rem;">
            <div class="fw-semibold mb-1">Qué pasa si lo subes o lo bajas</div>
            <ul class="mb-0">
              <li>Si subes <b>Radio general</b>: encuentras más candidatos, pero pueden estar más lejos.</li>
              <li>Si bajas <b>Radio general</b>: todo es más “cerca”, pero puedes quedarte sin conductores.</li>
              <li>Si subes <b>Radio de base</b>: la base puede influir más; si lo bajas, manda más la búsqueda general.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    {{-- ====== GUARDAR ====== --}}
    <div class="col-12">
      <div class="d-flex justify-content-end">
        <button class="btn btn-primary btn-lg">
          <i data-feather="save"></i> Guardar cambios
        </button>
      </div>
    </div>

  </form>
</div>

{{-- JS mínimo: solo mostrar valores en badges --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
  function bindRange(rangeId, badgeId, fmt) {
    const r = document.getElementById(rangeId);
    const b = document.getElementById(badgeId);
    if (!r || !b) return;
    const render = () => b.textContent = fmt(r.value);
    r.addEventListener('input', render);
    render();
  }

  bindRange('delayRange',    'delayBadge',    v => `${parseInt(v,10)}s`);
  bindRange('previewNRange', 'previewNBadge', v => `${parseInt(v,10)}`);
  bindRange('previewKmRange','previewKmBadge',v => `${Number(v).toFixed(1)} km`);

  bindRange('waveRange',     'waveBadge',     v => `${parseInt(v,10)}`);
  bindRange('expiresRange',  'expiresBadge',  v => `${parseInt(v,10)}s`);
  bindRange('leadRange',     'leadBadge',     v => `${parseInt(v,10)}m`);

  bindRange('nearbyRange',   'nearbyBadge',   v => `${Number(v).toFixed(1)} km`);
  bindRange('standRange',    'standBadge',    v => `${Number(v).toFixed(1)} km`);
});
</script>
@endsection
