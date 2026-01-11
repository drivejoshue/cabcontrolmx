@extends('layouts.sysadmin')

@section('title', 'Generación (GMV) - SysAdmin')

@section('content')
@php
  $fmtMoney = fn($v) => '$' . number_format((float)$v, 2);
  $fmtInt   = fn($v) => number_format((int)$v);

  $filters = $filters ?? [];
  $fTenant = $filters['tenant_id'] ?? null;
  $fStatus = $filters['status'] ?? null;
  $fChan   = $filters['requested_channel'] ?? null;
  $fSched  = $filters['scheduled'] ?? '';
  $fStand  = $filters['stand_only'] ?? ''; // ✅ antes taxi_stand_only

  // OJO: tus statuses reales (según schema rides)
  $statusOptions = [
    ''           => 'Todos',
    'requested'  => 'Requested',
    'offered'    => 'Offered',
    'accepted'   => 'Accepted',
    'en_route'   => 'En route',
    'arrived'    => 'Arrived',
    'on_board'   => 'On board',
    'finished'   => 'Finished',
    'canceled'   => 'Canceled',
    'scheduled'  => 'Scheduled',
    'queued'     => 'Queued',
  ];

  $ynOptions = [
    ''  => 'Todos',
    '1' => 'Sí',
    '0' => 'No',
  ];

  // ======= Mapeos visuales (suaves + iconos) =======

  $typeUi = function(string $type) {
    return match($type) {
      'stand'     => ['label'=>'stand',     'icon'=>'ti ti-map-pin',        'class'=>'bg-azure-lt text-azure'],
      'scheduled' => ['label'=>'scheduled', 'icon'=>'ti ti-calendar-time',  'class'=>'bg-purple-lt text-purple'],
      default     => ['label'=>'direct',    'icon'=>'ti ti-bolt',           'class'=>'bg-secondary-lt text-secondary'],
    };
  };

  $statusUi = function(string $st) {
    return match($st) {
      'finished'  => ['icon'=>'ti ti-circle-check', 'class'=>'bg-green-lt text-green'],
      'canceled'  => ['icon'=>'ti ti-circle-x',     'class'=>'bg-red-lt text-red'],
      'on_board'  => ['icon'=>'ti ti-steering-wheel','class'=>'bg-azure-lt text-azure'],
      'arrived'   => ['icon'=>'ti ti-flag-3',       'class'=>'bg-azure-lt text-azure'],
      'en_route'  => ['icon'=>'ti ti-route',        'class'=>'bg-indigo-lt text-indigo'],
      'accepted'  => ['icon'=>'ti ti-user-check',   'class'=>'bg-indigo-lt text-indigo'],
      'offered'   => ['icon'=>'ti ti-gift',         'class'=>'bg-cyan-lt text-cyan'],
      'scheduled' => ['icon'=>'ti ti-calendar',     'class'=>'bg-purple-lt text-purple'],
      'queued'    => ['icon'=>'ti ti-list-numbers', 'class'=>'bg-secondary-lt text-secondary'],
      default     => ['icon'=>'ti ti-circle',       'class'=>'bg-secondary-lt text-secondary'],
    };
  };

  $channelUi = function(string $ch) {
    $ch = $ch ?: '(none)';
    return match($ch) {
      'dispatch'       => ['icon'=>'ti ti-headset',       'class'=>'bg-blue-lt text-blue',   'label'=>$ch],
      'passenger_app'  => ['icon'=>'ti ti-device-mobile', 'class'=>'bg-indigo-lt text-indigo','label'=>$ch],
      'driver_app'     => ['icon'=>'ti ti-steering-wheel','class'=>'bg-azure-lt text-azure', 'label'=>$ch],
      'api'            => ['icon'=>'ti ti-api',           'class'=>'bg-secondary-lt text-secondary','label'=>$ch],
      default          => ['icon'=>'ti ti-adjustments',   'class'=>'bg-secondary-lt text-secondary','label'=>$ch],
    };
  };
@endphp

<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h1 class="mb-0">
        <i class="ti ti-chart-arrows-vertical me-1"></i>
        Generación (GMV) por rides
      </h1>
      <div class="text-muted">
        Reporte global SysAdmin. Basado en <code>rides.requested_at</code> y montos de rides finalizados (<code>status=finished</code>).
        <span class="ms-2"><i class="ti ti-shield-check me-1"></i>No depende de <code>ride_status_history</code>.</span>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary"
         href="{{ route('sysadmin.rides.generation.csv', request()->query()) }}">
        <i class="ti ti-download me-1"></i> Exportar CSV
      </a>
    </div>
  </div>

  {{-- Filtros --}}
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2">
        <div class="col-md-2">
          <label class="form-label"><i class="ti ti-calendar-event me-1"></i>Desde</label>
          <input type="date" name="from" value="{{ request('from', $from ?? '') }}" class="form-control">
        </div>

        <div class="col-md-2">
          <label class="form-label"><i class="ti ti-calendar-event me-1"></i>Hasta</label>
          <input type="date" name="to" value="{{ request('to', $to ?? '') }}" class="form-control">
        </div>

        <div class="col-md-3">
          <label class="form-label"><i class="ti ti-building me-1"></i>Tenant</label>
          <select name="tenant_id" class="form-select">
            <option value="">Todos</option>
            @foreach(($tenantsForSelect ?? []) as $t)
              <option value="{{ $t->id }}" @selected((string)$fTenant === (string)$t->id)>
                #{{ $t->id }} — {{ $t->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label"><i class="ti ti-antenna-bars-5 me-1"></i>Channel</label>
          <select name="requested_channel" class="form-select">
            <option value="">Todos</option>
            @foreach(($channelsForSelect ?? []) as $ch)
              <option value="{{ $ch }}" @selected((string)$fChan === (string)$ch)>{{ $ch }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label"><i class="ti ti-activity me-1"></i>Status</label>
          <select name="status" class="form-select">
            @foreach($statusOptions as $k => $lbl)
              <option value="{{ $k }}" @selected((string)$fStatus === (string)$k)>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label"><i class="ti ti-calendar-time me-1"></i>Scheduled</label>
          <select name="scheduled" class="form-select">
            @foreach($ynOptions as $k=>$lbl)
              <option value="{{ $k }}" @selected((string)$fSched === (string)$k)>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label"><i class="ti ti-map-pin me-1"></i>Taxi stand</label>
          <select name="stand_only" class="form-select"> {{-- ✅ antes taxi_stand_only --}}
            @foreach($ynOptions as $k=>$lbl)
              <option value="{{ $k }}" @selected((string)$fStand === (string)$k)>{{ $lbl }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-4 d-flex align-items-end gap-2">
          <button class="btn btn-primary w-100"><i class="ti ti-filter me-1"></i>Aplicar</button>
          <a class="btn btn-outline-secondary w-100" href="{{ route('sysadmin.rides.generation.index') }}">
            <i class="ti ti-eraser me-1"></i> Limpiar
          </a>
        </div>
      </form>
    </div>
  </div>

  {{-- KPIs (si tu _kpi_card usa badges fuertes, te digo abajo cómo suavizarlos también) --}}
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      @include('sysadmin.rides._kpi_card', [
        'label' => 'Rides (total)',
        'value' => $fmtInt($totals['total'] ?? 0),
        'hint'  => 'Filtrado por requested_at',
      ])
    </div>

    <div class="col-md-3">
      @include('sysadmin.rides._kpi_card', [
        'label' => 'Finalizados',
        'value' => $fmtInt($totals['finished'] ?? 0),
        'badge' => 'OK',
        'badgeClass' => 'bg-green-lt text-green',
        'hint'  => 'status=finished',
      ])
    </div>

    <div class="col-md-3">
      @include('sysadmin.rides._kpi_card', [
        'label' => 'Cancelados',
        'value' => $fmtInt($totals['canceled'] ?? 0),
        'badge' => ($totals['cancel_rate'] ?? 0) . '%',
        'badgeClass' => 'bg-yellow-lt text-yellow',
        'hint'  => 'Cancel rate (sobre total)',
      ])
    </div>

    <div class="col-md-3">
      @include('sysadmin.rides._kpi_card', [
        'label' => 'GMV cobrado (finished)',
        'value' => $fmtMoney($totals['sum_total'] ?? 0),
        'hint'  => 'SUM(total_amount) en finished',
      ])
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      @include('sysadmin.rides._kpi_card', [
        'label' => 'GMV cotizado (finished)',
        'value' => $fmtMoney($totals['sum_quote'] ?? 0),
        'hint'  => 'SUM(quoted_amount) en finished',
      ])
    </div>

    <div class="col-md-3">
      @include('sysadmin.rides._kpi_card', [
        'label' => 'Ticket promedio (finished)',
        'value' => $fmtMoney($totals['avg_total'] ?? 0),
        'hint'  => 'AVG(total_amount) en finished',
      ])
    </div>

    <div class="col-md-3">
      @include('sysadmin.rides._kpi_card', [
        'label' => 'Duración promedio',
        'value' => $fmtInt($totals['avg_duration_s'] ?? 0) . ' s',
        'hint'  => 'AVG(duration_s) en finished',
      ])
    </div>

    <div class="col-md-3">
      @include('sysadmin.rides._kpi_card', [
        'label' => 'Distancia promedio',
        'value' => number_format((float)($totals['avg_distance_m'] ?? 0), 0) . ' m',
        'hint'  => 'AVG(distance_m) en finished',
      ])
    </div>
  </div>

  {{-- Serie diaria --}}
  <div class="card mb-3">
    <div class="card-header">
      <div class="fw-semibold"><i class="ti ti-trending-up me-1"></i>Serie diaria (GMV cobrado)</div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th style="width: 180px;">Día</th>
              <th style="width: 180px;">Rides</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            @forelse(($seriesDaily ?? []) as $r)
              <tr>
                <td class="text-muted">{{ $r->d }}</td>
                <td>{{ $fmtInt($r->n ?? 0) }}</td>
                <td class="fw-semibold">{{ $fmtMoney($r->total ?? 0) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="text-muted text-center p-3">Sin datos en el rango.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Breakdown --}}
  <div class="row g-3 mb-3">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">
          <div class="fw-semibold"><i class="ti ti-building-bank me-1"></i>Top tenants por GMV</div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-vcenter table-striped mb-0">
              <thead>
                <tr>
                  <th>Tenant</th>
                  <th style="width:120px;">Rides</th>
                  <th style="width:120px;">Finished</th>
                  <th style="width:160px;">GMV</th>
                </tr>
              </thead>
              <tbody>
              @forelse(($byTenant ?? []) as $t)
                <tr>
                  <td>
                    <div class="fw-semibold">#{{ $t->tenant_id }} — {{ $t->tenant_name }}</div>
                  </td>
                  <td>{{ $fmtInt($t->rides_n ?? 0) }}</td>
                  <td>{{ $fmtInt($t->finished_n ?? 0) }}</td>
                  <td class="fw-semibold">{{ $fmtMoney($t->gmv_total ?? 0) }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-muted text-center p-3">Sin datos.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-3">
      <div class="card h-100">
        <div class="card-header">
          <div class="fw-semibold"><i class="ti ti-antenna-bars-5 me-1"></i>Por channel</div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-vcenter table-striped mb-0">
              <thead>
                <tr>
                  <th>Channel</th>
                  <th style="width:110px;">Rides</th>
                  <th style="width:140px;">GMV</th>
                </tr>
              </thead>
              <tbody>
              @forelse(($byChannel ?? []) as $c)
                @php $cu = $channelUi($c->channel ?? '(none)'); @endphp
                <tr>
                  <td>
                    <span class="badge {{ $cu['class'] }}">
                      <i class="{{ $cu['icon'] }} me-1"></i>{{ $cu['label'] }}
                    </span>
                  </td>
                  <td>{{ $fmtInt($c->rides_n ?? 0) }}</td>
                  <td class="fw-semibold">{{ $fmtMoney($c->gmv_total ?? 0) }}</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-muted text-center p-3">Sin datos.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-3">
      <div class="card h-100">
        <div class="card-header">
          <div class="fw-semibold"><i class="ti ti-tags me-1"></i>Por tipo (clasificación)</div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-vcenter table-striped mb-0">
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th style="width:110px;">Rides</th>
                  <th style="width:140px;">GMV</th>
                </tr>
              </thead>
              <tbody>
              @forelse(($byType ?? []) as $x)
                @php $tu = $typeUi($x->type ?? 'direct'); @endphp
                <tr>
                  <td>
                    <span class="badge {{ $tu['class'] }}">
                      <i class="{{ $tu['icon'] }} me-1"></i>{{ $tu['label'] }}
                    </span>
                  </td>
                  <td>{{ $fmtInt($x->rides_n ?? 0) }}</td>
                  <td class="fw-semibold">{{ $fmtMoney($x->gmv_total ?? 0) }}</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-muted text-center p-3">Sin datos.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Detalle --}}
  <div class="card">
    <div class="card-header">
      <div class="fw-semibold"><i class="ti ti-list-details me-1"></i>Detalle de rides (paginado)</div>
      <div class="text-muted small">
        Incluye tenant, canal, clasificación (direct/scheduled/stand) y montos.
      </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-vcenter table-striped mb-0">
          <thead>
            <tr>
              <th style="width:90px;">Ride</th>
              <th style="width:220px;">Tenant</th>
              <th style="width:160px;">Requested</th>
              <th style="width:140px;">Status</th>
              <th style="width:170px;">Tipo</th>
              <th style="width:150px;">Channel</th>
              <th style="width:140px;">GMV</th>
              <th style="width:140px;">Quote</th>
              <th style="width:170px;">Vehículo</th>
              <th style="width:180px;">Conductor</th>
              <th>Ruta</th>
            </tr>
          </thead>
          <tbody>
          @forelse(($rides ?? []) as $r)
            @php
              // ✅ Tipo correcto con tu esquema
              $type = 'direct';
              if (!empty($r->stand_id)) $type = 'stand';
              elseif (!empty($r->scheduled_for)) $type = 'scheduled';

              $tu = $typeUi($type);

              $st = (string)($r->status ?? '');
              $su = $statusUi($st);

              $cu = $channelUi((string)($r->requested_channel ?? '(none)'));

              $veh = trim(($r->vehicle_economico ? ('#'.$r->vehicle_economico) : '') . ' ' . ($r->vehicle_plate ?? ''));
              $veh = $veh ?: '—';
              $drv = $r->driver_name ?: '—';

              $gmv = ($st === 'finished') ? (float)($r->total_amount ?? 0) : 0.0;
              $qte = ($st === 'finished') ? (float)($r->quoted_amount ?? 0) : 0.0;

              $standInfo = '';
              if (!empty($r->stand_id)) {
                $standInfo = trim(($r->stand_code ? ('['.$r->stand_code.'] ') : '') . ($r->stand_name ?? ''));
              }
            @endphp

            <tr>
              <td class="fw-semibold">#{{ $r->id }}</td>

              <td>
                <div class="fw-semibold">
                  <i class="ti ti-building me-1 text-muted"></i>
                  #{{ $r->tenant_id }} — {{ $r->tenant_name }}
                </div>
              </td>

              <td class="text-muted">
                {{ $r->requested_at ? \Illuminate\Support\Carbon::parse($r->requested_at)->format('Y-m-d H:i') : '—' }}
                @if(!empty($r->scheduled_for))
                  <div class="small mt-1">
                    <span class="badge bg-purple-lt text-purple">
                      <i class="ti ti-calendar-time me-1"></i>scheduled
                    </span>
                    <span class="text-muted">{{ \Illuminate\Support\Carbon::parse($r->scheduled_for)->format('Y-m-d H:i') }}</span>
                  </div>
                @endif
              </td>

              <td>
                <span class="badge {{ $su['class'] }}">
                  <i class="{{ $su['icon'] }} me-1"></i>{{ $st }}
                </span>
              </td>

              <td>
                <span class="badge {{ $tu['class'] }}">
                  <i class="{{ $tu['icon'] }} me-1"></i>{{ $tu['label'] }}
                </span>

                @if(!empty($standInfo))
                  <div class="text-muted small mt-1">
                    <i class="ti ti-map-pin me-1"></i>{{ $standInfo }}
                  </div>
                @endif
              </td>

              <td>
                <span class="badge {{ $cu['class'] }}">
                  <i class="{{ $cu['icon'] }} me-1"></i>{{ $cu['label'] }}
                </span>
              </td>

              <td class="fw-semibold">{{ $fmtMoney($gmv) }}</td>
              <td>{{ $fmtMoney($qte) }}</td>

              <td><i class="ti ti-car me-1 text-muted"></i>{{ $veh }}</td>
              <td><i class="ti ti-user me-1 text-muted"></i>{{ $drv }}</td>

              <td>
                <div class="text-truncate" style="max-width: 520px;">
                  <span class="text-muted">{{ $r->origin_label ?? '—' }}</span>
                  <span class="text-muted">→</span>
                  <span class="text-muted">{{ $r->dest_label ?? '—' }}</span>
                </div>
                <div class="text-muted small">
                  <i class="ti ti-road me-1"></i>
                  {{ $r->distance_m ? number_format((float)$r->distance_m/1000, 1) . ' km' : '—' }}
                  <span class="mx-1">·</span>
                  <i class="ti ti-clock me-1"></i>
                  {{ $r->duration_s ? number_format((int)($r->duration_s/60)) . ' min' : '—' }}
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="11" class="text-muted text-center p-3">No hay rides en el rango/filtros.</td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card-footer">
      {{ $rides->links() }}
    </div>
  </div>

</div>
@endsection
