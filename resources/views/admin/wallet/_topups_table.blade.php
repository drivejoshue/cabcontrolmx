<div class="table-responsive">
  <table class="table table-striped align-middle mb-0">
    <thead>
    <tr>
      <th>#</th>
      <th>Creada</th>
      <th>Provider</th>
      <th>Status</th>
      <th>Ref</th>
      <th class="text-end">Monto</th>
      <th class="text-end">Acción</th>
    </tr>
    </thead>
    <tbody>
    @forelse($topups as $t)
      @php
        $st = strtolower($t->status ?? 'pending');
        $badge = match($st) {
          'approved' => 'success',
          'pending' => 'warning',
          'initiated' => 'secondary',
          'rejected' => 'danger',
          'canceled' => 'dark',
          'expired' => 'secondary',
          default => 'secondary',
        };
      @endphp
      <tr>
        <td class="text-muted">#{{ $t->id }}</td>
        <td>{{ \Carbon\Carbon::parse($t->created_at)->toDateTimeString() }}</td>
        <td class="text-uppercase small">{{ $t->provider ?? '—' }}</td>
        <td>
          <span class="badge bg-{{ $badge }} text-uppercase">{{ $t->status ?? '—' }}</span>
          @if(!empty($t->credited_at))
            <div class="small text-muted">Acreditada: {{ \Carbon\Carbon::parse($t->credited_at)->toDateTimeString() }}</div>
          @endif
        </td>
        <td class="mono small text-muted">
          {{ $t->external_reference ?? '—' }}
          @if(!empty($t->mp_payment_id))
            <div class="small">mp_payment_id: {{ $t->mp_payment_id }}</div>
          @endif
        </td>
        <td class="text-end fw-semibold">
          ${{ number_format((float)$t->amount, 2) }} <span class="text-muted small">{{ $t->currency ?? 'MXN' }}</span>
        </td>
        <td class="text-end">
          @if(!empty($t->init_point) && in_array($st, ['pending','initiated']))
            <a href="{{ $t->init_point }}" target="_blank" class="btn btn-outline-primary btn-sm">
              Pagar
            </a>
          @else
            <span class="text-muted small">—</span>
          @endif
        </td>
      </tr>
    @empty
      <tr>
        <td colspan="7" class="text-center py-3 text-muted">
          Aún no hay recargas registradas.
        </td>
      </tr>
    @endforelse
    </tbody>
  </table>
</div>
