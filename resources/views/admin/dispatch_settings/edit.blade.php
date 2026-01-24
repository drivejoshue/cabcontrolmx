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
            <li><b>Expiración</b>: tiempo total de vigencia de la oferta/ola (puede reenviarse dentro de esa ventana).</li>
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
              El comportamiento real lo manda el core (tenant 100). Aquí ajustas la política global.
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
      // ===== Base: old() -> row -> default seguro (coincide con tu tabla)
      $v_auto_enabled   = (int) old('auto_enabled', (int)($row->auto_enabled ?? 1));
      $v_auto_delay_sec = (int) old('auto_delay_sec', (int)($row->auto_delay_sec ?? 5));

      // UI delay (auto_dispatch_delay_s) y/o auto_delay_sec
      $v_delay = (int) old('auto_dispatch_delay_s', (int)($row->auto_dispatch_delay_s ?? ($row->auto_delay_sec ?? 5)));
      if ($v_delay <= 0) $v_delay = 1;

      $v_preview_n   = (int) old('auto_dispatch_preview_n', (int)($row->auto_dispatch_preview_n ?? 8));
      $v_preview_km  = (float) old('auto_dispatch_radius_km', (float)($row->auto_dispatch_radius_km ?? 5.0));

      $v_wave_n      = (int) old('wave_size_n', (int)($row->wave_size_n ?? 8));
      $v_expires     = (int) old('offer_expires_sec', (int)($row->offer_expires_sec ?? 180));
      $v_offer_global= (int) old('offer_global_expires_sec', (int)($row->offer_global_expires_sec ?? ($row->offer_expires_sec ?? 180)));
      if ($v_offer_global <= 0) $v_offer_global = $v_expires;

      $v_lead_min    = (int) old('lead_time_min', (int)($row->lead_time_min ?? 15));
      $v_auto_single = (int) old('auto_assign_if_single', (int)($row->auto_assign_if_single ?? 0));

      $v_nearby_km   = (float) old('nearby_search_radius_km', (float)($row->nearby_search_radius_km ?? 5.0));
      $v_stand_km    = (float) old('stand_radius_km', (float)($row->stand_radius_km ?? 3.0));
      $v_use_google  = (int) old('use_google_for_eta', (int)($row->use_google_for_eta ?? 1));

      // Taxi stands adicionales
      $v_taxi_enabled  = (int) old('taxi_stands_enabled', (int)($row->taxi_stands_enabled ?? 1));
      $v_stand_step    = (int) old('stand_step_sec', (int)($row->stand_step_sec ?? 30));
      $v_stand_timeout = (string) old('stand_on_timeout', (string)($row->stand_on_timeout ?? 'saltado'));
      $v_stand_onride  = (int) old('stand_allow_onride', (int)($row->stand_allow_onride ?? 0));
      $v_stand_bonus   = (int) old('stand_onride_eta_bonus_sec', (int)($row->stand_onride_eta_bonus_sec ?? 300));

      // Bidding
      $v_allow_bidding = (int) old('allow_fare_bidding', (int)($row->allow_fare_bidding ?? 0));
      $v_bid_bps       = (int) old('driver_bid_step_bps', (int)($row->driver_bid_step_bps ?? 800));
      $v_bid_min       = (int) old('driver_bid_step_min_amount', (int)($row->driver_bid_step_min_amount ?? 5));
      $v_bid_max       = (int) old('driver_bid_step_max_amount', (int)($row->driver_bid_step_max_amount ?? 25));
      $v_bid_tiers     = (int) old('driver_bid_tiers', (int)($row->driver_bid_tiers ?? 3));
      $v_bid_round     = (int) old('driver_bid_round_to', (int)($row->driver_bid_round_to ?? 5));

      // Queue/SLA/priority (si aún no lo renderizas, lo dejamos listo)
      $v_max_queue     = (int) old('max_queue', (int)($row->max_queue ?? 2));
      $v_queue_sla     = (int) old('queue_sla_minutes', (int)($row->queue_sla_minutes ?? 20));
      $v_central_prio  = (int) old('central_priority', (int)($row->central_priority ?? 1));
      $v_avail_ratio   = old('availability_min_ratio', $row->availability_min_ratio);

      // Client config
      $v_client_ver    = (int) old('client_config_version', (int)($row->client_config_version ?? 1));
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
                <span>Delay UI (seg)</span>
                <span class="badge bg-secondary-lt text-secondary" id="delayBadge">{{ $v_delay }}s</span>
              </label>
              <input class="form-range" type="range"
                     name="auto_dispatch_delay_s" id="delayRange"
                     min="1" max="30" step="1" value="{{ $v_delay }}">
              <div class="text-muted" style="font-size:.85rem;">
                Delay visible/operativo para el arranque del flujo (no dejar en 0).
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Previsualizar (N)</span>
                <span class="badge bg-secondary-lt text-secondary" id="previewNBadge">{{ $v_preview_n }}</span>
              </label>
              <input class="form-range" type="range"
                     name="auto_dispatch_preview_n" id="previewNRange"
                     min="1" max="20" step="1" value="{{ $v_preview_n }}">
              <div class="text-muted" style="font-size:.85rem;">
                Cuántos candidatos se consideran “a la vista” para iniciar.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Radio previsualización (km)</span>
                <span class="badge bg-secondary-lt text-secondary" id="previewKmBadge">{{ number_format($v_preview_km, 1) }} km</span>
              </label>
              <input class="form-range" type="range"
                     name="auto_dispatch_radius_km" id="previewKmRange"
                     min="0.5" max="15" step="0.5" value="{{ $v_preview_km }}">
              <div class="text-muted" style="font-size:.85rem;">
                Área donde el sistema busca candidatos para iniciar el flujo.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Tick delay core (seg)</span>
                <span class="badge bg-secondary-lt text-secondary" id="autoDelayBadge">{{ $v_auto_delay_sec }}s</span>
              </label>
              <input class="form-range" type="range"
                     name="auto_delay_sec" id="autoDelayRange"
                     min="0" max="30" step="1" value="{{ $v_auto_delay_sec }}">
              <div class="text-muted" style="font-size:.85rem;">
                Delay real en core. 0 = inmediato. Recomendado 1–10.
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
              <input class="form-range" type="range"
                     name="wave_size_n" id="waveRange"
                     min="1" max="15" step="1" value="{{ $v_wave_n }}">
              <div class="text-muted" style="font-size:.85rem;">
                Cuántos conductores reciben oferta en cada ronda.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Expiración oferta (seg)</span>
                <span class="badge bg-secondary-lt text-secondary" id="expiresBadge">{{ $v_expires }}s</span>
              </label>
              <input class="form-range" type="range"
                     name="offer_expires_sec" id="expiresRange"
                     min="60" max="900" step="30" value="{{ $v_expires }}">
              <div class="text-muted" style="font-size:.85rem;">
                Ventana de vida para aceptar (y para reintentos dentro del flujo).
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Expiración global (seg)</span>
                <span class="badge bg-secondary-lt text-secondary" id="globalExpiresBadge">{{ $v_offer_global }}s</span>
              </label>
              <input class="form-range" type="range"
                     name="offer_global_expires_sec" id="globalExpiresRange"
                     min="60" max="900" step="30" value="{{ $v_offer_global }}">
              <div class="text-muted" style="font-size:.85rem;">
                Ventana total para completar ola (re-ofertar) antes de rendirse.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Lead time (min)</span>
                <span class="badge bg-secondary-lt text-secondary" id="leadBadge">{{ $v_lead_min }}m</span>
              </label>
              <input class="form-range" type="range"
                     name="lead_time_min" id="leadRange"
                     min="0" max="240" step="5" value="{{ $v_lead_min }}">
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
            Si subes <b>Tamaño de ola</b>, se notifica a más conductores a la vez. Si lo bajas, es más ordenado pero puede tardar más.
          </div>
        </div>
      </div>
    </div>

    {{-- ====== BIDDING ====== --}}
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Bidding (ofertas con puja)</h3>
        </div>
        <div class="card-body">
          <div class="row g-3">

            <div class="col-md-3">
              <label class="form-label">Permitir bidding</label>
              <select name="allow_fare_bidding" class="form-select">
                <option value="1" @selected($v_allow_bidding===1)>Sí</option>
                <option value="0" @selected($v_allow_bidding===0)>No</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Step (%)</span>
                <span class="badge bg-secondary-lt text-secondary" id="bidBpsBadge">{{ number_format($v_bid_bps/100,2) }}%</span>
              </label>
              <input class="form-range" type="range"
                     name="driver_bid_step_bps" id="bidBpsRange"
                     min="50" max="2000" step="50" value="{{ $v_bid_bps }}">
              <div class="text-muted" style="font-size:.85rem;">En basis points. 800 = 8%.</div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Mín monto</span>
                <span class="badge bg-secondary-lt text-secondary" id="bidMinBadge">${{ $v_bid_min }}</span>
              </label>
              <input class="form-range" type="range"
                     name="driver_bid_step_min_amount" id="bidMinRange"
                     min="0" max="200" step="1" value="{{ $v_bid_min }}">
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Máx monto</span>
                <span class="badge bg-secondary-lt text-secondary" id="bidMaxBadge">${{ $v_bid_max }}</span>
              </label>
              <input class="form-range" type="range"
                     name="driver_bid_step_max_amount" id="bidMaxRange"
                     min="0" max="500" step="1" value="{{ $v_bid_max }}">
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Tiers</span>
                <span class="badge bg-secondary-lt text-secondary" id="bidTiersBadge">{{ $v_bid_tiers }}</span>
              </label>
              <input class="form-range" type="range"
                     name="driver_bid_tiers" id="bidTiersRange"
                     min="1" max="10" step="1" value="{{ $v_bid_tiers }}">
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Redondeo</span>
                <span class="badge bg-secondary-lt text-secondary" id="bidRoundBadge">${{ $v_bid_round }}</span>
              </label>
              <input class="form-range" type="range"
                     name="driver_bid_round_to" id="bidRoundRange"
                     min="1" max="50" step="1" value="{{ $v_bid_round }}">
            </div>

          </div>

          <div class="alert alert-info mt-3 mb-0" style="font-size:.9rem;">
            <div class="fw-semibold mb-1">Reglas</div>
            Min/Máx monto se normaliza en backend (si Máx &lt; Min, se corrige).
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
              <input class="form-range" type="range"
                     name="nearby_search_radius_km" id="nearbyRange"
                     min="0.5" max="30" step="0.5" value="{{ $v_nearby_km }}">
              <div class="text-muted" style="font-size:.85rem;">
                Define qué tan lejos se buscan conductores si no hay base cercana.
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label d-flex justify-content-between">
                <span>Radio de base (km)</span>
                <span class="badge bg-secondary-lt text-secondary" id="standBadge">{{ number_format($v_stand_km, 1) }} km</span>
              </label>
              <input class="form-range" type="range"
                     name="stand_radius_km" id="standRange"
                     min="0.2" max="10" step="0.1" value="{{ $v_stand_km }}">
              <div class="text-muted" style="font-size:.85rem;">
                Cuándo se toma en cuenta la cola de un paradero (Taxi Stand).
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Mejorar ETA (opcional)</label>
              <select name="use_google_for_eta" class="form-select">
                <option value="1" @selected($v_use_google === 1)>Sí</option>
                <option value="0" @selected($v_use_google === 0)>No</option>
              </select>
              <div class="text-muted" style="font-size:.85rem;">
                Úsalo solo si tienes Google listo y quieres mejor ETA.
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label">Taxi Stands habilitados</label>
              <select name="taxi_stands_enabled" class="form-select">
                <option value="1" @selected($v_taxi_enabled===1)>Sí</option>
                <option value="0" @selected($v_taxi_enabled===0)>No</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Paso en base (seg)</span>
                <span class="badge bg-secondary-lt text-secondary" id="standStepBadge">{{ $v_stand_step }}s</span>
              </label>
              <input class="form-range" type="range"
                     name="stand_step_sec" id="standStepRange"
                     min="10" max="120" step="5" value="{{ $v_stand_step }}">
            </div>

            <div class="col-md-3">
              <label class="form-label">Al vencer en base</label>
              <select name="stand_on_timeout" class="form-select">
                <option value="saltado" @selected($v_stand_timeout==='saltado')>Saltado (sigue en cola)</option>
                <option value="salio" @selected($v_stand_timeout==='salio')>Salió (lo saca de cola)</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Permitir on_ride en base</label>
              <select name="stand_allow_onride" class="form-select">
                <option value="1" @selected($v_stand_onride===1)>Sí</option>
                <option value="0" @selected($v_stand_onride===0)>No</option>
              </select>
              <div class="text-muted" style="font-size:.85rem;">
                Si está activo, permite ofrecer a drivers ocupados (con bonus ETA).
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label d-flex justify-content-between">
                <span>Bonus ETA on_ride (seg)</span>
                <span class="badge bg-secondary-lt text-secondary" id="standBonusBadge">{{ $v_stand_bonus }}s</span>
              </label>
              <input class="form-range" type="range"
                     name="stand_onride_eta_bonus_sec" id="standBonusRange"
                     min="0" max="1800" step="60" value="{{ $v_stand_bonus }}">
            </div>

          </div>

          <div class="alert alert-warning mt-3 mb-0" style="font-size:.9rem;">
            <div class="fw-semibold mb-1">Qué pasa si lo subes o lo bajas</div>
            <ul class="mb-0">
              <li>Si subes <b>Radio general</b>: encuentras más candidatos, pero pueden estar más lejos.</li>
              <li>Si bajas <b>Radio general</b>: más cerca, pero puedes quedarte sin conductores.</li>
              <li>Si subes <b>Radio de base</b>: la base influye más; si lo bajas, manda más la búsqueda general.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    {{-- ====== CLIENT CONFIG ====== --}}
    <div class="col-12">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Cliente / Cache</h3>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">client_config_version</label>
              <input type="number" class="form-control"
                     name="client_config_version"
                     value="{{ $v_client_ver }}"
                     min="1" step="1">
              <div class="text-muted" style="font-size:.85rem;">
                Sube este número para forzar refresh de config en apps/clients.
              </div>
            </div>
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

{{-- JS mínimo: mostrar valores en badges --}}
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

  bindRange('delayRange', 'delayBadge', v => `${parseInt(v,10)}s`);
  bindRange('previewNRange','previewNBadge', v => `${parseInt(v,10)}`);
  bindRange('previewKmRange','previewKmBadge', v => `${Number(v).toFixed(1)} km`);

  bindRange('autoDelayRange','autoDelayBadge', v => `${parseInt(v,10)}s`);

  bindRange('waveRange','waveBadge', v => `${parseInt(v,10)}`);
  bindRange('expiresRange','expiresBadge', v => `${parseInt(v,10)}s`);
  bindRange('globalExpiresRange','globalExpiresBadge', v => `${parseInt(v,10)}s`);
  bindRange('leadRange','leadBadge', v => `${parseInt(v,10)}m`);

  bindRange('nearbyRange','nearbyBadge', v => `${Number(v).toFixed(1)} km`);
  bindRange('standRange','standBadge', v => `${Number(v).toFixed(1)} km`);

  bindRange('standStepRange','standStepBadge', v => `${parseInt(v,10)}s`);
  bindRange('standBonusRange','standBonusBadge', v => `${parseInt(v,10)}s`);

  bindRange('bidBpsRange','bidBpsBadge', v => `${(parseInt(v,10)/100).toFixed(2)}%`);
  bindRange('bidMinRange','bidMinBadge', v => `$${parseInt(v,10)}`);
  bindRange('bidMaxRange','bidMaxBadge', v => `$${parseInt(v,10)}`);
  bindRange('bidTiersRange','bidTiersBadge', v => `${parseInt(v,10)}`);
  bindRange('bidRoundRange','bidRoundBadge', v => `$${parseInt(v,10)}`);
});
</script>
@endsection
