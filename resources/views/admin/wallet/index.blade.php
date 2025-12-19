
@extends('layouts.admin')

@section('title', 'Wallet de la central')

@push('styles')
<style>
    .kpi { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
    .soft-card { border: 1px solid rgba(0,0,0,.06); }
    [data-theme="dark"] .soft-card { border-color: rgba(255,255,255,.10); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
</style>
@endpush

@section('content')
@php
    $bal = (float)($wallet->balance ?? 0);
    $cur = $wallet->currency ?? 'MXN';

    $nextAmount = is_array($nextCharge) ? (float)($nextCharge['amount'] ?? 0) : (is_object($nextCharge) ? (float)($nextCharge->amount ?? 0) : 0);
    $nextLabel  = is_array($nextCharge) ? ($nextCharge['label'] ?? null) : (is_object($nextCharge) ? ($nextCharge->label ?? null) : null);
@endphp

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="mb-0">Wallet</h3>
            <div class="text-muted small">Fondos disponibles para cubrir cargos del plan</div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.billing.plan') }}" class="btn btn-outline-secondary">
                Volver a Plan
            </a>
            <a href="{{ route('admin.wallet.topup.create') }}" class="btn btn-primary">
                Recargar
            </a>
        </div>
    </div>

    @if(session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
    @endif
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">

        <div class="col-12 col-lg-5">
            <div class="card soft-card">
                <div class="card-header"><strong>Saldo</strong></div>
                <div class="card-body">
                    <div class="kpi">
                        ${{ number_format($bal, 2) }} <span class="fs-6">{{ $cur }}</span>
                    </div>

                    <div class="text-muted small mt-1">
                        @if(!empty($wallet->last_topup_at))
                            Última recarga: {{ \Carbon\Carbon::parse($wallet->last_topup_at)->format('d M Y H:i') }}
                        @else
                            Sin recargas registradas aún.
                        @endif
                    </div>

                    <hr>

                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('admin.wallet.topup.create') }}" class="btn btn-primary">
                            Recargar wallet
                        </a>

                        @if($nextAmount > 0)
                            <a href="{{ route('admin.wallet.topup.create', ['amount' => $nextAmount]) }}"
                               class="btn btn-outline-primary">
                                Recargar monto sugerido
                            </a>
                        @endif
                    </div>

                    @if($nextAmount > 0)
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="fw-semibold">Siguiente cargo estimado</div>
                            <div class="text-muted small">
                                {{ $nextLabel ?: 'Próximo ciclo' }}
                            </div>
                            <div class="fw-bold mt-1">
                                ${{ number_format($nextAmount, 2) }} {{ $cur }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recarga manual rápida (simulación) --}}
            <div class="card soft-card mt-3">
                <div class="card-header"><strong>Recarga manual (simulación)</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.wallet.topup.manual') }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Monto</label>
                            <input type="number" step="0.01" name="amount"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   min="10" max="200000" placeholder="Ej. 1500">
                            @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notas (opcional)</label>
                            <input type="text" name="notes"
                                   class="form-control @error('notes') is-invalid @enderror"
                                   maxlength="255" placeholder="Ej. Recarga para cubrir el mes">
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-primary">Aplicar recarga</button>
                        </div>

                        <div class="col-12 text-muted small">
                            Esto es temporal para pruebas. Después se reemplaza por MercadoPago/Conekta (OXXO, SPEI, tarjeta).
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card soft-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Movimientos recientes</strong>
                    <span class="text-muted small">Últimos 100</span>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th class="text-end">Monto</th>
                                <th>Referencia</th>
                                <th>Notas</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($movements as $m)
                                @php
                                    $type = strtolower((string)$m->type);
                                    $isIn = in_array($type, ['topup','credit','refund','adjust'], true);
                                @endphp
                                <tr>
                                    <td class="mono">{{ $m->id }}</td>
                                    <td>{{ \Carbon\Carbon::parse($m->created_at)->format('d M Y H:i') }}</td>
                                    <td>
                                        <span class="badge {{ $isIn ? 'bg-success' : 'bg-danger' }} text-uppercase">
                                            {{ $type }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold {{ $isIn ? 'text-success' : 'text-danger' }}">
                                        {{ $isIn ? '+' : '-' }} ${{ number_format((float)$m->amount, 2) }}
                                    </td>
                                    <td class="mono small">
                                        {{ $m->external_ref ?: ($m->ref_type ? ($m->ref_type.'#'.$m->ref_id) : '—') }}
                                    </td>
                                    <td class="small text-muted">{{ $m->notes ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Aún no hay movimientos en el wallet.
                                    </td>
                                </tr>
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
