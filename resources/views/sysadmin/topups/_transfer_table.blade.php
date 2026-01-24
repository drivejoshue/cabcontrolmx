@php use Illuminate\Support\Facades\Storage; @endphp

<table class="table table-striped mb-0">
  <thead>
    <tr>
      <th>ID</th>
      <th>Tenant</th>
      <th>Partner</th>
      <th>Monto</th>
      <th>Ref</th>
      <th>Boucher</th>
      <th>Estatus</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    @forelse($items as $t)
      <tr>
        <td>#{{ $t->id }}</td>
        <td>{{ $t->tenant_id }}</td>
        <td>{{ $t->partner_id }}</td>
        <td>${{ number_format($t->amount,2) }} {{ $t->currency }}</td>
        <td>{{ $t->bank_ref ?? $t->external_reference ?? '-' }}</td>

        <td>
          @if($t->proof_path)
            <a target="_blank" href="{{ Storage::disk('public')->url($t->proof_path) }}">Ver</a>
          @else
            <span class="text-muted">—</span>
          @endif
        </td>

        <td>{{ $t->status }}</td>

        <td class="text-end">
          <a class="btn btn-sm btn-outline-primary"
             href="{{ route('sysadmin.topups.partner_transfer.show', $t) }}">
             Revisar
          </a>
        </td>
      </tr>
    @empty
      <tr><td colspan="8" class="text-muted p-3">Sin solicitudes.</td></tr>
    @endforelse
  </tbody>
</table>
