@extends('layouts.admin')

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Soporte · Ticket</div>
        <h2 class="page-title">{{ $t->subject ?? 'Ticket' }}</h2>
        <div class="text-muted">
          Partner: <span class="fw-semibold">{{ $t->partner_name ?? ('#'.$t->partner_id) }}</span>
          · Estado: <span class="fw-semibold">{{ $t->status }}</span>
          @if(!empty($t->category)) · Categoría: {{ $t->category }} @endif
        </div>
      </div>
      <div class="col-auto ms-auto d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.partner_support.index', request()->query()) }}">
          <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header fw-semibold">Conversación</div>
        <div class="card-body">
         @forelse($messages as $m)
  @php
    $role = (string)($m->author_role ?? 'partner'); // ✅ columna real
    $isTenant = in_array($role, ['tenant','admin'], true); // ✅ según cómo lo guardes
    $dt = !empty($m->created_at) ? \Carbon\Carbon::parse($m->created_at)->format('Y-m-d H:i') : '';
  @endphp

  <div class="mb-3">
    <div class="d-flex justify-content-between">
      <div class="fw-semibold">
        {{ $isTenant ? 'Tenant' : 'Partner' }}
      </div>
      <div class="text-muted small">{{ $dt }}</div>
    </div>

    <div class="text-muted">{!! nl2br(e($m->message ?? '')) !!}</div> {{-- ✅ columna real --}}
  </div>

  <hr class="my-3">
@empty
  <div class="text-muted">Sin mensajes.</div>
@endforelse

        </div>
      </div>

      <div class="card mt-3">
        <div class="card-header fw-semibold">Responder</div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.partner_support.reply', $t->id) }}">
            @csrf
            <div class="mb-2">
              <textarea name="body" rows="4" class="form-control" placeholder="Escribe tu respuesta..." required>{{ old('body') }}</textarea>
            </div>
            <button class="btn btn-primary">
              <i class="ti ti-send me-1"></i> Enviar
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header fw-semibold">Estado</div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.partner_support.status', $t->id) }}" class="d-flex gap-2">
            @csrf
            <select name="status" class="form-select">
              @foreach(['open'=>'Abierto','in_review'=>'En revisión','closed'=>'Cerrado'] as $k=>$v)
                <option value="{{ $k }}" {{ (string)$t->status===$k ? 'selected':'' }}>{{ $v }}</option>
              @endforeach
            </select>
            <button class="btn btn-outline-secondary">
              <i class="ti ti-check me-1"></i> Guardar
            </button>
          </form>

          <div class="text-muted small mt-3">
            Recomendación: al responder, mover a “En revisión” y cerrar cuando quede resuelto.
          </div>
        </div>
      </div>
    </div>

  </div>

</div>
@endsection
