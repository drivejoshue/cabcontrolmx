@extends('layouts.partner')

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Cuenta</div>
        <h2 class="page-title">Ticket #{{ $thread->id }}</h2>
        <div class="text-muted">{{ $thread->subject }}</div>
      </div>

      <div class="col-auto ms-auto d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('partner.support.index', request()->query()) }}">
          <i class="ti ti-arrow-left me-1"></i> Volver
        </a>

        @if($thread->status !== 'closed')
          <form method="POST" action="{{ route('partner.support.close', $thread->id) }}">
            @csrf
            <button class="btn btn-outline-danger">
              <i class="ti ti-lock me-1"></i> Cerrar
            </button>
          </form>
        @endif
      </div>
    </div>
  </div>

  @php
    $st = (string)$thread->status;
    $stBadge = match($st){
      'open'        => 'bg-red-lt text-red',
      'in_progress' => 'bg-yellow-lt text-yellow',
      'resolved'    => 'bg-green-lt text-green',
      'closed'      => 'bg-secondary-lt text-secondary',
      default       => 'bg-secondary-lt text-secondary',
    };
    $prio = (string)($thread->priority ?? 'normal');
    $pBadge = match($prio){
      'urgent' => 'bg-red-lt text-red',
      'high'   => 'bg-orange-lt text-orange',
      'low'    => 'bg-secondary-lt text-secondary',
      default  => 'bg-blue-lt text-blue',
    };
    $cat = (string)($thread->category ?? 'other');
    $catLabel = [
      'taxi_stand'=>'TaxiStand / Bases',
      'tariff'=>'Tarifas',
      'bug'=>'Bug / Inconsistencia',
      'suggestion'=>'Sugerencia',
      'other'=>'Otro',
    ][$cat] ?? $cat;
  @endphp

  {{-- resumen --}}
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small">Estado</div>
          <div class="mt-1"><span class="badge {{ $stBadge }}">{{ $st }}</span></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small">Prioridad</div>
          <div class="mt-1"><span class="badge {{ $pBadge }}">{{ $prio }}</span></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small">Categoría</div>
          <div class="fw-semibold mt-1">{{ $catLabel }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small">Última actividad</div>
          <div class="fw-semibold mt-1">
            {{ $thread->last_message_at ? \Carbon\Carbon::parse($thread->last_message_at)->format('Y-m-d H:i') : '-' }}
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- conversación --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="fw-semibold">Conversación</div>
      <div class="text-muted small">Mensajes en orden</div>
    </div>

    <div class="card-body">
      @forelse($messages as $m)
        @php
          $role = (string)($m->author_role ?? '');
          $isPartner = $role === 'partner';
          $dt = !empty($m->created_at) ? \Carbon\Carbon::parse($m->created_at)->format('Y-m-d H:i') : '-';
        @endphp

        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <div class="fw-semibold">
              @if($isPartner)
                <span class="badge bg-blue-lt text-blue">Partner</span>
              @else
                <span class="badge bg-secondary-lt text-secondary">Tenant</span>
              @endif
            </div>
            <div class="text-muted small">{{ $dt }}</div>
          </div>
          <div class="mt-2">
            <div class="p-3 rounded" style="background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.06);">
              {!! nl2br(e($m->message)) !!}
            </div>
          </div>
        </div>

      @empty
        <div class="text-muted">Aún no hay mensajes.</div>
      @endforelse
    </div>
  </div>

  {{-- reply --}}
  <div class="card">
    <div class="card-header">
      <div class="fw-semibold">Responder</div>
    </div>

    <div class="card-body">
      @if($thread->status === 'closed')
        <div class="text-muted">El ticket está cerrado. Si necesitas reabrirlo, crea uno nuevo.</div>
      @else
        <form method="POST" action="{{ route('partner.support.reply', $thread->id) }}">
          @csrf
          <div class="mb-3">
            <textarea name="message" class="form-control" rows="5" required maxlength="8000"
                      placeholder="Escribe tu respuesta...">{{ old('message') }}</textarea>
            @error('message') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
          </div>

          <button class="btn btn-primary">
            <i class="ti ti-send me-1"></i> Enviar
          </button>
        </form>
      @endif
    </div>
  </div>

</div>
@endsection
