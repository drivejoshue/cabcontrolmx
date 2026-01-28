@extends('layouts.sysadmin')

@section('title', 'Partner · Billing · '.$partner->name.' · Tenant #'.$tenant->id)

@section('content')


<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Partner: {{ $partner->name ?? ('#'.$partner->id) }}</h3>
      <div class="text-muted small">
        Tenant: {{ $tenant->name }} ({{ $tenant->id }}) · Partner ID: {{ $partner->id }}
      </div>
    </div>

    <a class="btn btn-outline-secondary btn-sm"
       href="{{ route('sysadmin.tenants.billing.show', $tenant) }}?tab=partners">
      Volver a Partners
    </a>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header"><strong>Wallet</strong></div>
        <div class="card-body">
          @php
            $bal = (float)($wallet->balance ?? 0);
            $cur = $wallet->currency ?? 'MXN';
          @endphp
          <div class="display-6">${{ number_format($bal,2) }} <span class="fs-6">{{ $cur }}</span></div>
          <div class="text-muted small">updated: {{ $wallet->updated_at ?? '—' }}</div>

          <hr>

          {{-- ✅ Topup manual (SysAdmin) --}}
<div class="mt-3">
  <div class="small text-muted mb-2">
    <strong>Topup manual</strong> · crea un registro y acredita saldo inmediatamente
  </div>

  <form method="POST"
        action="{{ route('partners.topups.manual.store', $partner) }}"
        onsubmit="return confirm('¿Crear y acreditar este topup manual?');">
    @csrf

    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label small">Monto (MXN)</label>
        <input type="number" step="0.01" min="1" name="amount"
               class="form-control form-control-sm"
               value="{{ old('amount') }}" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small">Folio/Ref (opcional)</label>
        <input type="text" name="bank_ref"
               class="form-control form-control-sm"
               value="{{ old('bank_ref') }}"
               placeholder="Ej: AJUSTE-ENE-01">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label small">Notas (opcional)</label>
        <input type="text" name="notes"
               class="form-control form-control-sm"
               value="{{ old('notes') }}"
               placeholder="Motivo / auditoría">
      </div>

      <input type="hidden" name="currency" value="MXN">

      <div class="col-12 d-flex gap-2 mt-2">
        <button class="btn btn-primary btn-sm">Abonar saldo</button>
        <span class="text-muted small align-self-center">
          Se registrará como <code>provider=manual</code> y quedará <code>credited</code>.
        </span>
      </div>
    </div>
  </form>
</div>

          
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card">
        <div class="card-header"><strong>Últimos movimientos</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped tight mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Tipo</th>
                  <th class="text-end">Monto</th>
                  <th>Ref</th>
                  <th>Notas</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
              @forelse($movements as $m)
                <tr>
                  <td>{{ $m->id }}</td>
                  <td>{{ $m->type }}</td>
                  <td class="text-end">{{ number_format((float)$m->amount,2) }} {{ $m->currency }}</td>
                  <td>{{ $m->external_ref ?? '—' }}</td>
                  <td>{{ $m->notes ?? '—' }}</td>
                  <td>{{ $m->created_at }}</td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-muted">Sin movimientos.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>

        <div class="card-footer">
          {{ $movements->links() }}
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header"><strong>Topups recientes</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped tight mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Status</th>
                  <th>Provider</th>
                  <th class="text-end">Monto</th>
                  <th>Ref</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
              @forelse($topups as $t)
                <tr>
                  <td>{{ $t->id }}</td>
                  <td>{{ $t->status }}</td>
                  <td>{{ $t->provider }}</td>
                  <td class="text-end">{{ number_format((float)$t->amount,2) }} {{ $t->currency }}</td>
                  <td>{{ $t->bank_ref ?? $t->external_reference ?? '—' }}</td>
                  <td>{{ $t->created_at }}</td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-muted">Sin topups.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>

</div>
@endsection
