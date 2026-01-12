@php
  $badge = function ($status) {
    return match($status) {
      'pending_review' => 'bg-warning-lt text-warning',
      'credited'       => 'bg-success-lt text-success',
      'approved'       => 'bg-success-lt text-success',
      'rejected'       => 'bg-danger-lt text-danger',
      default          => 'bg-secondary-lt text-secondary',
    };
  };

  $statusLabel = function ($status) {
    return match($status) {
      'pending_review' => 'Pendiente',
      'credited'       => 'Acreditado',
      'approved'       => 'Aprobado',
      'rejected'       => 'Rechazado',
      default          => $status ?: '—',
    };
  };
@endphp

<div class="table-responsive">
  <table class="table table-vcenter card-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Tenant</th>
        <th>Monto</th>
        <th>Referencia / Rastreo</th>
        <th>Fecha reportada</th>
        <th>Cuenta destino</th>
        <th>Comprobante</th>
        <th>Estado</th>
        <th class="w-1"></th>
      </tr>
    </thead>
    <tbody>
    @forelse($items as $it)
      @php
        $meta = (array)($it->meta ?? []);
        $transfer = (array)($meta['transfer'] ?? []);
        $slot = $transfer['account_slot'] ?? ($it->provider_account_slot ?? null);
        $hasProof = !empty($it->proof_path);
      @endphp
      <tr>
        <td class="text-muted">{{ $it->id }}</td>
        <td>
          <div class="fw-semibold">T{{ $it->tenant_id }}</div>
          <div class="text-muted small mono">{{ $it->external_reference ?? '—' }}</div>
        </td>
        <td class="fw-semibold">${{ number_format((float)$it->amount, 2) }} MXN</td>
        <td class="mono">{{ $it->bank_ref ?? '—' }}</td>
        <td class="text-muted">{{ $it->deposited_at?->format('Y-m-d H:i') ?? '—' }}</td>
        <td>
          <span class="badge bg-secondary-lt text-secondary">
            Cuenta {{ $slot ? '#'.$slot : '—' }}
          </span>
        </td>
        <td>
          @if($hasProof)
            <span class="badge bg-success-lt text-success">Sí</span>
          @else
            <span class="badge bg-secondary-lt text-secondary">No</span>
          @endif
        </td>
        <td>
          <span class="badge {{ $badge($it->status) }}">{{ $statusLabel($it->status) }}</span>
        </td>
        <td>
          <a class="btn btn-sm btn-outline-primary"
             href="{{ route('sysadmin.topups.transfer.show', $it->id) }}">
            Ver
          </a>
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="9" class="text-center text-muted py-4">
          No hay transferencias.
        </td>
      </tr>
    @endforelse
    </tbody>
  </table>
</div>
