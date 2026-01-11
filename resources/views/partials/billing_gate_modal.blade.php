@php
  $bg = $billingGate ?? null;
@endphp

@if(!empty($bg['show_modal']))
@php
  $kind = (string)($bg['modal_kind'] ?? '');
  $blocking = in_array($kind, ['overdue','terms','billing_suspended','billing_canceled'], true);

  $ui = $bg['ui'] ?? [];
  $msg =
      $bg['message']
      ?? $bg['billing_message']
      ?? $ui['message']
      ?? $ui['billing_message']
      ?? '';
@endphp

<div class="modal fade" id="billingGateModal" tabindex="-1" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ $bg['title'] ?? 'Aviso' }}</h5>

        {{-- SOLO permitir cerrar si NO es bloqueante --}}
        @if(!$blocking)
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        @endif
      </div>

      <div class="modal-body">
        <p class="mb-2">{{ $msg }}</p>

        @if(!empty($ui['required_amount']))
          <div class="small text-muted">
            Requerido: <strong>${{ number_format((float)$ui['required_amount'], 2) }}</strong>
            | Saldo: <strong>${{ number_format((float)($ui['balance'] ?? 0), 2) }}</strong>
            @if(!empty($ui['due_date'])) | Vence: <strong>{{ $ui['due_date'] }}</strong> @endif
          </div>
        @endif
      </div>

      <div class="modal-footer">
        <a href="{{ route('admin.billing.plan') }}" class="btn btn-outline-secondary">
          Ver facturación
        </a>

        @if($kind === 'terms')
          <form method="POST" action="{{ route('admin.billing.accept_terms') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary">Aceptar términos</button>
          </form>

        @elseif($kind === 'overdue' || $kind === 'billing_suspended')
          <a href="{{ route('admin.billing.plan') }}" class="btn btn-primary">
            Recargar wallet
          </a>

        @else
          {{-- NO bloqueante: aquí SÍ va Entendido --}}
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
            Entendido
          </button>
        @endif
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const kind = @json($kind);
  const blocking = @json($blocking);

  const el = document.getElementById('billingGateModal');
  if (!el) return;

  // Mostrar (bootstrap si existe)
  if (window.bootstrap?.Modal) {
    const m = new bootstrap.Modal(el);
    m.show();

    // Si es bloqueante, aunque intenten cerrarlo por eventos raros,
    // reabrimos y/o redirigimos a facturación.
    if (blocking) {
      el.addEventListener('hide.bs.modal', function (e) {
        e.preventDefault();
        window.location.href = @json(route('admin.billing.plan'));
      });
    }
    return;
  }

  // Fallback: forzar visible + sin salida
  el.classList.add('show');
  el.style.display = 'block';
  document.body.classList.add('modal-open');

  const bd = document.createElement('div');
  bd.className = 'modal-backdrop fade show';
  document.body.appendChild(bd);

  if (blocking) {
    // Sin handlers de cierre: el usuario debe ir a Billing.
  }
});
</script>
@endif
