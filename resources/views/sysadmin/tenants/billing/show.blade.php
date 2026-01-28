@extends('layouts.sysadmin')

@section('title','Billing · Tenant #'.$tenant->id.' · '.$tenant->name)

@push('styles')
<style>
  .stat-pill { border-radius: 999px; padding:.25rem .7rem; font-size:.75rem; }
  .tabular { font-variant-numeric: tabular-nums; }
  .mini { font-size:.85rem; }
  .tight td, .tight th { padding: .45rem .6rem; vertical-align: middle; }
  details > summary { cursor:pointer; }
  .btn-xs { padding: .15rem .45rem; font-size: .78rem; }
</style>
@endpush

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">
        Facturación del tenant: {{ $tenant->name }}

        @php
          $bm = $profile->billing_model ?? 'per_vehicle';
          $st = strtolower($profile->status ?? 'trial');
          $badgeSt = match($st) {
            'active'   => 'success',
            'trial'    => 'warning',
            'paused'   => 'secondary',
            'canceled' => 'danger',
            default    => 'secondary',
          };
        @endphp

        <span class="badge bg-{{ $badgeSt }} stat-pill align-middle">{{ strtoupper($st) }}</span>

        @if($bm === 'commission')
          <span class="badge bg-info stat-pill align-middle">Modelo: comisión</span>
        @else
          <span class="badge bg-primary stat-pill align-middle">Modelo: por vehículo</span>
        @endif
      </h3>

      <div class="text-muted small">
        Tenant ID: {{ $tenant->id }}
        · Ciudad: {{ $tenant->city ?? '—' }}
        · Dominio: {{ $tenant->domain ?? '—' }}
        · TZ: {{ $tenant->timezone ?? config('app.timezone') }}
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-outline-secondary btn-sm">
        Volver a lista de tenants
      </a>

      <form method="POST" action="{{ route('sysadmin.tenants.billing.runMonthly', $tenant) }}?tab={{ $tab }}"
            onsubmit="return confirm('¿Correr el ciclo mensual de facturación para este tenant?');">
        @csrf
        <button class="btn btn-primary btn-sm">Correr ciclo mensual</button>
      </form>
    </div>
  </div>

  {{-- Flash --}}
  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif
@php
  $allowedTabs = ['billing','wallet','invoices','users','vehicles','documents','partners'];


  // Normaliza tab: si no viene o es inválido, cae a billing
  $tabReq = request('tab', $tab ?? 'billing');
  $tab = in_array($tabReq, $allowedTabs, true) ? $tabReq : 'billing';

  // Defensivos para evitar "Undefined variable"
  $wallet = $wallet ?? null;
  $walletMovements = $walletMovements ?? collect();
  $invoices = $invoices ?? collect();
  $users = $users ?? collect();
  $vehicles = $vehicles ?? collect();
  $docs = $docs ?? collect();   // ✅ IMPORTANTE

  // filtros vehiculos
  $vq = $vq ?? request('vq', '');
  $vactive = $vactive ?? request('vactive', '');
@endphp


  {{-- Tabs --}}
  @php
    $is = fn($t) => ($tab ?? 'billing') === $t ? 'active' : '';
  @endphp


<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link {{ $is('billing') }}" href="{{ route('sysadmin.tenants.billing.show',$tenant) }}?tab=billing">Billing</a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $is('wallet') }}" href="{{ route('sysadmin.tenants.billing.show',$tenant) }}?tab=wallet">Wallet</a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $is('invoices') }}" href="{{ route('sysadmin.tenants.billing.show',$tenant) }}?tab=invoices">Facturas</a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $is('users') }}" href="{{ route('sysadmin.tenants.billing.show',$tenant) }}?tab=users">Usuarios</a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $is('vehicles') }}" href="{{ route('sysadmin.tenants.billing.show',$tenant) }}?tab=vehicles">Vehículos</a>
  </li>



 <li class="nav-item">
  <a class="nav-link {{ $is('documents') }}"
     href="{{ route('sysadmin.tenants.billing.show',$tenant) }}?tab=documents">
    Documentos
  </a>
</li>

  <li class="nav-item">
  <a class="nav-link {{ $is('partners') }}"
     href="{{ route('sysadmin.tenants.billing.show',$tenant) }}?tab=partners">
    Partners
  </a>
</li>

</ul>

@if(($tab ?? 'billing') === 'documents')
  @includeIf('sysadmin.tenants.tenant_documents.documents', [
    'tenant' => $tenant,
    'docs'   => $docs ?? collect()
  ])
@endif


  {{-- ===========================
       TAB: BILLING
  ============================ --}}
  @if($tab === 'billing')
  <div class="row g-3">

    {{-- Resumen --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header"><strong>Resumen de facturación</strong></div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-5">Plan</dt><dd class="col-7">{{ $profile->plan_code ?? '—' }}</dd>

            <dt class="col-5">Modelo</dt>
            <dd class="col-7">{{ ($profile->billing_model ?? 'per_vehicle') === 'commission' ? 'Comisión por viaje' : 'Cobro por vehículo' }}</dd>

            <dt class="col-5">Estado</dt><dd class="col-7">{{ $profile->status ?? 'trial' }}</dd>

            <dt class="col-5">Día de corte</dt><dd class="col-7">{{ $profile->invoice_day ?? 1 }}</dd>

            <dt class="col-5">Trial hasta</dt><dd class="col-7">{{ optional($profile->trial_ends_at)->toDateString() ?? '—' }}</dd>

            <dt class="col-5">Vehículos en trial</dt><dd class="col-7">{{ $profile->trial_vehicles ?? 0 }}</dd>

            <dt class="col-5">Última factura</dt>
            <dd class="col-7">
              @if($lastInvoice)
                <div class="tabular">
                  {{ $lastInvoice->issue_date?->toDateString() }} · {{ number_format($lastInvoice->total,2) }} {{ $lastInvoice->currency }}
                </div>
                <a href="{{ route('sysadmin.invoices.show', $lastInvoice) }}" class="small">Ver factura</a>
              @else
                <span class="text-muted">Sin facturas todavía</span>
              @endif
            </dd>

            <dt class="col-5">Próx. corte</dt><dd class="col-7">{{ optional($profile->next_invoice_date)->toDateString() ?? '—' }}</dd>

            <dt class="col-5">Notas</dt><dd class="col-7">{{ $profile->notes ?: '—' }}</dd>
          </dl>

          <hr class="my-3">

          {{-- Acciones de estado (SysAdmin Console) --}}
          <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('sysadmin.tenants.billing.actions.recheck',$tenant) }}?tab=billing">
              @csrf
              <button class="btn btn-outline-secondary btn-sm">Recheck</button>
            </form>

            <form method="POST" action="{{ route('sysadmin.tenants.billing.actions.pause',$tenant) }}?tab=billing"
                  onsubmit="return confirm('¿Pausar tenant? Esto bloqueará acceso.');">
              @csrf
              <input type="hidden" name="close_open_shifts" value="1">
              <input type="hidden" name="reason" value="manual_pause_sysadmin">
              <button class="btn btn-warning btn-sm">Pausar</button>
            </form>

            <form method="POST" action="{{ route('sysadmin.tenants.billing.actions.activate',$tenant) }}?tab=billing"
                  onsubmit="return confirm('¿Activar tenant?');">
              @csrf
              <button class="btn btn-success btn-sm">Activar</button>
            </form>

            <form method="POST" action="{{ route('sysadmin.tenants.billing.actions.cancel',$tenant) }}?tab=billing"
                  onsubmit="return confirm('¿Cancelar tenant? Esto es destructivo a nivel negocio.');">
              @csrf
              <input type="hidden" name="close_open_shifts" value="1">
              <input type="hidden" name="reason" value="manual_cancel_sysadmin">
              <button class="btn btn-danger btn-sm">Cancelar</button>
            </form>

            <form method="POST" action="{{ route('sysadmin.tenants.billing.actions.close_open_shifts',$tenant) }}?tab=billing"
                  onsubmit="return confirm('¿Cerrar turnos abiertos?');">
              @csrf
              <input type="hidden" name="note" value="Cerrado por sysadmin desde consola.">
              <button class="btn btn-outline-danger btn-sm">Cerrar turnos</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    {{-- Vehículos / límites --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header"><strong>Vehículos y límites</strong></div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-6">Vehículos activos</dt><dd class="col-6">{{ $activeVehicles }}</dd>
            <dt class="col-6">Vehículos totales</dt><dd class="col-6">{{ $totalVehicles }}</dd>

            @if(($profile->billing_model ?? 'per_vehicle') === 'per_vehicle')
              <dt class="col-6">Incluidos en plan</dt><dd class="col-6">{{ $profile->included_vehicles ?? 0 }}</dd>
              <dt class="col-6">Máx. vehículos</dt><dd class="col-6">{{ $profile->max_vehicles ?? 'Ilimitado' }}</dd>
              <dt class="col-6">Base mensual</dt><dd class="col-6">${{ number_format($profile->base_monthly_fee ?? 0,2) }} MXN</dd>
              <dt class="col-6">Precio extra</dt><dd class="col-6">${{ number_format($profile->price_per_vehicle ?? 0,2) }} MXN</dd>
            @else
              <dt class="col-6">Comisión %</dt><dd class="col-6">{{ $profile->commission_percent !== null ? $profile->commission_percent.' %' : '—' }}</dd>
              <dt class="col-6">Mínimo mensual</dt><dd class="col-6">${{ number_format($profile->commission_min_fee ?? 0,2) }} MXN</dd>
            @endif
          </dl>

          <hr>

          @if($canRegisterNewVehicle)
            <div class="alert alert-success mb-0 small">Puede registrar nuevos vehículos.</div>
          @else
            <div class="alert alert-warning mb-0 small">
              No puede registrar vehículos.<br><span class="d-block mt-1">{{ $canRegisterReason }}</span>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Editar perfil --}}
    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header"><strong>Editar perfil de facturación</strong></div>

        <form method="POST" action="{{ route('sysadmin.tenants.billing.update', $tenant) }}?tab=billing">
          @csrf
          <div class="card-body small">
            {{-- (Tu formulario actual, igual) --}}
            <div class="mb-2">
              <label class="form-label">Código de plan</label>
              <input type="text" name="plan_code" class="form-control form-control-sm"
                     value="{{ old('plan_code', $profile->plan_code ?? 'basic-per-vehicle') }}">
            </div>

            <div class="mb-2">
              <label class="form-label">Modelo de cobro</label>
              @php $bmVal = old('billing_model', $profile->billing_model ?? 'per_vehicle'); @endphp
              <select name="billing_model" class="form-select form-select-sm">
                <option value="per_vehicle" @selected($bmVal==='per_vehicle')>Por vehículo</option>
                <option value="commission" @selected($bmVal==='commission')>Comisión por viaje</option>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label">Estado del perfil</label>
              @php $stVal = old('status', $profile->status ?? 'trial'); @endphp
              <select name="status" class="form-select form-select-sm">
                @foreach(['trial','active','paused','canceled'] as $opt)
                  <option value="{{ $opt }}" @selected($stVal === $opt)>{{ ucfirst($opt) }}</option>
                @endforeach
              </select>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label">Trial hasta</label>
                <input type="date" name="trial_ends_at" class="form-control form-control-sm"
                       value="{{ old('trial_ends_at', optional($profile->trial_ends_at)->toDateString()) }}">
              </div>
              <div class="col-6">
                <label class="form-label">Vehículos en trial</label>
                <input type="number" name="trial_vehicles" min="0" class="form-control form-control-sm"
                       value="{{ old('trial_vehicles', $profile->trial_vehicles ?? 5) }}">
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label">Día de corte (1–28)</label>
              <input type="number" name="invoice_day" min="1" max="28" class="form-control form-control-sm"
                     value="{{ old('invoice_day', $profile->invoice_day ?? 1) }}">
            </div>

            <div class="border rounded p-2 mb-2">
              <div class="form-text mb-1">Si el modelo es <strong>per_vehicle</strong>:</div>
              <div class="row g-2 mb-1">
                <div class="col-6">
                  <label class="form-label">Base mensual (MXN)</label>
                  <input type="number" step="0.01" min="0" name="base_monthly_fee" class="form-control form-control-sm"
                         value="{{ old('base_monthly_fee', $profile->base_monthly_fee ?? 0) }}">
                </div>
                <div class="col-6">
                  <label class="form-label">Incluye vehículos</label>
                  <input type="number" min="0" name="included_vehicles" class="form-control form-control-sm"
                         value="{{ old('included_vehicles', $profile->included_vehicles ?? 0) }}">
                </div>
              </div>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">Precio x vehículo extra</label>
                  <input type="number" step="0.01" min="0" name="price_per_vehicle" class="form-control form-control-sm"
                         value="{{ old('price_per_vehicle', $profile->price_per_vehicle ?? 0) }}">
                </div>
                <div class="col-6">
                  <label class="form-label">Máx. vehículos</label>
                  <input type="number" min="0" name="max_vehicles" class="form-control form-control-sm"
                         value="{{ old('max_vehicles', $profile->max_vehicles) }}">
                </div>
              </div>
            </div>

            <div class="border rounded p-2 mb-2">
              <div class="form-text mb-1">Si el modelo es <strong>commission</strong>:</div>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label">% comisión</label>
                  <input type="number" step="0.1" min="0" max="100" name="commission_percent"
                         class="form-control form-control-sm"
                         value="{{ old('commission_percent', $profile->commission_percent) }}">
                </div>
                <div class="col-6">
                  <label class="form-label">Mínimo mensual (MXN)</label>
                  <input type="number" step="0.01" min="0" name="commission_min_fee"
                         class="form-control form-control-sm"
                         value="{{ old('commission_min_fee', $profile->commission_min_fee ?? 0) }}">
                </div>
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label">Notas internas</label>
              <textarea name="notes" rows="2" class="form-control form-control-sm"
                        placeholder="Comentarios internos">{{ old('notes', $profile->notes) }}</textarea>
            </div>
          </div>

          <div class="card-footer d-flex justify-content-end">
            <button class="btn btn-primary btn-sm" type="submit">Guardar cambios</button>
          </div>
        </form>
      </div>
    </div>

  </div>
  @endif


  {{-- ===========================
       TAB: WALLET
  ============================ --}}
  @if($tab === 'wallet')
  <div class="row g-3">

    <div class="col-12 col-lg-4">
      <div class="card h-100">
        <div class="card-header"><strong>Saldo actual</strong></div>
        <div class="card-body">
          @php
            $bal = $wallet ? (float)$wallet->balance : 0;
            $cur = $wallet->currency ?? 'MXN';
          @endphp

          <div class="display-6 tabular mb-2">${{ number_format($bal,2) }} <span class="fs-6">{{ $cur }}</span></div>
          <div class="text-muted small">last_topup_at: {{ $wallet->last_topup_at ?? '—' }}</div>

          <hr>

          <div class="alert alert-info small mb-0">
            Aquí acreditas pagos por <strong>transferencia</strong> o ajustes manuales.
            Esto genera <strong>movimientos</strong> auditables.
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card">
        <div class="card-header"><strong>Acciones de wallet</strong></div>
        <div class="card-body">

          <div class="row g-3">
            {{-- CREDIT --}}
            <div class="col-12 col-lg-4">
              <div class="border rounded p-2">
                <div class="fw-semibold mb-2">Acreditar (+)</div>
                <form method="POST" action="{{ route('sysadmin.tenants.billing.wallet.credit',$tenant) }}?tab=wallet"
                      onsubmit="return confirm('¿Acreditar saldo a este tenant?');">
                  @csrf
                  <div class="mb-2">
                    <label class="form-label mini">Monto</label>
                    <input name="amount" type="number" step="0.01" min="1" class="form-control form-control-sm" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label mini">Folio transferencia</label>
                    <input name="external_ref" class="form-control form-control-sm" placeholder="STP/folio/banco">
                  </div>
                  <div class="mb-2">
                    <label class="form-label mini">Notas</label>
                    <input name="notes" class="form-control form-control-sm" placeholder="Pago por transferencia">
                  </div>
                  <button class="btn btn-success btn-sm w-100">Acreditar</button>
                </form>
              </div>
            </div>

            {{-- DEBIT --}}
            <div class="col-12 col-lg-4">
              <div class="border rounded p-2">
                <div class="fw-semibold mb-2">Cargo (-)</div>
                <form method="POST" action="{{ route('sysadmin.tenants.billing.wallet.debit',$tenant) }}?tab=wallet"
                      onsubmit="return confirm('¿Aplicar cargo?');">
                  @csrf
                  <div class="mb-2">
                    <label class="form-label mini">Monto</label>
                    <input name="amount" type="number" step="0.01" min="1" class="form-control form-control-sm" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label mini">Referencia</label>
                    <input name="external_ref" class="form-control form-control-sm" placeholder="Motivo/folio">
                  </div>
                  <div class="mb-2">
                    <label class="form-label mini">Notas</label>
                    <input name="notes" class="form-control form-control-sm" placeholder="Cargo manual">
                  </div>
                  <button class="btn btn-danger btn-sm w-100">Aplicar cargo</button>
                </form>
              </div>
            </div>

            {{-- ADJUST --}}
            <div class="col-12 col-lg-4">
              <div class="border rounded p-2">
                <div class="fw-semibold mb-2">Ajuste (+/-)</div>
                <form method="POST" action="{{ route('sysadmin.tenants.billing.wallet.adjust',$tenant) }}?tab=wallet"
                      onsubmit="return confirm('¿Aplicar ajuste?');">
                  @csrf
                  <div class="mb-2">
                    <label class="form-label mini">Delta (puede ser negativo)</label>
                    <input name="delta" type="number" step="0.01" class="form-control form-control-sm" required>
                  </div>
                  <div class="mb-2">
                    <label class="form-label mini">Referencia</label>
                    <input name="external_ref" class="form-control form-control-sm" placeholder="Ticket interno">
                  </div>
                  <div class="mb-2">
                    <label class="form-label mini">Notas (obligatorio)</label>
                    <input name="notes" class="form-control form-control-sm" required placeholder="Motivo del ajuste">
                  </div>
                  <button class="btn btn-outline-primary btn-sm w-100">Ajustar</button>
                </form>
              </div>
            </div>
          </div>

          <hr class="my-3">

          <div class="fw-semibold mb-2">Últimos movimientos</div>
          <div class="table-responsive">
            <table class="table table-sm table-striped tight tabular">
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
              @forelse($walletMovements as $m)
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
      </div>
    </div>

  </div>
  @endif


  {{-- ===========================
       TAB: INVOICES
  ============================ --}}
  @if($tab === 'invoices')
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Facturas del tenant (últimas 25)</strong>

      <form method="POST" action="{{ route('sysadmin.tenants.billing.generate-monthly', $tenant) }}?tab=invoices"
            onsubmit="return confirm('¿Generar factura mensual (manual/prueba)?');">
        @csrf
        <button class="btn btn-outline-primary btn-sm">Generar mensual</button>
      </form>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-striped tight tabular">
          <thead>
            <tr>
              <th>#</th>
              <th>Issue</th>
              <th>Due</th>
              <th>Status</th>
              <th class="text-end">Total</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          @forelse($invoices as $inv)
            @php
              $st = strtolower($inv->status ?? 'pending');
              $b = match($st){
                'paid'=>'success','pending'=>'warning','void'=>'secondary','canceled'=>'danger', default=>'secondary'
              };
            @endphp
            <tr>
              <td>{{ $inv->id }}</td>
              <td>{{ optional($inv->issue_date)->toDateString() ?? $inv->issue_date }}</td>
              <td>{{ $inv->due_date ?? '—' }}</td>
              <td><span class="badge bg-{{ $b }}">{{ strtoupper($st) }}</span></td>
              <td class="text-end">{{ number_format((float)$inv->total,2) }} {{ $inv->currency }}</td>
              <td class="d-flex flex-wrap gap-1">
                <a class="btn btn-outline-secondary btn-xs" href="{{ route('sysadmin.invoices.show',$inv) }}">Ver</a>

                <details class="d-inline">
                  <summary class="btn btn-success btn-xs">Marcar pagada</summary>
                  <div class="border rounded p-2 mt-1 bg-light">
                    <form method="POST"
                          action="{{ route('sysadmin.tenants.billing.invoices.mark_paid', [$tenant, $inv]) }}?tab=invoices"
                          onsubmit="return confirm('¿Marcar factura como PAGADA?');">
                      @csrf
                      <div class="mb-1">
                        <input name="payment_method" class="form-control form-control-sm" placeholder="transfer/wallet/other" value="transfer">
                      </div>
                      <div class="mb-1">
                        <input name="external_ref" class="form-control form-control-sm" placeholder="Folio/Referencia">
                      </div>
                      <div class="mb-2">
                        <input name="notes" class="form-control form-control-sm" placeholder="Nota interna">
                      </div>
                      <button class="btn btn-success btn-sm w-100">Confirmar</button>
                    </form>
                  </div>
                </details>

                <form method="POST"
                      action="{{ route('sysadmin.tenants.billing.invoices.mark_pending', [$tenant, $inv]) }}?tab=invoices"
                      onsubmit="return confirm('¿Regresar a PENDING?');">
                  @csrf
                  <button class="btn btn-outline-warning btn-xs">Pending</button>
                </form>

                <details class="d-inline">
                  <summary class="btn btn-outline-danger btn-xs">Void</summary>
                  <div class="border rounded p-2 mt-1 bg-light">
                    <form method="POST"
                          action="{{ route('sysadmin.tenants.billing.invoices.void', [$tenant, $inv]) }}?tab=invoices"
                          onsubmit="return confirm('¿Anular (VOID) la factura?');">
                      @csrf
                      <div class="mb-2">
                        <input name="reason" class="form-control form-control-sm" required placeholder="Motivo">
                      </div>
                      <button class="btn btn-danger btn-sm w-100">Anular</button>
                    </form>
                  </div>
                </details>

              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-muted">Sin facturas.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>

      <div class="text-muted small">
        Recomendación operativa: cuando paguen por transferencia, normalmente haces:
        (1) <strong>marcar factura pagada</strong> y (2) si aplica, <strong>activar</strong> tenant.
      </div>
    </div>
  </div>
  @endif


  {{-- ===========================
       TAB: USERS
  ============================ --}}
  @if($tab === 'users')
  <div class="card">
    <div class="card-header"><strong>Usuarios del tenant (últimos 30)</strong></div>
    <div class="card-body">

      <div class="alert alert-warning small">
        “Enviar reset link” requiere que SMTP esté configurado. Si aún no hay correo estable, usa “Set password” manual.
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-striped tight">
          <thead>
            <tr>
              <th>#</th>
              <th>Nombre</th>
              <th>Email</th>
              <th>Roles</th>
              <th>Verificado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          @forelse($users as $u)
            <tr>
              <td>{{ $u->id }}</td>
              <td>{{ $u->name }}</td>
              <td class="tabular">{{ $u->email }}</td>
              <td class="small">
                @if($u->is_sysadmin) <span class="badge bg-danger">sysadmin</span> @endif
                @if($u->is_admin) <span class="badge bg-primary">admin</span> @endif
                @if(!$u->is_admin && !$u->is_sysadmin) <span class="badge bg-secondary">user</span> @endif
              </td>
              <td>
                @if($u->email_verified_at)
                  <span class="badge bg-success">Sí</span>
                @else
                  <span class="badge bg-warning">No</span>
                @endif
              </td>
              <td class="d-flex flex-wrap gap-1">

                @if(!$u->email_verified_at)
                  <form method="POST" action="{{ route('sysadmin.tenants.billing.users.verify_email', [$tenant,$u]) }}?tab=users">
                    @csrf
                    <button class="btn btn-success btn-xs">Verificar</button>
                  </form>
                @else
                  <form method="POST" action="{{ route('sysadmin.tenants.billing.users.unverify_email', [$tenant,$u]) }}?tab=users"
                        onsubmit="return confirm('¿Marcar como NO verificado?');">
                    @csrf
                    <button class="btn btn-outline-warning btn-xs">Unverify</button>
                  </form>
                @endif

                <form method="POST" action="{{ route('sysadmin.tenants.billing.users.revoke_tokens', [$tenant,$u]) }}?tab=users"
                      onsubmit="return confirm('¿Revocar tokens? Cerrará sesiones API (Sanctum).');">
                  @csrf
                  <button class="btn btn-outline-danger btn-xs">Revoke tokens</button>
                </form>

                <form method="POST" action="{{ route('sysadmin.tenants.billing.users.send_reset_link', [$tenant,$u]) }}?tab=users"
                      onsubmit="return confirm('¿Enviar enlace de recuperación por email?');">
                  @csrf
                  <button class="btn btn-outline-primary btn-xs">Reset link</button>
                </form>

                <details class="d-inline">
                  <summary class="btn btn-secondary btn-xs">Set password</summary>
                  <div class="border rounded p-2 mt-1 bg-light" style="min-width: 240px;">
                    <form method="POST" action="{{ route('sysadmin.tenants.billing.users.set_password', [$tenant,$u]) }}?tab=users"
                          onsubmit="return confirm('¿Cambiar password?');">
                      @csrf
                      <div class="mb-2">
                        <input name="password" type="text" class="form-control form-control-sm"
                               placeholder="Nuevo password (min 8)" required minlength="8">
                      </div>
                      <button class="btn btn-dark btn-sm w-100">Guardar</button>
                    </form>
                  </div>
                </details>

              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-muted">Sin usuarios.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>

      <div class="text-muted small">
        Nota: esta tabla muestra los últimos 30 usuarios. Si luego quieres filtros/CRUD completo, lo añadimos sin salir de esta misma pantalla.
      </div>

    </div>
  </div>
  @endif


  {{-- ===========================
     TAB: VEHICLES
============================ --}}
@if($tab === 'vehicles')
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Vehículos del tenant</strong>

      <form class="d-flex gap-2" method="GET" action="{{ route('sysadmin.tenants.billing.show',$tenant) }}">
        <input type="hidden" name="tab" value="vehicles">
        <input type="text" name="vq" value="{{ $vq ?? request('vq') }}" class="form-control form-control-sm"
               placeholder="Buscar eco, placa, marca, modelo...">

        <select name="vactive" class="form-select form-select-sm" style="max-width: 160px;">
          @php $va = (string)($vactive ?? request('vactive','')); @endphp
          <option value="" @selected($va==='')>Todos</option>
          <option value="1" @selected($va==='1')>Activos</option>
          <option value="0" @selected($va==='0')>Inactivos</option>
        </select>

        <button class="btn btn-primary btn-sm">Filtrar</button>
      </form>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-striped tight tabular mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Eco</th>
              <th>Placa</th>
              <th>Tipo</th>
              <th>Marca/Modelo</th>
              <th>Año</th>
              <th>Docs</th>
              <th>Activo</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          @forelse(($vehicles ?? []) as $v)
            <tr>
              <td>{{ $v->id }}</td>
              <td>{{ $v->economico }}</td>
              <td>{{ $v->plate }}</td>
              <td><span class="badge bg-secondary">{{ $v->type ?? '—' }}</span></td>
              <td>{{ trim(($v->brand ?? '').' '.($v->model ?? '')) ?: '—' }}</td>
              <td>{{ $v->year ?? '—' }}</td>
              <td>{{ $v->documents_count ?? 0 }}</td>
              <td>
                @if($v->active)
                  <span class="badge bg-success">Sí</span>
                @else
                  <span class="badge bg-secondary">No</span>
                @endif
              </td>
              <td class="text-end d-flex justify-content-end gap-1 flex-wrap">

                {{-- Docs SysAdmin --}}
                <a class="btn btn-outline-primary btn-xs"
                   href="{{ route('sysadmin.vehicles.documents.index', ['tenant'=>$tenant->id, 'vehicle'=>$v->id]) }}">
                  Docs
                </a>

                {{-- Verificación (detalle en cola) --}}
                <a class="btn btn-outline-secondary btn-xs"
                   href="{{ route('sysadmin.verifications.vehicles.show', ['vehicle'=>$v->id]) }}">
                  Verif
                </a>

                {{-- Toggle activo --}}
                <form method="POST"
                      action="{{ route('sysadmin.tenants.billing.vehicles.toggle_active', ['tenant'=>$tenant->id, 'vehicle'=>$v->id]) }}?tab=vehicles"
                      onsubmit="return confirm('¿Cambiar estado activo/inactivo del vehículo?');">
                  @csrf
                  <button class="btn btn-outline-danger btn-xs">
                    {{ $v->active ? 'Desactivar' : 'Activar' }}
                  </button>
                </form>

              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-muted">Sin vehículos.</td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(isset($vehicles) && method_exists($vehicles,'links'))
      <div class="card-footer">
        {{ $vehicles->links() }}
      </div>
    @endif

  </div>
@endif

@if($tab === 'partners')
  @includeIf('sysadmin.tenants.billing.partners.index', [
    'tenant' => $tenant,
    'partners' => $partners ?? collect(),
    'partnerTopups' => $partnerTopups ?? collect(),
    'pstatus' => $pstatus ?? request('pstatus','pending'),
  ])
@endif
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Partners del tenant</strong>

    <form class="d-flex gap-2" method="GET" action="{{ route('sysadmin.tenants.billing.show',$tenant) }}">
      <input type="hidden" name="tab" value="partners">
      <input type="text" name="pq" value="{{ $pq ?? '' }}" class="form-control form-control-sm"
             placeholder="Buscar por nombre/email/teléfono...">
      <button class="btn btn-primary btn-sm">Buscar</button>
    </form>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped tight mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Tel</th>
            <th>Status</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($partners as $p)
          <tr>
            <td>{{ $p->id }}</td>
            <td>{{ $p->name ?? '—' }}</td>
            <td>{{ $p->email ?? '—' }}</td>
            <td>{{ $p->phone ?? '—' }}</td>
            <td>
              @php $st = strtolower((string)($p->status ?? 'active')); @endphp
              <span class="badge bg-{{ $st==='active' ? 'success' : 'secondary' }}">{{ strtoupper($st) }}</span>
            </td>
           <td class="text-end">
  <div class="d-inline-flex gap-1">
    <a class="btn btn-outline-primary btn-xs"
       href="{{ route('sysadmin.partners.billing.show', ['tenant' => $tenant->id, 'partner' => $p->id]) }}">
      Ver billing
    </a>
  </div>
</td>

          </tr>
        @empty
          <tr><td colspan="6" class="text-muted">Sin partners.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if(method_exists($partners,'links'))
    <div class="card-footer">
      {{ $partners->links() }}
    </div>
  @endif
</div>



</div>
@endsection
