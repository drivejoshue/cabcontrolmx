@extends('layouts.admin')

@section('title','Wallet')

@push('styles')
<style>
  .stat-pill { border-radius: 999px; padding: .25rem .7rem; font-size: .75rem; }
  .metric { font-size: 1.55rem; font-weight: 800; letter-spacing: -.02em; }
  .metric-sub { font-size: .85rem; color: rgba(0,0,0,.55); }
  [data-theme="dark"] .metric-sub { color: rgba(255,255,255,.65); }
  .soft-card { border: 1px solid rgba(0,0,0,.06); }
  [data-theme="dark"] .soft-card { border-color: rgba(255,255,255,.10); }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>
@endpush

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Wallet</h3>
      <div class="text-muted small">
        Central: {{ auth()->user()->tenant?->name ?? '—' }} · Tenant ID: {{ auth()->user()->tenant_id ?? '—' }}
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('admin.billing.plan') }}" class="btn btn-outline-secondary">Mi plan</a>
      <a href="{{ route('admin.wallet.topup.create') }}" class="btn btn-primary">Recargar saldo</a>
    </div>
  </div>

  <div class="row g-3">

    {{-- Balance --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100 soft-card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <strong>Saldo disponible</strong>
          <span class="badge bg-info stat-pill">Prepaid</span>
        </div>
        <div class="card-body">
          <div class="metric">
            ${{ number_format((float)($wallet->balance ?? 0), 2) }} <span class="fs-6">MXN</span>
          </div>
          <div class="metric-sub">
            Última recarga:
            {{ !empty($wallet->last_topup_at) ? \Carbon\Carbon::parse($wallet->last_topup_at)->toDateTimeString() : '—' }}
          </div>

          <hr>

          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="small text-muted">Estimación próximo cargo</div>
              <div class="fw-semibold">
                @if($nextCharge !== null)
                  ${{ number_format((float)$nextCharge, 2) }} MXN
                @else
                  —
                @endif
              </div>
            </div>
            <div class="text-end">
              <div class="small text-muted">Acción</div>
              <a class="btn btn-sm btn-primary" href="{{ route('admin.wallet.topup.create') }}">Recargar</a>
            </div>
          </div>

          @if(session('ok'))
            <div class="alert alert-success mt-3 mb-0">{{ session('ok') }}</div>
          @endif
          @if(session('success'))
            <div class="alert alert-success mt-3 mb-0">{{ session('success') }}</div>
          @endif
          @if(session('warning'))
            <div class="alert alert-warning mt-3 mb-0">{{ session('warning') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger mt-3 mb-0">{{ session('error') }}</div>
          @endif
        </div>
      </div>
    </div>

    {{-- Atajos --}}
    <div class="col-12 col-lg-8">
      <div class="card h-100 soft-card">
        <div class="card-header d-flex align-items-center justify-content-between">
          <strong>Movimientos recientes</strong>
          <a href="{{ route('admin.wallet.topup.create') }}" class="btn btn-outline-primary btn-sm">Nueva recarga</a>
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead>
              <tr>
                <th>#</th>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Ref</th>
                <th>Notas</th>
                <th class="text-end">Monto</th>
              </tr>
              </thead>
              <tbody>
              @forelse($movements as $m)
                @php
                  $type = strtolower($m->type ?? '');
                  $badge = match($type) {
                    'topup','credit' => 'success',
                    'debit','charge' => 'danger',
                    default => 'secondary',
                  };
                  $amt = (float)($m->amount ?? 0);
                @endphp
                <tr>
                  <td class="text-muted">#{{ $m->id }}</td>
                  <td>{{ \Carbon\Carbon::parse($m->created_at)->toDateTimeString() }}</td>
                  <td><span class="badge bg-{{ $badge }} text-uppercase">{{ $m->type ?? '—' }}</span></td>
                  <td class="mono small text-muted">
                    {{ $m->external_ref ?? ($m->ref_type ?? '') }}{{ !empty($m->ref_id) ? ('#'.$m->ref_id) : '' }}
                  </td>
                  <td class="small">{{ $m->notes ?? '—' }}</td>
                  <td class="text-end fw-semibold">
                    @if($amt >= 0)
                      <span class="text-success">+${{ number_format($amt,2) }}</span>
                    @else
                      <span class="text-danger">-${{ number_format(abs($amt),2) }}</span>
                    @endif
                    <span class="text-muted small">MXN</span>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center py-3 text-muted">
                    Aún no hay movimientos.
                  </td>
                </tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="card-footer d-flex justify-content-between">
          <div class="text-muted small">
            Mostrando últimos {{ is_countable($movements) ? count($movements) : 0 }} movimientos.
          </div>
          <a href="{{ route('admin.billing.plan') }}" class="btn btn-outline-secondary btn-sm">Volver a Mi plan</a>
        </div>

      </div>
    </div>
  </div>

</div>
@endsection
