@extends('layouts.sysadmin')

@section('title','Topups Partner · Transferencias')

@php
  $status = (string)($status ?? request('status',''));

  $badge = function(string $s) {
    return match($s) {
      'pending_review' => 'bg-yellow-lt text-yellow',
      'approved'       => 'bg-blue-lt text-blue',
      'credited'       => 'bg-green-lt text-green',
      'rejected'       => 'bg-red-lt text-red',
      default          => 'bg-secondary-lt',
    };
  };

  $label = function(string $s) {
    return match($s) {
      'pending_review' => 'Pendiente',
      'approved'       => 'Aprobada',
      'credited'       => 'Acreditada',
      'rejected'       => 'Rechazada',
      default          => $s ?: '—',
    };
  };

  $statusOptions = [
    ''              => 'Todas',
    'pending_review'=> 'Pendientes',
    'credited'      => 'Acreditadas',
    'rejected'      => 'Rechazadas',
  ];
@endphp

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">SysAdmin · Finanzas</div>
          <h2 class="page-title">Transferencias de Partner (Topups)</h2>
          <div class="text-muted mt-1">
            Proveedor: <span class="fw-semibold">bank</span> · Mostrando
            <span class="fw-semibold">{{ $items->count() }}</span> de
            <span class="fw-semibold">{{ $items->total() }}</span>
          </div>
        </div>

        <div class="col-auto ms-auto d-print-none">
          <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-select" onchange="this.form.submit()">
              @foreach($statusOptions as $k => $v)
                <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
              @endforeach
            </select>

            @if($status !== '')
              <a href="{{ route('sysadmin.topups.partner_transfer.index') }}" class="btn btn-outline-secondary">
                Limpiar
              </a>
            @endif
          </form>
        </div>
      </div>

      {{-- Flash messages estilo Tabler --}}
      <div class="row mt-3">
        <div class="col">
          @if(session('ok'))
            <div class="alert alert-success" role="alert">
              <div class="d-flex">
                <div>
                  <h4 class="alert-title">Listo</h4>
                  <div class="text-muted">{{ session('ok') }}</div>
                </div>
              </div>
            </div>
          @endif

          @if(session('warning'))
            <div class="alert alert-warning" role="alert">
              <h4 class="alert-title">Atención</h4>
              <div class="text-muted">{{ session('warning') }}</div>
            </div>
          @endif

          @if(session('error'))
            <div class="alert alert-danger" role="alert">
              <h4 class="alert-title">Error</h4>
              <div class="text-muted">{{ session('error') }}</div>
            </div>
          @endif
        </div>
      </div>

    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">

      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Solicitudes</h3>

          <div class="card-actions">
            {{-- Chips de estado (opcional pero se ve Tabler) --}}
            <div class="btn-list">
              <a class="btn btn-sm {{ $status===''?'btn-primary':'btn-outline-primary' }}"
                 href="{{ route('sysadmin.topups.partner_transfer.index') }}">
                Todas
              </a>
              <a class="btn btn-sm {{ $status==='pending_review'?'btn-warning':'btn-outline-warning' }}"
                 href="{{ route('sysadmin.topups.partner_transfer.index', ['status'=>'pending_review']) }}">
                Pendientes
              </a>
              <a class="btn btn-sm {{ $status==='credited'?'btn-success':'btn-outline-success' }}"
                 href="{{ route('sysadmin.topups.partner_transfer.index', ['status'=>'credited']) }}">
                Acreditadas
              </a>
              <a class="btn btn-sm {{ $status==='rejected'?'btn-danger':'btn-outline-danger' }}"
                 href="{{ route('sysadmin.topups.partner_transfer.index', ['status'=>'rejected']) }}">
                Rechazadas
              </a>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table card-table table-vcenter">
            <thead>
              <tr>
                <th>ID</th>
                <th>Tenant</th>
                <th>Partner</th>
                <th>Estatus</th>
                <th>Referencia</th>
                <th>Fecha</th>
                <th class="text-end">Monto</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              @forelse($items as $t)
                <tr>
                  <td class="text-muted">#{{ $t->id }}</td>

                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span class="avatar avatar-sm">{{ $t->tenant_id }}</span>
                      <div class="lh-sm">
                        <div class="fw-semibold">Tenant {{ $t->tenant_id }}</div>
                        <div class="text-muted small">provider: {{ $t->provider }}</div>
                      </div>
                    </div>
                  </td>

                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span class="avatar avatar-sm bg-azure-lt">{{ $t->partner_id }}</span>
                      <div class="lh-sm">
                        <div class="fw-semibold">Partner {{ $t->partner_id }}</div>
                        <div class="text-muted small">{{ $t->payer_name ?? '—' }}</div>
                      </div>
                    </div>
                  </td>

                  <td>
                    <span class="badge {{ $badge((string)$t->status) }}">
                      {{ $label((string)$t->status) }}
                    </span>
                    @if(!empty($t->review_status))
                      <div class="text-muted small">rev: {{ $t->review_status }}</div>
                    @endif
                  </td>

                  <td class="small">
                    <div class="text-truncate" style="max-width:260px;">
                      {{ $t->bank_ref ?? $t->external_reference ?? '—' }}
                    </div>
                    @if(!empty($t->proof_path))
                      <div class="text-muted small">Con comprobante</div>
                    @else
                      <div class="text-muted small">Sin comprobante</div>
                    @endif
                  </td>

                  <td class="small text-muted">
                    {{ optional($t->created_at)->format('Y-m-d H:i') ?? $t->created_at }}
                  </td>

                  <td class="text-end fw-semibold">
                    ${{ number_format((float)$t->amount,2) }} {{ $t->currency ?? 'MXN' }}
                  </td>

                  <td class="text-end">
                    <a class="btn btn-primary btn-sm"
                       href="{{ route('sysadmin.topups.partner_transfer.show', $t) }}">
                      Revisar
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="text-center text-muted p-4">
                    Sin registros para este filtro.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="card-footer d-flex align-items-center">
          <div class="text-muted">
            Página <span class="fw-semibold">{{ $items->currentPage() }}</span> de
            <span class="fw-semibold">{{ $items->lastPage() }}</span>
          </div>
          <div class="ms-auto">
            {{ $items->withQueryString()->links() }}
          </div>
        </div>

      </div>

    </div>
  </div>
</div>
@endsection
