@extends('layouts.partner')

@section('title', 'Dashboard')

@section('content')
@php
    /** @var \App\Models\Partner $partner */
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-0">Partner: {{ $partner->name }}</h1>
        <div class="text-muted small">Tenant #{{ $partner->tenant_id }}</div>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Vehículos</div>
               
               <div class="h2 mb-0" id="kpi-vehicles-total">{{ $stats['vehicles_total'] }}</div>
<span class="badge bg-success-lt text-success" id="kpi-vehicles-active">Activos: {{ $stats['vehicles_active'] }}</span>

            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Conductores</div>
              
               <div class="h2 mb-0" id="kpi-drivers-total">{{ $stats['drivers_total'] }}</div>
<span class="badge bg-info-lt text-info" id="kpi-drivers-active">Operando: {{ $stats['drivers_active'] }}</span>

            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="text-muted small">Wallet</div>
                <div class="d-flex align-items-baseline gap-2">
                   <div class="h2 mb-0" id="kpi-wallet-balance">{{ number_format((float)$stats['wallet_balance'], 2) }}</div>
<div class="text-muted" id="kpi-wallet-currency">{{ $stats['wallet_currency'] }}</div>

                </div>
                <div class="text-muted small mt-2">
                    Recargas recientes (últimas 10)
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h5 class="card-title mb-0">Recargas recientes</h5>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Proveedor</th>
                    <th>Método</th>
                    <th>Monto</th>
                    <th>Status</th>
                    <th>Creado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentTopups as $t)
                    <tr>
                        <td>{{ $t->id }}</td>
                        <td>{{ $t->provider }}</td>
                        <td>{{ $t->method ?? '—' }}</td>
                        <td>{{ number_format((float)$t->amount, 2) }} {{ $t->currency }}</td>
                        <td>
                            <span class="badge bg-secondary-lt text-secondary">{{ $t->status }}</span>
                        </td>
                        <td class="text-muted small">{{ $t->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Sin recargas todavía.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
  const url = @json(route('partner.api.dashboard'));
  const range = 'today'; // luego lo hacemos selector

  function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  async function tick() {
    try {
      const res = await fetch(url + '?range=' + encodeURIComponent(range), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      const j = await res.json();
      if (!j || !j.ok) return;

      const k = j.data.kpi;

      setText('kpi-vehicles-total', k.vehicles.total);
      setText('kpi-vehicles-active', 'Activos: ' + k.vehicles.active);

      setText('kpi-drivers-total', k.drivers.total);
      // “Operando” aquí lo puedes definir como online o active; por ahora online
      setText('kpi-drivers-active', 'Operando: ' + k.drivers.online);

      setText('kpi-wallet-balance', (k.wallet.balance ?? 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}));
      setText('kpi-wallet-currency', k.wallet.currency ?? 'MXN');

      // Si luego agregamos cards de rides/issues, ya queda el pipeline.
    } catch (e) {
      // Silencioso; el siguiente tick reintenta.
    }
  }

  tick();
  setInterval(tick, 12000);
})();
</script>
@endpush
