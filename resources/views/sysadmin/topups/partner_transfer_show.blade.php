@extends('layouts.sysadmin')

@section('title', 'Transferencia Partner #' . $topup->id)

@php
  use Illuminate\Support\Facades\Storage;

  $proofUrl = $topup->proof_path ? asset('storage/'.$topup->proof_path) : null;
// o: url('/storage/'.$topup->proof_path)

  $ext = $topup->proof_path ? strtolower(pathinfo($topup->proof_path, PATHINFO_EXTENSION)) : '';
  $isImg = in_array($ext, ['jpg','jpeg','png','webp']);
  $isPdf = ($ext === 'pdf');

  $status = (string)($topup->status ?? '');
  $badge = match($status) {
    'pending_review' => 'bg-yellow-lt text-yellow',
    'approved'       => 'bg-blue-lt text-blue',
    'credited'       => 'bg-green-lt text-green',
    'rejected'       => 'bg-red-lt text-red',
    default          => 'bg-secondary-lt'
  };

  $statusLabel = match($status) {
    'pending_review' => 'Pendiente',
    'approved'       => 'Aprobada',
    'credited'       => 'Acreditada',
    'rejected'       => 'Rechazada',
    default          => $status ?: '—'
  };

  $canAct = $status === 'pending_review';
@endphp

@section('content')
<style>
  .timeline-event-icon{
    width: 10px !important;
    height: 10px !important;
    border-radius: 999px !important;
    margin-top: .35rem; /* alinea con el título */
  }
</style>

<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="row g-2 align-items-center">
        <div class="col">
          <div class="page-pretitle">SysAdmin · Topups · Partner Transfer</div>
          <h2 class="page-title d-flex align-items-center gap-2">
            Transferencia #{{ $topup->id }}
            <span class="badge {{ $badge }}">{{ $statusLabel }}</span>
          </h2>
          <div class="text-muted mt-1">
            Tenant <span class="fw-semibold">{{ $topup->tenant_id }}</span> ·
            Partner <span class="fw-semibold">{{ $topup->partner_id }}</span> ·
            ${{ number_format((float)$topup->amount,2) }} {{ $topup->currency ?? 'MXN' }}
          </div>
        </div>

        <div class="col-auto ms-auto d-print-none">
          <div class="btn-list">
            <a href="{{ route('sysadmin.topups.partner_transfer.index', ['status' => request('status','pending_review')]) }}"
               class="btn btn-outline-secondary">
              Volver
            </a>

            @if($proofUrl)
              <a class="btn btn-outline-primary" target="_blank" href="{{ $proofUrl }}">
                Abrir comprobante
              </a>
            @endif
          </div>
        </div>
      </div>

      {{-- Alerts --}}
      <div class="row mt-3">
        <div class="col">
          @if(session('ok'))
            <div class="alert alert-success">
              <h4 class="alert-title">Listo</h4>
              <div class="text-muted">{{ session('ok') }}</div>
            </div>
          @endif
          @if(session('warning'))
            <div class="alert alert-warning">
              <h4 class="alert-title">Atención</h4>
              <div class="text-muted">{{ session('warning') }}</div>
            </div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger">
              <h4 class="alert-title">Error</h4>
              <div class="text-muted">{{ session('error') }}</div>
            </div>
          @endif

          @if ($errors->any())
            <div class="alert alert-danger">
              <h4 class="alert-title">Revisa el formulario</h4>
              <ul class="mb-0">
                @foreach($errors->all() as $e)
                  <li>{{ $e }}</li>
                @endforeach
              </ul>
            </div>
          @endif
        </div>
      </div>

    </div>
  </div>

  <div class="page-body">
    <div class="container-xl">
      <div class="row g-3">

        {{-- Col izquierda: datos --}}
        <div class="col-lg-5">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Detalle</h3>
            </div>
            <div class="card-body">
              <dl class="row">
                <dt class="col-5 text-muted">ID</dt>
                <dd class="col-7">#{{ $topup->id }}</dd>

                <dt class="col-5 text-muted">Tenant</dt>
                <dd class="col-7">{{ $topup->tenant_id }}</dd>

                <dt class="col-5 text-muted">Partner</dt>
                <dd class="col-7">{{ $topup->partner_id }}</dd>

                <dt class="col-5 text-muted">Proveedor</dt>
                <dd class="col-7">{{ $topup->provider }}</dd>

                <dt class="col-5 text-muted">Método</dt>
                <dd class="col-7">{{ $topup->method ?? '-' }}</dd>

                <dt class="col-5 text-muted">Monto</dt>
                <dd class="col-7 fw-semibold">${{ number_format((float)$topup->amount,2) }} {{ $topup->currency ?? 'MXN' }}</dd>

                <dt class="col-5 text-muted">Referencia</dt>
                <dd class="col-7">
                  {{ $topup->bank_ref ?? $topup->external_reference ?? '—' }}
                </dd>

                <dt class="col-5 text-muted">Creado</dt>
                <dd class="col-7">{{ optional($topup->created_at)->format('Y-m-d H:i') }}</dd>

                <dt class="col-5 text-muted">Revisado</dt>
                <dd class="col-7">
                  @if($topup->reviewed_at)
                    {{ optional($topup->reviewed_at)->format('Y-m-d H:i') }}
                  @else
                    —
                  @endif
                </dd>

                <dt class="col-5 text-muted">Resultado</dt>
                <dd class="col-7">{{ $topup->review_status ?? '—' }}</dd>

                <dt class="col-5 text-muted">Notas SysAdmin</dt>
                <dd class="col-7">
                  <div class="text-wrap">{{ $topup->review_notes ?? '—' }}</div>
                </dd>
              </dl>

              @if(!empty($topup->apply_wallet_movement_id))
                <div class="mt-3">
                  <span class="badge bg-green-lt text-green">
                    movement_id: {{ $topup->apply_wallet_movement_id }}
                  </span>
                </div>
              @endif
            </div>
          </div>

          {{-- Acciones --}}
          <div class="card mt-3">
            <div class="card-header">
              <h3 class="card-title">Acciones</h3>
              @if(!$canAct)
                <div class="card-subtitle text-muted">
                  Esta transferencia ya fue procesada; no se permiten cambios.
                </div>
              @endif
            </div>

            <div class="card-body">
              <div class="row g-3">

                {{-- Aprobar --}}
                <div class="col-12">
                  <div class="p-3 border rounded">
                    <div class="fw-semibold mb-2">Aprobar y acreditar</div>
                    <form method="POST" action="{{ route('sysadmin.topups.partner_transfer.approve', $topup) }}">
                      @csrf
                      <div class="mb-2">
                        <label class="form-label">Comentario para Partner (opcional)</label>
                        <textarea name="review_notes" class="form-control" rows="2" @disabled(!$canAct)>{{ old('review_notes') }}</textarea>
                        <div class="form-hint">Se mostrará al partner en su historial de recargas.</div>
                      </div>

                      <button class="btn btn-success" @disabled(!$canAct)>
                        Aprobar y acreditar saldo
                      </button>
                    </form>
                  </div>
                </div>

                {{-- Rechazar --}}
                <div class="col-12">
                  <div class="p-3 border rounded">
                    <div class="fw-semibold mb-2 text-danger">Rechazar</div>

                    <form method="POST" action="{{ route('sysadmin.topups.partner_transfer.reject', $topup) }}">
                      @csrf

                      <div class="mb-2">
                        <label class="form-label">Motivo (requerido)</label>

                        {{-- motivo predefinido + detalle --}}
                        <select class="form-select mb-2" id="rejectReason" @disabled(!$canAct)
                                onchange="document.getElementById('rejectNotes').value = this.value;">
                          <option value="">Selecciona un motivo…</option>
                          <option value="Comprobante ilegible o incompleto.">Comprobante ilegible o incompleto</option>
                          <option value="Referencia no encontrada en estado de cuenta.">Referencia no encontrada</option>
                          <option value="Monto no coincide con el comprobante.">Monto no coincide</option>
                          <option value="Comprobante corresponde a otra transferencia.">Comprobante no corresponde</option>
                          <option value="Datos de transferencia incompletos.">Datos incompletos</option>
                          <option value="Otro (especificar en la nota).">Otro</option>
                        </select>

                        <textarea id="rejectNotes"
                                  name="review_notes"
                                  class="form-control"
                                  rows="3"
                                  required
                                  @disabled(!$canAct)>{{ old('review_notes') }}</textarea>

                        <div class="form-hint">
                          Esto se mostrará al partner. Sé específico (ej. “No aparece REF-XXXXX en banco”).
                        </div>
                      </div>

                      <button class="btn btn-danger" @disabled(!$canAct)>
                        Rechazar transferencia
                      </button>
                    </form>

                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

        {{-- Col derecha: comprobante --}}
        <div class="col-lg-7">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Comprobante</h3>
              <div class="card-actions">
                @if($proofUrl)
                  <span class="badge bg-azure-lt text-azure">{{ strtoupper($ext ?: 'FILE') }}</span>
                @else
                  <span class="badge bg-secondary-lt">Sin archivo</span>
                @endif
              </div>
            </div>

            <div class="card-body">
              @if($proofUrl)
                @if($isImg)
                  <a href="{{ $proofUrl }}" target="_blank">
                    <img src="{{ $proofUrl }}" class="img-fluid rounded border" style="max-height: 720px; width:auto;">
                  </a>
                @elseif($isPdf)
                  <div class="ratio ratio-16x9">
                    <iframe src="{{ $proofUrl }}" loading="lazy"></iframe>
                  </div>
                @else
                  <div class="text-muted mb-2">Formato no embebible. Usa “Abrir comprobante”.</div>
                  <a class="btn btn-outline-primary" target="_blank" href="{{ $proofUrl }}">Abrir</a>
                @endif
              @else
                <div class="empty">
                  <div class="empty-img"></div>
                  <p class="empty-title">No se adjuntó comprobante</p>
                  <p class="empty-subtitle text-muted">
                    El partner envió la solicitud sin evidencia. Puedes rechazar y pedir que adjunte el archivo correcto.
                  </p>
                </div>
              @endif
            </div>

            @if($proofUrl)
              <div class="card-footer">
                <div class="text-muted">
                  Ruta: <span class="fw-semibold">{{ $topup->proof_path }}</span>
                </div>
              </div>
            @endif
          </div>

          {{-- Si quieres: caja de “historial” básico (por ahora del mismo registro) --}}
          <div class="card mt-3">
            <div class="card-header">
              <h3 class="card-title">Estado y comunicación</h3>
            </div>
            <div class="card-body">
              <div class="timeline">
                <div class="timeline-event">
                  <div class="timeline-event-icon bg-blue-lt"></div>
                  <div class="timeline-event-content">
                    <div class="fw-semibold">Solicitud creada</div>
                    <div class="text-muted small">{{ optional($topup->created_at)->format('Y-m-d H:i') }}</div>
                  </div>
                </div>
          @if($topup->reviewed_at)
            <div class="timeline-event">
              <div class="timeline-event-icon {{ $topup->review_status==='rejected' ? 'bg-red' : 'bg-green' }}"></div>

              <div class="timeline-event-content ps-3">
                <div class="fw-semibold">
                  Revisada: {{ $topup->review_status ?? '—' }}
                </div>
                <div class="text-muted small">{{ optional($topup->reviewed_at)->format('Y-m-d H:i') }}</div>

                @if($topup->review_notes)
                  <div class="mt-2">
                    <div class="text-muted small">Mensaje para partner</div>
                    <div class="text-wrap">{{ $topup->review_notes }}</div>
                  </div>
                @endif
              </div>
            </div>
          @endif


                @if($topup->credited_at)
                  <div class="timeline-event">
                    <div class="timeline-event-icon bg-green-lt"></div>
                    <div class="timeline-event-content">
                      <div class="fw-semibold">Saldo acreditado</div>
                      <div class="text-muted small">{{ optional($topup->credited_at)->format('Y-m-d H:i') }}</div>
                    </div>
                  </div>
                @endif
              </div>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>
</div>
@endsection
