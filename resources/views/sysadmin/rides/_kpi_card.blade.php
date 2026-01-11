<div class="card h-100">
  <div class="card-body">
    <div class="text-muted">{{ $label ?? '—' }}</div>

    <div class="d-flex align-items-end justify-content-between mt-1">
      <div class="h2 mb-0">{{ $value ?? '—' }}</div>
      @if(!empty($badge))
        <span class="badge {{ $badgeClass ?? 'bg-secondary' }}">{{ $badge }}</span>
      @endif
    </div>

    @if(!empty($hint))
      <div class="text-muted small mt-1">{{ $hint }}</div>
    @endif
  </div>
</div>
