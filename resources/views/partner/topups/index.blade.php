@extends('layouts.partner')

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Finanzas</div>
        <h2 class="page-title">Recargas</h2>
        <div class="text-muted">Historial de solicitudes de recarga</div>
      </div>
      <div class="col-auto ms-auto d-flex gap-2">
        <a class="btn btn-primary" href="{{ route('partner.topups.create') }}">
          <i class="ti ti-plus me-1"></i> Solicitar recarga
        </a>
        <a class="btn btn-outline-secondary" href="{{ route('partner.wallet.index') }}">
          <i class="ti ti-wallet me-1"></i> Wallet
        </a>
      </div>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Solicitudes</h3>
      <div class="card-actions text-muted small">
        Total: {{ method_exists($items, 'total') ? $items->total() : count($items) }}
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-vcenter card-table table-striped">
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha</th>
            <th class="text-end">Monto</th>
            <th>Método</th>
            <th>Estatus</th>
            <th>Ref</th>
            <th class="text-end">Acción</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $t)
            @php
              $st = strtolower((string)($t->status ?? ''));
              $badge = 'secondary';
              if (in_array($st, ['pending_review','pending'])) $badge = 'warning';
              if ($st === 'approved') $badge = 'info';
              if ($st === 'credited') $badge = 'success';
              if ($st === 'rejected') $badge = 'danger';
            @endphp
            <tr>
              <td class="text-muted">#{{ $t->id }}</td>
              <td>
                <div class="fw-semibold">{{ optional($t->created_at)->format('Y-m-d') }}</div>
                <div class="text-muted small">{{ optional($t->created_at)->format('H:i') }}</div>
              </td>
              <td class="text-end">
                <div class="fw-bold">${{ number_format($t->amount, 2) }}</div>
                <div class="text-muted small">{{ $t->currency ?? 'MXN' }}</div>
              </td>
              <td>
                <span class="badge bg-azure-lt">
                  {{ $t->method ?? '-' }}
                </span>
              </td>
              <td>
                <span class="badge bg-{{ $badge }}">{{ $t->status }}</span>
                @if(!empty($t->review_status))
                  <div class="text-muted small">rev: {{ $t->review_status }}</div>
                @endif
              </td>
              <td class="text-muted">
                {{ $t->bank_ref ?: ($t->external_reference ?: '—') }}
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('partner.topups.show', $t->id) }}">
                  Ver
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-muted p-3">Aún no hay recargas.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($items, 'links'))
      <div class="card-footer">
        {{ $items->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
