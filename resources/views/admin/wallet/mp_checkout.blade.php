@extends('layouts.admin')

@section('title','Procesando pago')

@section('content')
@php
  $initPoint = $initPoint ?? null;
@endphp

<div class="container-fluid">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-6">

      <div class="card shadow-sm border-0">
        <div class="card-body">
          <h4 class="mb-2">Redirigiendo a Mercado Pago…</h4>
          <div class="text-muted">
            En unos segundos abrirá el checkout para completar tu recarga.
          </div>

          <hr>

          <div class="small text-muted mb-3">
            Ref: <span class="mono">{{ $topup->external_reference }}</span><br>
            Monto: <strong>${{ number_format((float)$topup->amount, 2) }} MXN</strong><br>
            Estado: <span class="badge bg-secondary">{{ strtoupper($topup->status) }}</span>
          </div>

          @if($initPoint)
            <a href="{{ $initPoint }}" class="btn btn-primary w-100" rel="noopener">
              Abrir checkout
            </a>

            <div class="text-muted small mt-2">
              Si no se abre automáticamente, usa el botón.
            </div>
          @else
            <div class="alert alert-danger mb-0">
              No se pudo generar el enlace de pago. Intenta nuevamente.
            </div>
          @endif
        </div>
      </div>

    </div>
  </div>
</div>

@if($initPoint)
<script>
  // Redirect automático (fallback: botón)
  setTimeout(function () {
    window.location.href = @json($initPoint);
  }, 600);
</script>
@endif
@endsection
