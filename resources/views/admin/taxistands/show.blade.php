<?php /** @var object $stand */ ?>
@extends('layouts.admin')
@section('title','Paradero #'.$stand->id)

@push('styles')
<style>
  .help-xs{ font-size:.85rem; }
  .qr-wrap{
    display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap;
  }
  .qr-box{
    background:#fff;
    border:1px solid rgba(98,105,118,.25);
    border-radius:.75rem;
    padding:14px;
    width: 260px;
  }
  .qr-box #qr { display:flex; justify-content:center; }
  .dl-compact dt{ color: #626976; font-weight:600; }
  .dl-compact dd{ margin-bottom:.5rem; }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h3 class="mb-0">Paradero #{{ $stand->id }}</h3>
      <div class="text-muted help-xs">Vista completa del paradero y QR para enrolar conductores.</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.taxistands.index') }}" class="btn btn-outline-secondary">
        <i data-feather="arrow-left"></i> Volver
      </a>
      <a href="{{ route('admin.taxistands.edit',$stand->id) }}" class="btn btn-primary">
        <i data-feather="edit-2"></i> Editar
      </a>
    </div>
  </div>

  <div class="row g-3">
    {{-- Datos --}}
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">Datos del paradero</h3>
        </div>
        <div class="card-body">
          <dl class="row mb-0 dl-compact">
            <dt class="col-sm-4">Nombre</dt>
            <dd class="col-sm-8">{{ $stand->nombre }}</dd>

            <dt class="col-sm-4">Sector</dt>
            <dd class="col-sm-8">
              {{-- Si traes sector_nombre úsalo; si no, muestra el id --}}
              {{ $stand->sector_nombre ?? $stand->sector_id ?? '—' }}
            </dd>

            <dt class="col-sm-4">Coordenadas</dt>
            <dd class="col-sm-8">
              <span class="text-muted">{{ number_format($stand->latitud,6) }}, {{ number_format($stand->longitud,6) }}</span>
            </dd>

            <dt class="col-sm-4">Capacidad</dt>
            <dd class="col-sm-8">{{ $stand->capacidad ?? 0 }}</dd>

            <dt class="col-sm-4">Activo</dt>
            <dd class="col-sm-8">
              @if(($stand->activo ?? $stand->active ?? 1) == 1)
                <span class="badge bg-success-lt text-success">Activo</span>
              @else
                <span class="badge bg-secondary-lt text-secondary">Inactivo</span>
              @endif
            </dd>

            <dt class="col-sm-4">Código</dt>
            <dd class="col-sm-8"><code>{{ $stand->codigo }}</code></dd>

            <dt class="col-sm-4">QR Secret</dt>
            <dd class="col-sm-8"><code>{{ $stand->qr_secret }}</code></dd>
          </dl>

          <hr class="my-3">

          <div class="text-muted help-xs">
            Recomendación: mantén el paradero “Activo” para permitir enrolamiento y asignación desde cola.
          </div>
        </div>
      </div>
    </div>

    {{-- QR grande + instrucciones --}}
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header">
          <h3 class="card-title mb-0">QR para enrolar en la base</h3>
        </div>
        <div class="card-body">
          <div class="qr-wrap">
            <div class="qr-box">
              <div id="qr"></div>
              <div class="text-center mt-2">
                <span class="badge bg-primary-lt text-primary">Imprimible</span>
              </div>
            </div>

            <div class="flex-grow-1">
              <div class="fw-semibold mb-2">Instrucciones</div>
              <ol class="mb-2 help-xs">
                <li>Imprime este QR y colócalo en el paradero/base.</li>
                <li>En la app del conductor, abre <span class="fw-semibold">Bases / Taxi Stands</span> y selecciona <span class="fw-semibold">Escanear QR</span>.</li>
                <li>El conductor escanea el QR para <span class="fw-semibold">ingresar a la cola</span> de esta base.</li>
                <li>Cuando salga de la base o termine turno, puede <span class="fw-semibold">salir de la cola</span> desde la app.</li>
              </ol>

              <div class="alert alert-info py-2 mb-0 help-xs">
                <div class="fw-semibold">Tip</div>
                Si el QR se filtra, puedes regenerarlo (si implementas esa acción) o cambiar la base de ubicación y reimprimir.
              </div>

              <div class="mt-3">
                <div class="text-muted help-xs mb-1">Valor que leerá la app:</div>
                <code class="d-block p-2 rounded bg-light">{{ $stand->qr_secret }}</code>
              </div>
            </div>
          </div>

        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i data-feather="printer"></i> Imprimir
          </button>
          <a href="{{ route('admin.taxistands.edit',$stand->id) }}" class="btn btn-primary">
            <i data-feather="edit-2"></i> Editar
          </a>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const target = @json($stand->qr_secret);
  const el = document.getElementById('qr');
  if (el) {
    // QR más grande
    new QRCode(el, { text: target, width: 220, height: 220 });
  }
});
</script>
@endpush
