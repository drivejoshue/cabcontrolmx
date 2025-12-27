@extends('layouts.sysadmin')

@section('title','Lead #'.$lead->id.' · Contacto')

@section('content')
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <div class="text-muted small">Lead</div>
      <h1 class="h4 mb-0">#{{ $lead->id }} · {{ $lead->contact_name }}</h1>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('sysadmin.leads.index') }}" class="btn btn-outline-secondary btn-sm">
        Volver
      </a>
    </div>
  </div>

  <div class="row g-3">

    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
          <div class="fw-semibold">Mensaje</div>

          @php
            $status = $lead->status ?: 'new';
            $badge = match($status) {
              'new' => 'bg-danger',
              'contacted' => 'bg-primary',
              'closed' => 'bg-success',
              default => 'bg-secondary'
            };
            $label = match($status) {
              'new' => 'Nuevo',
              'contacted' => 'En seguimiento',
              'closed' => 'Cerrado',
              default => $status
            };
          @endphp
          <span class="badge {{ $badge }}">{{ $label }}</span>
        </div>

        <div class="card-body">
          @if($lead->message)
            <div class="fs-6" style="white-space: pre-wrap;">{{ $lead->message }}</div>
          @else
            <div class="text-muted">— Sin mensaje —</div>
          @endif
        </div>

        <div class="card-footer bg-white text-muted small">
          Recibido: {{ optional($lead->created_at)->format('Y-m-d H:i:s') }}
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Datos de contacto</div>
        <div class="card-body">
          <div class="mb-2">
            <div class="text-muted small">Email</div>
            <div class="fw-semibold">
              <a href="mailto:{{ $lead->contact_email }}">{{ $lead->contact_email }}</a>
            </div>
          </div>

          <div class="mb-2">
            <div class="text-muted small">Teléfono</div>
            <div class="fw-semibold">
              @if($lead->contact_phone)
                <a href="tel:{{ $lead->contact_phone }}">{{ $lead->contact_phone }}</a>
              @else
                —
              @endif
            </div>
          </div>

          <hr>

          <div class="mb-2">
            <div class="text-muted small">Central</div>
            <div class="fw-semibold">{{ $lead->central_name ?: '—' }}</div>
          </div>

          <div class="mb-2">
            <div class="text-muted small">Ciudad / Estado</div>
            <div class="fw-semibold">
              {{ $lead->city ?: '—' }} / {{ $lead->state ?: '—' }}
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Acciones</div>
        <div class="card-body">

          <form method="POST" action="{{ route('sysadmin.leads.status', $lead) }}">
            @csrf
            <label class="form-label">Cambiar estado</label>
            <select name="status" class="form-select mb-2" required>
              <option value="new" {{ ($lead->status === 'new') ? 'selected' : '' }}>Nuevo</option>
              <option value="contacted" {{ ($lead->status === 'contacted') ? 'selected' : '' }}>En seguimiento</option>
              <option value="closed" {{ ($lead->status === 'closed') ? 'selected' : '' }}>Cerrado</option>
            </select>
            <button class="btn btn-primary w-100">
              Guardar
            </button>
          </form>

          <hr>

          <div class="small text-muted">
            <div><b>Fuente:</b> {{ $lead->source ?: 'landing' }}</div>
            <div><b>IP:</b> {{ $lead->ip ?: '—' }}</div>
            <div class="text-truncate"><b>User-Agent:</b> {{ $lead->user_agent ?: '—' }}</div>
          </div>

        </div>
      </div>

    </div>

  </div>

</div>
@endsection
