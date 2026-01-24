@extends('layouts.partner')

@section('title', 'Reporte · Viaje')
@section('page-id','partner-reports-rides-show')

@php
  use Carbon\Carbon;

  $amount = (float)($ride->agreed_amount ?? $ride->total_amount ?? $ride->quoted_amount ?? 0);

  $statusLabel = match((string)$ride->status) {
    'finished' => 'Finalizado',
    'canceled' => 'Cancelado',
    'accepted' => 'Aceptado',
    'en_route' => 'En camino',
    'arrived'  => 'Llegó',
    'on_board' => 'En viaje',
    'offered'  => 'Ofertado',
    'requested'=> 'Solicitado',
    'queued'   => 'En cola',
    'scheduled'=> 'Programado',
    default    => (string)$ride->status,
  };

  $badgeClass = match((string)$ride->status) {
    'finished' => 'bg-success-lt text-success',
    'canceled' => 'bg-danger-lt text-danger',
    'accepted','en_route','arrived','on_board' => 'bg-primary-lt text-primary',
    default => 'bg-secondary-lt text-secondary',
  };

  $channelLabel = 'App de pasajero'; // en partner todo es app

  $vehText = trim(($ride->vehicle_economico ?? '').' · '.($ride->vehicle_plate ?? ''));
  $vehText = $vehText && $vehText !== '·' ? $vehText : ($ride->vehicle_id ? ('#'.$ride->vehicle_id) : '—');

  $fmtDiff = function($a, $b) {
    if (!$a || !$b) return '—';
    try {
      $da = Carbon::parse($a);
      $db = Carbon::parse($b);
      $s = $da->diffInSeconds($db, false);
      if ($s < 0) $s = abs($s);
      $m = intdiv($s, 60);
      $ss = $s % 60;
      $h = intdiv($m, 60);
      $mm = $m % 60;
      return $h > 0 ? sprintf('%d:%02d:%02d', $h, $mm, $ss) : sprintf('%d:%02d', $mm, $ss);
    } catch (\Throwable $e) { return '—'; }
  };

  $finalAt = $ride->finished_at ?? $ride->canceled_at ?? null;

  // ratings (si existen)
  $pRating = $ratings['passenger'][0]->rating ?? null;
  $dRating = $ratings['driver'][0]->rating ?? null;
@endphp

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h1 class="h3 mb-0">Viaje #{{ $ride->id }}</h1>
    <div class="text-muted small">
      <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
      <span class="ms-2">Canal: {{ $channelLabel }}</span>
    </div>
  </div>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="{{ url()->previous() ?: route('partner.reports.rides.index') }}">
      <i class="ti ti-arrow-left me-1"></i> Volver
    </a>
  </div>
</div>

<div class="row g-3">
  {{-- MAPA + RUTA --}}
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Mapa del viaje</h5>
        <div class="card-actions">
          <span class="text-muted small">Origen · Destino · Ruta guardada</span>
        </div>
      </div>
      <div class="card-body">
        <div id="rideMap" style="height:420px;border-radius:12px;overflow:hidden;"></div>

        <div class="mt-3">
          <div class="text-muted small">Origen</div>
          <div>{{ $ride->origin_label ?? '—' }}</div>

          <div class="text-muted small mt-2">Destino</div>
          <div>{{ $ride->dest_label ?? '—' }}</div>

          @if(!empty($ride->notes))
            <div class="text-muted small mt-2">Notas</div>
            <div style="white-space:pre-wrap;">{{ $ride->notes }}</div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- RESUMEN AMIGABLE --}}
  <div class="col-lg-5">
    <div class="row g-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header"><h5 class="card-title mb-0">Resumen</h5></div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-6">
                <div class="text-muted small">Monto</div>
                <div class="fw-semibold">{{ number_format($amount, 2) }} <span class="text-muted">{{ $ride->currency ?? 'MXN' }}</span></div>
              </div>
              <div class="col-6">
                <div class="text-muted small">Pago</div>
                <div class="fw-semibold">{{ $ride->payment_method ?? '—' }}</div>
              </div>

              <div class="col-6">
                <div class="text-muted small">Distancia</div>
                <div class="fw-semibold">{{ $ride->distance_m ? number_format((int)$ride->distance_m/1000, 2) . ' km' : '—' }}</div>
              </div>
              <div class="col-6">
                <div class="text-muted small">Duración</div>
                <div class="fw-semibold">{{ $ride->duration_s ? number_format((int)round($ride->duration_s/60)) . ' min' : '—' }}</div>
              </div>
            </div>

            @if($ride->status === 'canceled')
              <hr class="my-3">
              <div class="text-muted small">Cancelación</div>
              <div class="fw-semibold">{{ $ride->cancel_reason ?? '—' }}</div>
              <div class="text-muted small">Por: {{ $ride->canceled_by ?? '—' }} · {{ $ride->canceled_at ?? '—' }}</div>
            @endif
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card">
          <div class="card-header"><h5 class="card-title mb-0">Pasajero</h5></div>
          <div class="card-body">
            <div class="fw-semibold">{{ $ride->passenger_name ?? '—' }}</div>
            <div class="text-muted">{{ $ride->passenger_phone ?? '—' }}</div>
            <div class="text-muted small mt-2">
              Calificación pasajero→conductor: {{ $pRating ? ($pRating.'/5') : '—' }}
            </div>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card">
          <div class="card-header"><h5 class="card-title mb-0">Conductor y vehículo</h5></div>
          <div class="card-body">
            <div class="fw-semibold">{{ $ride->driver_id ? $ride->driver_name : '—' }}</div>
            <div class="text-muted">{{ $ride->driver_phone ?? '' }}</div>

            <hr class="my-3">

            <div class="fw-semibold">{{ $vehText }}</div>
            <div class="text-muted small">
              {{ $ride->vehicle_brand ? $ride->vehicle_brand : '' }}
              {{ $ride->vehicle_model ? '· '.$ride->vehicle_model : '' }}
              {{ $ride->vehicle_color ? '· '.$ride->vehicle_color : '' }}
              {{ $ride->vehicle_year ? '· '.$ride->vehicle_year : '' }}
            </div>

            <div class="text-muted small mt-2">
              Calificación conductor→pasajero: {{ $dRating ? ($dRating.'/5') : '—' }}
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

{{-- LÍNEA DE TIEMPO --}}
<div class="card mt-3">
  <div class="card-header"><h5 class="card-title mb-0">Línea de tiempo del viaje</h5></div>
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-4">
        <div class="text-muted small">Solicitud</div>
        <div class="fw-semibold">{{ $ride->requested_at ?? $ride->created_at ?? '—' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small">Aceptado</div>
        <div class="fw-semibold">{{ $ride->accepted_at ?? '—' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small">Llegó</div>
        <div class="fw-semibold">{{ $ride->arrived_at ?? '—' }}</div>
      </div>

      <div class="col-md-4">
        <div class="text-muted small">Abordó</div>
        <div class="fw-semibold">{{ $ride->onboard_at ?? '—' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small">Finalizó / Canceló</div>
        <div class="fw-semibold">{{ $finalAt ?? '—' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small">Tiempo total (solicitud→final)</div>
        <div class="fw-semibold">{{ $fmtDiff($ride->requested_at ?? $ride->created_at, $finalAt) }}</div>
      </div>

      <div class="col-md-4">
        <div class="text-muted small">Aceptación (solicitud→aceptado)</div>
        <div class="fw-semibold">{{ $fmtDiff($ride->requested_at ?? $ride->created_at, $ride->accepted_at) }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small">Pickup (aceptado→llegó)</div>
        <div class="fw-semibold">{{ $fmtDiff($ride->accepted_at, $ride->arrived_at) }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small">Viaje (abordó→final)</div>
        <div class="fw-semibold">{{ $fmtDiff($ride->onboard_at, $ride->finished_at) }}</div>
      </div>
    </div>
  </div>
</div>

{{-- OFERTAS (CARDS) --}}
<div class="card mt-3">
  <div class="card-header"><h5 class="card-title mb-0">Ofertas (drivers del partner)</h5></div>
  <div class="card-body">
    @if($offers->isEmpty())
      <div class="text-muted">Sin ofertas registradas para este partner.</div>
    @else
      <div class="row g-3">
        @foreach($offers as $o)
          @php
            $oBadge = match((string)$o->status) {
              'accepted' => 'bg-success-lt text-success',
              'rejected','expired','canceled','released' => 'bg-danger-lt text-danger',
              'queued' => 'bg-warning-lt text-warning',
              default => 'bg-secondary-lt text-secondary',
            };
            $ov = trim(($o->vehicle_economico ?? '').' · '.($o->vehicle_plate ?? ''));
            $ov = $ov && $ov !== '·' ? $ov : ($o->vehicle_id ? ('#'.$o->vehicle_id) : '—');
          @endphp
          <div class="col-lg-6">
            <div class="card card-sm">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="fw-semibold">{{ $o->driver_name }}</div>
                  <span class="badge {{ $oBadge }}">{{ $o->status }}</span>
                </div>
                <div class="text-muted small mt-1">Vehículo: {{ $ov }}</div>

                <div class="row g-2 mt-2">
                  <div class="col-6">
                    <div class="text-muted small">Enviada</div>
                    <div class="fw-semibold">{{ $o->sent_at }}</div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted small">Expira</div>
                    <div class="fw-semibold">{{ $o->expires_at ?? '—' }}</div>
                  </div>

                  <div class="col-6">
                    <div class="text-muted small">Respuesta</div>
                    <div class="fw-semibold">{{ $o->response ?? '—' }}</div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted small">Respondió</div>
                    <div class="fw-semibold">{{ $o->responded_at ?? '—' }}</div>
                  </div>

                  <div class="col-6">
                    <div class="text-muted small">ETA</div>
                    <div class="fw-semibold">{{ $o->eta_seconds !== null ? (int)round($o->eta_seconds/60).' min' : '—' }}</div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted small">Distancia</div>
                    <div class="fw-semibold">{{ $o->distance_m !== null ? number_format((int)$o->distance_m/1000, 2).' km' : '—' }}</div>
                  </div>

                  <div class="col-6">
                    <div class="text-muted small">Oferta</div>
                    <div class="fw-semibold">{{ $o->driver_offer !== null ? number_format((float)$o->driver_offer, 2) : '—' }}</div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted small">Ronda</div>
                    <div class="fw-semibold">{{ (int)$o->round_no }}{{ (int)($o->is_direct ?? 0) ? ' · Directa' : '' }}</div>
                  </div>
                </div>

                @if($o->queued_position !== null)
                  <hr class="my-2">
                  <div class="text-muted small">Cola: Pos {{ $o->queued_position }} · {{ $o->queued_at ?? '—' }}</div>
                @endif

              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>

{{-- BIDS (si aplica) --}}
@if(!empty($bids) && count($bids))
<div class="card mt-3">
  <div class="card-header"><h5 class="card-title mb-0">Negociación (bids)</h5></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-vcenter mb-0">
        <thead><tr><th>Hora</th><th>Rol</th><th>Monto</th><th>Nota</th></tr></thead>
        <tbody>
        @foreach($bids as $b)
          <tr>
            <td class="text-muted small">{{ $b->created_at }}</td>
            <td>{{ $b->role }}</td>
            <td>{{ number_format((float)$b->amount,2) }} <span class="text-muted">MXN</span></td>
            <td class="text-muted">{{ $b->note ?? '—' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mapbox/polyline@1.2.1/src/polyline.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const mapEl = document.getElementById('rideMap');
  if (!mapEl) return;

  const origin = { lat: Number(@json($ride->origin_lat)), lng: Number(@json($ride->origin_lng)) };
  const destLat = @json($ride->dest_lat);
  const destLng = @json($ride->dest_lng);
  const hasDest = destLat !== null && destLng !== null;
  const dest = hasDest ? { lat: Number(destLat), lng: Number(destLng) } : null;

  const map = L.map('rideMap', { zoomControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  // =========================
  // ICONOS (PNG en /public/images)
  // Ajusta los paths a los tuyos reales
  // =========================
  const ICON_SIZE = [34, 46];
  const ICON_ANCHOR = [17, 46];     // punta del pin
  const POPUP_ANCHOR = [0, -38];

  const iconOrigin = L.icon({
    iconUrl: @json(asset('images/origen.png')),
    iconSize: ICON_SIZE,
    iconAnchor: ICON_ANCHOR,
    popupAnchor: POPUP_ANCHOR
  });

  const iconDest = L.icon({
    iconUrl: @json(asset('images/destino.png')),
    iconSize: ICON_SIZE,
    iconAnchor: ICON_ANCHOR,
    popupAnchor: POPUP_ANCHOR
  });

  const iconStop = L.icon({
    iconUrl: @json(asset('images/stopride.png')),
    iconSize: ICON_SIZE,
    iconAnchor: ICON_ANCHOR,
    popupAnchor: POPUP_ANCHOR
  });

  // Parada con número (wrapper: PNG + badge)
  function stopIconNumber(n) {
    const url = @json(asset('images/stopride.png'));
    return L.divIcon({
      className: 'mk-stop-wrap',
      html: `
        <div class="mk-stop">
          <img src="${url}" alt="Parada" />
          <span class="mk-stop-n">${n}</span>
        </div>
      `,
      iconSize: ICON_SIZE,
      iconAnchor: ICON_ANCHOR,
      popupAnchor: POPUP_ANCHOR
    });
  }

  // Estilos para badge (puedes mover esto a tu CSS)
  const style = document.createElement('style');
  style.innerHTML = `
    .mk-stop-wrap { background: transparent; border: 0; }
    .mk-stop { position: relative; width: ${ICON_SIZE[0]}px; height: ${ICON_SIZE[1]}px; }
    .mk-stop img { width: 100%; height: 100%; display:block; }
    .mk-stop-n{
      position:absolute;
      top: 6px;
      left: 50%;
      transform: translateX(-50%);
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.85);
      color: #fff;
      font-size: 12px;
      line-height: 18px;
      text-align:center;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(0,0,0,0.25);
    }
  `;
  document.head.appendChild(style);

  const markers = [];

  // Origen
  const mOrigin = L.marker([origin.lat, origin.lng], { icon: iconOrigin })
    .addTo(map)
    .bindPopup('Origen');
  markers.push(mOrigin);

  // Destino
  if (dest) {
    const mDest = L.marker([dest.lat, dest.lng], { icon: iconDest })
      .addTo(map)
      .bindPopup('Destino');
    markers.push(mDest);
  }

  // Stops (si vienen)
  let stops = [];
  try {
    stops = JSON.parse(@json($ride->stops_json ?? '[]')) || [];
  } catch(e) {}

  if (Array.isArray(stops) && stops.length) {
    stops.forEach((s, idx) => {
      const lat = Number(s.lat ?? s.latitude ?? null);
      const lng = Number(s.lng ?? s.longitude ?? null);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      const mk = L.marker([lat, lng], { icon: stopIconNumber(idx + 1) })
        .addTo(map)
        .bindPopup('Parada ' + (idx + 1));

      markers.push(mk);
    });
  }

  // Ruta (encoded polyline)
  const enc = @json($routePolyline ?? '');
  let routeLine = null;

  // Color suave para la ruta (ajústalo a tu paleta)
  const routeColor = '#2563EB';      // azul elegante
  const routeHalo  = 'rgba(37,99,235,0.18)';

  if (enc && typeof polyline !== 'undefined') {
    try {
      const pts = polyline.decode(enc).map(p => [p[0], p[1]]);
      if (pts.length) {
        // halo para verse "premium"
        L.polyline(pts, { color: routeHalo, weight: 10, opacity: 1, lineCap: 'round' }).addTo(map);

        routeLine = L.polyline(pts, {
          color: routeColor,
          weight: 5,
          opacity: 0.95,
          lineCap: 'round'
        }).addTo(map);

        map.fitBounds(routeLine.getBounds(), { padding: [18, 18] });
        return;
      }
    } catch(e) {}
  }

  // Fallback: encuadrar markers
  if (markers.length > 1) {
    const group = L.featureGroup(markers);
    map.fitBounds(group.getBounds(), { padding: [18, 18] });
  } else {
    map.setView([origin.lat, origin.lng], 15);
  }
});
</script>

@endpush

@endsection
