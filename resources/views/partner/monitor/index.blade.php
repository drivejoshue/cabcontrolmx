@extends('layouts.partner')

@push('head')
  <meta name="tenant-id" content="{{ (int)$tenant->id }}">
  <meta name="partner-id" content="{{ (int)$partner->id }}">

  <script>
  window.ccTenant = @json($ccTenant);

  window.__PARTNER_CTX__ = {!! json_encode([
    'tenant'  => $ccTenant,
    'partner' => [
      'id' => (int) $partner->id,
      'tenant_id' => (int) $tenant->id,
    ],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!};
</script>

@endpush

@section('content')
  <div class="pm-wrap">
    <div id="pmMap" class="pm-map"></div>

    <div class="pm-dock">
      <div class="pm-dock-head">
        <div class="pm-title">Monitoreo</div>
        <div class="pm-actions">
          <button class="btn btn-sm btn-outline-primary" id="pmBtnCenter">Centrar</button>
          <button class="btn btn-sm btn-outline-primary" id="pmBtnFollow">Seguir</button>
        </div>
      </div>

      <div class="pm-section">
        <div class="pm-section-title">Conductores <span id="pmDriversBadge" class="badge bg-secondary">0</span></div>
        <div id="pmDriversList"></div>
      </div>

      <div class="pm-section">
        <div class="pm-section-title">Viajes activos <span id="pmRidesBadge" class="badge bg-secondary">0</span></div>
        <div id="pmRidesList"></div>
      </div>
    </div>
  </div>

  @vite('resources/js/pages/partner_monitor.js')
@endsection
